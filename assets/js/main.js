/* =========================================================
   원더(wonder) 랜딩페이지 — 상담신청 전환 최적화
   의존성 없음 (vanilla JS)
   ========================================================= */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* -------------------------------------------------------
     설정
     ------------------------------------------------------- */

  /* 상담 신청 전송 엔드포인트.
     null 이면 전송하지 않고 완료 화면만 표시합니다(데모 모드).
     실제 연동 시 백엔드 URL을 지정하세요. 예) '/api/apply' */
  var APPLY_ENDPOINT = 'api/apply.php';

  /* 수익 계산 계수.
     공식 원더 페이지 기본값(건강 10만 + 자동차 10만 → 보험소득 138만원)을
     그대로 재현합니다. 실제 수수료율에 맞춰 여기만 수정하세요. */
  var CALC = {
    healthRate: 13,
    driverRate: 0.8,
    bonusFirst:  100000,
    bonusDouble: 200000,
    bonusTriple: 300000,
    thresholdDouble:  50000,
    thresholdTriple: 100000,
    maxInput: 10000000
  };

  /* 상담 유도 팝업: 페이지 진입 후 몇 ms 뒤에 띄울지 */
  var LAYER_DELAY_MS = 60000;


  /* =======================================================
     유틸
     ======================================================= */
  function toNumber(v) {
    var n = parseInt(String(v).replace(/[^0-9]/g, ''), 10);
    return isNaN(n) ? 0 : n;
  }
  function withComma(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  /* 전환 이벤트 훅.
     GA4 / GTM 등을 붙이면 여기서 한 번에 발화됩니다. */
  function track(event, params) {
    try {
      if (window.dataLayer && window.dataLayer.push) {
        window.dataLayer.push(Object.assign({ event: event }, params || {}));
      }
      if (typeof window.gtag === 'function') {
        window.gtag('event', event, params || {});
      }
    } catch (e) { /* 추적 실패가 화면을 막지 않도록 */ }
    if (!window.dataLayer && typeof window.gtag !== 'function') {
      console.debug('[track]', event, params || {});
    }
  }


  /* =======================================================
     1. 헤더 / 모바일 드로어
     ======================================================= */
  var header = $('#siteHeader');
  function onScrollHeader() {
    if (header) header.classList.toggle('is-scrolled', window.pageYOffset > 8);
  }

  var menuBtn   = $('#menuBtn');
  var sideMenu  = $('#sideMenu');
  var drawerDim = $('#drawerDim');
  var sideClose = $('#sideClose');

  function openDrawer() {
    if (!sideMenu) return;
    sideMenu.hidden = false;
    drawerDim.hidden = false;
    menuBtn.setAttribute('aria-expanded', 'true');
    menuBtn.setAttribute('aria-label', '메뉴 닫기');
    document.body.style.overflow = 'hidden';
    var first = $('a, button', sideMenu);
    if (first) first.focus();
  }
  function closeDrawer() {
    if (!sideMenu || sideMenu.hidden) return;
    sideMenu.hidden = true;
    drawerDim.hidden = true;
    menuBtn.setAttribute('aria-expanded', 'false');
    menuBtn.setAttribute('aria-label', '메뉴 열기');
    document.body.style.overflow = '';
  }

  if (menuBtn) menuBtn.addEventListener('click', function () {
    if (sideMenu.hidden) openDrawer(); else closeDrawer();
  });
  if (sideClose) sideClose.addEventListener('click', closeDrawer);
  if (drawerDim) drawerDim.addEventListener('click', closeDrawer);
  if (sideMenu) $$('a', sideMenu).forEach(function (a) { a.addEventListener('click', closeDrawer); });


  /* =======================================================
     2. 맨 위로
     ======================================================= */
  var quickMenu = $('#quickMenu');
  var backToTop = $('#backToTop');

  function onScrollQuick() {
    if (quickMenu) quickMenu.classList.toggle('visible', window.pageYOffset > 500);
  }
  if (backToTop) backToTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });


  /* =======================================================
     3. CTA 클릭 추적 + 폼으로 포커스 이동
     ======================================================= */
  $$('[data-cta]').forEach(function (el) {
    el.addEventListener('click', function () {
      track('cta_click', { cta_location: el.getAttribute('data-cta') });

      // #apply 로 가는 CTA는 이동 후 첫 입력칸에 포커스를 줘서 이탈을 줄인다
      if (el.getAttribute('href') === '#apply') {
        window.setTimeout(function () {
          var input = $('#leadBottom input[name="name"]');
          if (input) input.focus({ preventScroll: true });
        }, 620);
      }
    });
  });


  /* =======================================================
     4. 입력 포맷 (금액 / 휴대폰)
     ======================================================= */
  $$('input[data-money]').forEach(function (input) {
    input.addEventListener('input', function () {
      var n = toNumber(input.value);
      if (n > CALC.maxInput) n = CALC.maxInput;
      input.value = n ? withComma(n) : '';
    });
    input.addEventListener('blur', function () {
      if (!input.value) input.value = '0';
    });
  });

  $$('input[data-phone]').forEach(function (input) {
    input.addEventListener('input', function () {
      var d = input.value.replace(/[^0-9]/g, '').slice(0, 11);
      var out = d;
      if (d.length > 7)      out = d.slice(0, 3) + '-' + d.slice(3, 7) + '-' + d.slice(7);
      else if (d.length > 3) out = d.slice(0, 3) + '-' + d.slice(3);
      input.value = out;
    });
  });


  /* =======================================================
     5. 예상 소득 계산기
     ======================================================= */
  var calcForm = $('#calcForm');
  var lastCalc = '';

  function setResult(id, manwon) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = withComma(manwon);
    el.classList.remove('count-flash');
    void el.offsetWidth;
    el.classList.add('count-flash');
  }

  function calculate() {
    var health = toNumber($('#healthVal').value);
    var driver = toNumber($('#driverVal').value);
    var monthly = health + driver;

    var insurance = health * CALC.healthRate + driver * CALC.driverRate;
    var b1 = monthly === 0 ? 0 : CALC.bonusFirst;
    var b2 = monthly >= CALC.thresholdDouble ? CALC.bonusDouble : 0;
    var b3 = monthly >= CALC.thresholdTriple ? CALC.bonusTriple : 0;

    var total = insurance + b1 + b2 + b3;
    var toMan = function (won) { return Math.round(won / 10000); };

    setResult('rsTotal',  toMan(total));
    setResult('rsIns',    toMan(insurance));
    setResult('rsBonus1', toMan(b1));
    setResult('rsBonus2', toMan(b2));
    setResult('rsBonus3', toMan(b3));

    // 계산 결과를 폼에 실어 보내면 상담원이 맥락을 알고 전화할 수 있다
    lastCalc = '건강 ' + withComma(health) + '원 / 자동차 ' + withComma(driver) +
               '원 → 예상 총소득 ' + toMan(total) + '만원';
    $$('input[name="calc"]').forEach(function (i) { i.value = lastCalc; });

    track('calc_submit', { monthly_premium: monthly, expected_total: toMan(total) });
  }

  if (calcForm) calcForm.addEventListener('submit', function (e) {
    e.preventDefault();
    calculate();
  });


  /* =======================================================
     6. 스크롤 효과 (마우스 반응 없음)
     ======================================================= */
  var reduceMotion = window.matchMedia &&
                     window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* 6-1. 숫자 카운트업 */
  function countUp(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var comma = el.hasAttribute('data-comma');
    var fmt = function (n) { return comma ? withComma(n) : String(n); };

    if (reduceMotion || target === 0) {
      el.textContent = fmt(target);
      return;
    }

    var duration = 1400, start = null;
    el.classList.add('is-counting');

    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 4);          // easeOutQuart
      el.textContent = fmt(Math.round(target * eased));
      if (p < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.classList.remove('is-counting');
      }
    }
    window.requestAnimationFrame(step);
  }

  /* 6-2. 순차 등장 대상 */
  var revealTargets = $$(
    '.section-head, .trustbar-head, .stat, .why-card, .step, ' +
    '.faq-item, .calc-form, .calc-result, .cta-inline'
  );
  revealTargets.forEach(function (el) { el.classList.add('reveal'); });

  /* 같은 부모 안에서 몇 번째인지로 지연을 준다 (stagger) */
  function staggerDelay(el) {
    var sibs = Array.prototype.filter.call(
      el.parentNode.children,
      function (n) { return n.classList && n.classList.contains('reveal'); }
    );
    var i = sibs.indexOf(el);
    return i > 0 ? Math.min(i, 6) * 0.08 : 0;
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        el.style.setProperty('--d', staggerDelay(el) + 's');
        el.classList.add('is-in');

        $$('.count', el).forEach(function (c) {
          if (c.dataset.done) return;
          c.dataset.done = '1';
          countUp(c);
        });

        io.unobserve(el);
      });
    }, { threshold: 0.2, rootMargin: '0px 0px -60px 0px' });

    revealTargets.forEach(function (el) { io.observe(el); });

    /* 6-3. 히어로는 로드 직후 재생 */
    var hero = $('#hero');
    var heroTitle = $('.hero-title');
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        if (hero) hero.classList.add('is-in');
        if (heroTitle) heroTitle.classList.add('is-in');
      });
    });
  } else {
    revealTargets.forEach(function (el) { el.classList.add('is-in'); });
    $$('.count').forEach(countUp);
    if ($('#hero')) $('#hero').classList.add('is-in');
    if ($('.hero-title')) $('.hero-title').classList.add('is-in');
  }

  /* 6-4. 스크롤 진행바 */
  var progressBar = $('#scrollProgressBar');
  function onScrollProgress() {
    if (!progressBar) return;
    var max = document.documentElement.scrollHeight - window.innerHeight;
    var pct = max > 0 ? (window.pageYOffset / max) * 100 : 0;
    progressBar.style.width = Math.min(pct, 100) + '%';
  }


  /* =======================================================
     7. 리드 폼 (히어로 + 하단 공용)
     ======================================================= */
  function initLeadForm(form) {
    var card = form.closest('.lead-card');
    var done = $('.lead-done', card);
    var submitBtn = $('.lf-submit', form);

    /* 동의 내용 토글 */
    $$('.agree-toggle', form).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var detail = btn.nextElementSibling;
        var open = detail.hidden;
        detail.hidden = !open;
        btn.setAttribute('aria-expanded', String(open));
        btn.textContent = open ? '내용 닫기' : '내용 보기';
      });
    });

    function showErr(key, msg) {
      var el = $('[data-err="' + key + '"]', form);
      if (el) el.textContent = msg || '';
      var input = form.querySelector('[name="' + key + '"]');
      var wrap = input && input.closest('.lf-field');
      if (wrap) wrap.classList.toggle('is-error', !!msg);
    }

    function clearErrs() {
      $$('.lf-err', form).forEach(function (e) { e.textContent = ''; });
      $$('.lf-field.is-error', form).forEach(function (f) { f.classList.remove('is-error'); });
    }

    /* 필수: 이름 / 휴대폰 / 필수동의 — 그 외는 모두 선택.
       필드를 늘릴수록 전환은 떨어지므로, 지역·직업 등은 상담 통화에서 받는 것을 권장합니다. */
    function validate() {
      clearErrs();
      var ok = true, firstBad = null;

      var name = form.querySelector('[name="name"]');
      if (!name.value.trim()) {
        showErr('name', '이름을 입력해주세요.');
        ok = false; firstBad = firstBad || name;
      }

      var phone = form.querySelector('[name="phone"]');
      var digits = phone.value.replace(/[^0-9]/g, '');
      if (!digits) {
        showErr('phone', '휴대폰 번호를 입력해주세요.');
        ok = false; firstBad = firstBad || phone;
      } else if (!/^01[016789]\d{7,8}$/.test(digits)) {
        showErr('phone', '올바른 휴대폰 번호를 입력해주세요.');
        ok = false; firstBad = firstBad || phone;
      }

      var agree = form.querySelector('[name="agree1"]');
      if (!agree.checked) {
        showErr('agree', '필수 항목에 동의해주세요.');
        ok = false; firstBad = firstBad || agree;
      }

      if (firstBad) firstBad.focus();
      if (!ok) track('form_error', { source: form.querySelector('[name="source"]').value });
      return ok;
    }

    function getPayload() {
      var pick = function (n) {
        var el = form.querySelector('[name="' + n + '"]:checked') || form.querySelector('[name="' + n + '"]');
        return el ? el.value : '';
      };
      return {
        name:   form.querySelector('[name="name"]').value.trim(),
        phone:  form.querySelector('[name="phone"]').value.trim(),
        age:    (form.querySelector('[name="age"]:checked')  || {}).value || '',
        time:   (form.querySelector('[name="time"]:checked') || {}).value || '',
        source: pick('source'),
        calc:   form.querySelector('[name="calc"]').value,
        agreeRequired: true,
        agreeMarketing: form.querySelector('[name="agree2"]').checked,
        referrer: document.referrer || '',
        landingUrl: location.href
      };
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!validate()) return;

      var payload = getPayload();
      var origText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.textContent = '전송 중...';

      var succeed = function () {
        form.hidden = true;
        done.hidden = false;
        // 히어로 폼은 헤드도 감춰 완료 메시지만 남긴다
        var head = $('.lead-head', card);
        if (head) head.hidden = true;
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        track('lead_submit', { source: payload.source, age: payload.age, time: payload.time });
      };

      var fail = function (err) {
        console.error('[apply] 전송 실패', err);
        submitBtn.disabled = false;
        submitBtn.textContent = origText;
        showErr('agree', '전송에 실패했습니다. 잠시 후 다시 시도해주세요.');
      };

      if (!APPLY_ENDPOINT) {
        console.info('[apply] APPLY_ENDPOINT 미설정 - 전송하지 않음', payload);
        window.setTimeout(succeed, 400);
        return;
      }

      fetch(APPLY_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        succeed();
      }).catch(fail);
    });
  }

  $$('[data-lead-form]').forEach(initLeadForm);


  /* =======================================================
     8. 스크롤 팝업 (오늘 하루 보지 않기)
     ======================================================= */
  var layer = $('#scrollLayer');
  var layerToday = $('#layerToday');
  var LAYER_KEY = 'wonder_layer_hide_until';
  var layerShown = false;

  function layerSuppressed() {
    try {
      var until = window.localStorage.getItem(LAYER_KEY);
      return until && Date.now() < parseInt(until, 10);
    } catch (e) { return false; }
  }

  function closeLayer() {
    if (!layer || layer.hidden) return;
    if (layerToday && layerToday.checked) {
      try {
        window.localStorage.setItem(LAYER_KEY, String(Date.now() + 24 * 60 * 60 * 1000));
      } catch (e) { /* 저장 실패는 무시 */ }
    }
    layer.hidden = true;
    document.body.style.overflow = '';
  }

  function openLayer() {
    if (!layer || layerShown || layerSuppressed()) return;
    // 이미 신청을 마친 사용자에게는 띄우지 않는다
    if ($('.lead-done:not([hidden])')) return;
    layerShown = true;
    layer.hidden = false;
    document.body.style.overflow = 'hidden';
    var x = $('.layer-x', layer);
    if (x) x.focus();
    track('layer_open');
  }

  if (layer) {
    $$('[data-layer-close]', layer).forEach(function (el) {
      el.addEventListener('click', closeLayer);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeDrawer();
    closeLayer();
  });

  /* 스크롤 위치가 아니라 체류 시간 기준으로 노출 */
  window.setTimeout(openLayer, LAYER_DELAY_MS);


  /* =======================================================
     9. 스크롤 리스너 (rAF 스로틀)
     ======================================================= */
  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () {
      onScrollHeader();
      onScrollQuick();
      onScrollProgress();
      ticking = false;
    });
  }, { passive: true });

  onScrollHeader();
  onScrollQuick();
  onScrollProgress();

})();
