/* =========================================================
   index_test4.html 전용 보조 스크립트
   main.js는 손대지 않는다(다른 페이지들이 공유). 이 페이지에서만
   쓰는 STRUCTURE 섹션의 카테고리 리스트 전환만 처리한다.
   ========================================================= */
(function () {
  'use strict';

  var $  = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var items   = $$('.cat-item');
  var panels  = $$('.cat-panel');
  var ringEl  = $('#catRingFill');
  var RING_CIRCUMFERENCE = 289; // 2 * PI * r(46), .cat-ring-fill의 stroke-dasharray와 맞춤
  if (!items.length || !panels.length) return;

  function activate(idx) {
    items.forEach(function (el, i) { el.classList.toggle('is-active', i === idx); });
    panels.forEach(function (el, i) { el.classList.toggle('is-active', i === idx); });
    if (ringEl) {
      var progress = (idx + 1) / items.length;
      ringEl.style.strokeDashoffset = String(RING_CIRCUMFERENCE * (1 - progress));
    }
  }

  items.forEach(function (el, i) {
    el.addEventListener('click', function () { activate(i); });
  });

  /* 링을 켜자마자 애니메이션으로 채워지는 느낌을 주기 위해
     0에서 시작해 한 틱 뒤에 실제 진행률로 전환한다 */
  window.requestAnimationFrame(function () {
    window.requestAnimationFrame(function () { activate(0); });
  });

  /* =======================================================
     계산기 비주얼 패널의 보험소득/축하금 비율 막대 — main.js가
     #rsIns, #rsBonus1~3 텍스트를 갱신할 때마다(계산 버튼 클릭)
     MutationObserver로 감지해서 다시 계산한다. main.js의 계산
     로직 자체는 건드리지 않고 그 결과(DOM 텍스트)만 읽는다.
     ======================================================= */
  var calcResult = $('.calc-result');
  var barIns   = $('#calcBarIns');
  var barBonus = $('#calcBarBonus');
  var legendIns   = $('#calcLegendIns');
  var legendBonus = $('#calcLegendBonus');

  function num(el) { return el ? (parseFloat(el.textContent.replace(/,/g, '')) || 0) : 0; }

  function updateCalcBar() {
    if (!barIns || !barBonus) return;
    var ins   = num($('#rsIns'));
    var bonus = num($('#rsBonus1')) + num($('#rsBonus2')) + num($('#rsBonus3'));
    var total = ins + bonus;
    var insPct = total > 0 ? (ins / total) * 100 : 50;
    barIns.style.width = insPct + '%';
    barBonus.style.width = (100 - insPct) + '%';
    if (legendIns) legendIns.textContent = String(ins);
    if (legendBonus) legendBonus.textContent = String(bonus);
  }

  if (calcResult && window.MutationObserver) {
    new MutationObserver(updateCalcBar).observe(calcResult, { childList: true, characterData: true, subtree: true });
  }
  window.requestAnimationFrame(function () {
    window.requestAnimationFrame(updateCalcBar);
  });
})();
