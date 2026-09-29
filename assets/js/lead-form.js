/* =========================================================
   원더(wonder) 상세 상담신청 폼 (lead-full.html 전용)
   main.js의 리드 폼 로직만 떼어낸 경량 버전. 의존성 없음.
   ========================================================= */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var APPLY_ENDPOINT = 'api/apply.php';

  function track(event, params) {
    try {
      if (window.dataLayer && window.dataLayer.push) {
        window.dataLayer.push(Object.assign({ event: event }, params || {}));
      }
      if (typeof window.gtag === 'function') {
        window.gtag('event', event, params || {});
      }
    } catch (e) { /* 추적 실패가 화면을 막지 않도록 */ }
  }

  function initLeadForm(form) {
    var card = form.closest('.lead-card');
    var done = $('.lead-done', card);
    var submitBtn = $('.lf-submit', form);

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
      var qs = new URLSearchParams(location.search);
      return {
        name:   form.querySelector('[name="name"]').value.trim(),
        phone:  form.querySelector('[name="phone"]').value.trim(),
        age:    (form.querySelector('[name="age"]:checked')  || {}).value || '',
        time:   (form.querySelector('[name="time"]:checked') || {}).value || '',
        region: pick('region').trim(),
        job:    pick('job'),
        source: pick('source'),
        calc:   '',
        agreeRequired: true,
        agreeMarketing: form.querySelector('[name="agree2"]').checked,
        referrer: document.referrer || '',
        landingUrl: location.href,
        utm_source: qs.get('utm_source') || '',
        utm_medium: qs.get('utm_medium') || '',
        utm_campaign: qs.get('utm_campaign') || '',
        utm_content: qs.get('utm_content') || '',
        utm_term: qs.get('utm_term') || ''
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
})();
