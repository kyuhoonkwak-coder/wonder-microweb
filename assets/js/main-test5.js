/* =========================================================
   index_test5.html 전용 보조 스크립트
   main.js는 손대지 않는다(다른 페이지들이 공유). 히어로 통계(.stat)와
   카드 그리드(.why-card)는 main.js의 reveal 대상 셀렉터에 이미 포함되어
   있어 별도 처리가 필요 없다. 이 페이지에서만 쓰는 계산기 게이지 바만
   여기서 다룬다.
   ========================================================= */
(function () {
  'use strict';

  var $ = function (s, c) { return (c || document).querySelector(s); };

  /* =======================================================
     계산기 게이지 바 — main.js가 #rsTotal의 textContent를 갱신할
     때마다(계산 버튼 클릭 또는 초기값) MutationObserver로 감지해서
     게이지 폭을 갱신한다. main.js의 계산 로직은 건드리지 않는다.
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
