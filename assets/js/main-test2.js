/* =========================================================
   index_test2.html 전용 보조 스크립트
   main.js는 절대 수정하지 않는다(index.html / index_test.html이
   공유하므로). 이 페이지에서 새로 생긴 구조(히어로 인라인 통계,
   story 내러티브 스크롤, 계산기 게이지)만 추가로 처리한다.
   ========================================================= */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  function withComma(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  /* =======================================================
     1. 히어로 인라인 통계 카운트업
     main.js의 reveal 대상 목록(.trustbar-head, .stat …)에
     .hero-stats가 없어서 main.js는 이 숫자를 건드리지 않는다.
     히어로는 항상 최상단이라 스크롤 트리거 없이 로드 시 바로 실행.
     ======================================================= */
  function countUp(el) {
    var target = parseFloat(el.getAttribute('data-count')) || 0;
    var comma = el.hasAttribute('data-comma');
    var reduceMotion = window.matchMedia &&
                       window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || target === 0) {
      el.textContent = comma ? withComma(target) : String(target);
      return;
    }

    var duration = 1300, start = null;
    function step(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - p, 4);
      var val = Math.round(target * eased);
      el.textContent = comma ? withComma(val) : String(val);
      if (p < 1) window.requestAnimationFrame(step);
    }
    window.requestAnimationFrame(step);
  }

  $$('.hero-stats .count').forEach(function (el) {
    window.requestAnimationFrame(function () { countUp(el); });
  });

  /* =======================================================
     2. STORY 내러티브 — 화면 중앙에 가장 가까운 항목을 active로
     (IntersectionObserver의 threshold 판정은 항목 크기가 뷰포트보다
     작을 때 여러 항목이 동시에 조건을 만족해버리는 문제가 있어서,
     스크롤 위치 기반 "가장 가까운 항목 찾기" 방식을 쓴다 — 이 프로젝트의
     캐러셀 구현에서 이미 검증된 방식)
     ======================================================= */
  var storyItems = $$('.story-item');
  var railFrames  = $$('.rail-frame');
  var railDots    = $$('.rail-dots li');

  function updateStoryActive() {
    if (!storyItems.length) return;
    var viewportCenter = window.innerHeight / 2;
    var closestIdx = 0;
    var closestDist = Infinity;

    storyItems.forEach(function (item, idx) {
      var rect = item.getBoundingClientRect();
      var itemCenter = rect.top + rect.height / 2;
      var dist = Math.abs(itemCenter - viewportCenter);
      if (dist < closestDist) {
        closestDist = dist;
        closestIdx = idx;
      }
      // 화면에 들어온 항목은 각각 개별적으로 페이드인
      if (rect.top < window.innerHeight * 0.85 && !item.classList.contains('is-in')) {
        item.classList.add('is-in');
      }
    });

    railFrames.forEach(function (f, i) { f.classList.toggle('is-active', i === closestIdx); });
    railDots.forEach(function (d, i) { d.classList.toggle('is-active', i === closestIdx); });
  }

  var storyTicking = false;
  window.addEventListener('scroll', function () {
    if (storyTicking) return;
    storyTicking = true;
    window.requestAnimationFrame(function () {
      updateStoryActive();
      storyTicking = false;
    });
  }, { passive: true });

  updateStoryActive();

  /* =======================================================
     3. 계산기 게이지 바
     main.js가 #rsTotal의 textContent를 갱신할 때마다(계산 버튼 클릭
     또는 초기값) MutationObserver로 감지해서 게이지 폭을 갱신한다.
     main.js를 건드리지 않고 그 결과물(DOM 텍스트)만 관찰하는 방식이라
     계산 로직과 완전히 분리되어 있다.
     ======================================================= */
  var rsTotal = $('#rsTotal');
  var gaugeFill = $('#gaugeFill');
  var GAUGE_MAX = 500; // 만원 — 게이지가 가득 차는 기준선(연출용 상한)

  function updateGauge() {
    if (!rsTotal || !gaugeFill) return;
    var val = parseFloat(rsTotal.textContent.replace(/,/g, '')) || 0;
    var pct = Math.max(4, Math.min(100, (val / GAUGE_MAX) * 100));
    gaugeFill.style.width = pct + '%';
  }

  if (rsTotal && gaugeFill && window.MutationObserver) {
    new MutationObserver(updateGauge).observe(rsTotal, { childList: true, characterData: true, subtree: true });
  }
  updateGauge();

})();
