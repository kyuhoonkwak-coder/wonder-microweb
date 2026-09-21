/* =========================================================
   index_test3.html 전용 보조 스크립트
   main.js는 손대지 않는다(다른 페이지들이 공유). 이 페이지에서만
   쓰는 씬(scene) 도트 내비게이션의 활성 상태만 추가로 처리한다.
   ========================================================= */
(function () {
  'use strict';

  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  var scenes = $$('.scene');
  var dots = $$('.scene-nav a');
  if (!scenes.length || !dots.length) return;

  function updateActiveScene() {
    var viewportCenter = window.innerHeight / 2;
    var closestIdx = 0;
    var closestDist = Infinity;

    scenes.forEach(function (sec, idx) {
      var rect = sec.getBoundingClientRect();
      var center = rect.top + Math.min(rect.height, window.innerHeight) / 2;
      var dist = Math.abs(center - viewportCenter);
      if (dist < closestDist) { closestDist = dist; closestIdx = idx; }
    });

    dots.forEach(function (d, i) { d.classList.toggle('is-active', i === closestIdx); });
  }

  var ticking = false;
  window.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () {
      updateActiveScene();
      ticking = false;
    });
  }, { passive: true });

  updateActiveScene();
})();
