<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>상담 신청 관리 | 원더</title>
<style>
  * { box-sizing:border-box; }
  body { font-family: -apple-system, "Pretendard", sans-serif; background:#F5F5ED; margin:0; color:#1a1a1a; }

  /* 로그인 화면 */
  .login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .login-box { background:#fff; padding:32px; border-radius:12px; width:280px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
  .login-box h1 { font-size:18px; margin:0 0 20px; }
  .login-box label { display:block; font-size:13px; color:#555; margin:12px 0 4px; }
  .login-box input { width:100%; box-sizing:border-box; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
  .login-box button { width:100%; margin-top:20px; padding:11px; background:#1a1a1a; color:#fff; border:none; border-radius:6px; font-size:14px; cursor:pointer; }
  .login-error { color:#c0392b; font-size:13px; margin-top:12px; display:none; }

  /* 목록 화면 */
  .wrap { max-width:1100px; margin:0 auto; padding:32px 20px 60px; }
  .head { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
  h1 { font-size:20px; margin:0; }
  .head-actions { display:flex; gap:8px; align-items:center; }
  .count { font-size:13px; color:#666; }
  a.btn, button.btn { display:inline-block; padding:8px 14px; border-radius:6px; font-size:13px; text-decoration:none; border:1px solid #ccc; background:#fff; color:#1a1a1a; cursor:pointer; }
  form.search { margin-bottom:16px; display:flex; gap:8px; }
  form.search input { flex:1; max-width:280px; padding:9px 12px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
  table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,.05); }
  th, td { padding:10px 12px; text-align:left; font-size:13px; border-bottom:1px solid #eee; white-space:nowrap; }
  th { background:#f0f0e8; font-weight:600; color:#444; }
  tr:last-child td { border-bottom:none; }
  td.calc { white-space:normal; max-width:260px; font-size:12px; color:#555; }
  .empty { padding:40px; text-align:center; color:#888; }
  .pager { display:flex; gap:6px; margin-top:20px; justify-content:center; }
  .pager a, .pager span { padding:6px 11px; border:1px solid #ddd; border-radius:6px; font-size:13px; text-decoration:none; color:#333; cursor:pointer; }
  .pager .current { background:#1a1a1a; color:#fff; border-color:#1a1a1a; }
  .tag { display:inline-block; padding:2px 8px; border-radius:20px; background:#eee; font-size:11px; }
  .tag.yes { background:#e6f4ea; color:#1e7e34; }
  [hidden] { display:none !important; }
</style>
</head>
<body>

<div class="login-wrap" id="loginWrap">
  <form class="login-box" id="loginForm" autocomplete="off">
    <h1>원더 상담신청 관리자</h1>
    <label for="user">아이디</label>
    <input type="text" id="user" autocomplete="username" required>
    <label for="password">비밀번호</label>
    <input type="password" id="password" autocomplete="current-password" required>
    <button type="submit">로그인</button>
    <p class="login-error" id="loginError">아이디 또는 비밀번호가 올바르지 않습니다.</p>
  </form>
</div>

<div class="wrap" id="panelWrap" hidden>
  <div class="head">
    <h1>상담 신청 목록</h1>
    <div class="head-actions">
      <span class="count" id="totalCount"></span>
      <a class="btn" id="exportBtn" href="#">CSV 내보내기</a>
      <button class="btn" id="logoutBtn" type="button">로그아웃</button>
    </div>
  </div>

  <form class="search" id="searchForm">
    <input type="text" id="searchInput" placeholder="이름 또는 전화번호 검색">
    <button class="btn" type="submit">검색</button>
    <a class="btn" id="resetBtn" href="#" hidden>초기화</a>
  </form>

  <div id="listArea"></div>
  <div class="pager" id="pager"></div>
</div>

<script>
(function () {
  'use strict';

  var STORAGE_KEY = 'wonder_admin_auth';
  var state = { q: '', page: 1 };

  var loginWrap = document.getElementById('loginWrap');
  var panelWrap = document.getElementById('panelWrap');
  var loginForm = document.getElementById('loginForm');
  var loginError = document.getElementById('loginError');
  var listArea = document.getElementById('listArea');
  var pager = document.getElementById('pager');
  var totalCount = document.getElementById('totalCount');
  var searchForm = document.getElementById('searchForm');
  var searchInput = document.getElementById('searchInput');
  var resetBtn = document.getElementById('resetBtn');
  var exportBtn = document.getElementById('exportBtn');
  var logoutBtn = document.getElementById('logoutBtn');

  function getAuthHeader() {
    var v = localStorage.getItem(STORAGE_KEY);
    return v ? 'Basic ' + v : null;
  }

  function showLogin() {
    loginWrap.hidden = false;
    panelWrap.hidden = true;
  }

  function showPanel() {
    loginWrap.hidden = true;
    panelWrap.hidden = false;
  }

  function fmtPhone(digits) {
    digits = String(digits || '').replace(/\D/g, '');
    if (digits.length === 11) return digits.slice(0,3) + '-' + digits.slice(3,7) + '-' + digits.slice(7);
    if (digits.length === 10) return digits.slice(0,3) + '-' + digits.slice(3,6) + '-' + digits.slice(6);
    return digits;
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function render(data) {
    totalCount.textContent = '총 ' + data.total.toLocaleString() + '건';

    if (!data.leads.length) {
      listArea.innerHTML = '<div class="empty">신청 내역이 없습니다.</div>';
      pager.innerHTML = '';
      return;
    }

    var rows = data.leads.map(function (l) {
      var isMarketingYes = l.agree_marketing === 'true' || l.agree_marketing === true;
      var apiStatus = l.api_result_code
        ? (l.api_result_code === '0000' || l.api_result_code === '0001' ? '전송완료' : '전송실패(' + esc(l.api_result_code) + ')')
        : '-';
      return '<tr>' +
        '<td>' + esc(l.name) + '</td>' +
        '<td><a href="tel:' + esc(l.phone) + '">' + esc(fmtPhone(l.phone)) + '</a></td>' +
        '<td>' + esc(l.age || '-') + '</td>' +
        '<td>' + esc(l.contact_time || '-') + '</td>' +
        '<td>' + esc(l.region || '-') + '</td>' +
        '<td>' + esc(l.job || '-') + '</td>' +
        '<td>' + esc(l.source || '-') + '</td>' +
        '<td><span class="tag ' + (isMarketingYes ? 'yes' : '') + '">' + (isMarketingYes ? '동의' : '미동의') + '</span></td>' +
        '<td>' + apiStatus + '</td>' +
        '<td class="calc">' + esc(l.calc_summary || '-') + '</td>' +
        '<td>' + esc(l.created_at) + '</td>' +
        '</tr>';
    }).join('');

    listArea.innerHTML =
      '<div style="overflow-x:auto"><table><thead><tr>' +
      '<th>이름</th><th>연락처</th><th>연령대</th><th>연락가능시간</th><th>지역</th><th>직업</th>' +
      '<th>유입경로</th><th>마케팅동의</th><th>API전송</th><th>계산기 결과</th><th>신청일시</th>' +
      '</tr></thead><tbody>' + rows + '</tbody></table></div>';

    var pagerHtml = '';
    for (var p = 1; p <= data.totalPages; p++) {
      if (p === data.page) {
        pagerHtml += '<span class="current">' + p + '</span>';
      } else {
        pagerHtml += '<a data-page="' + p + '">' + p + '</a>';
      }
    }
    pager.innerHTML = data.totalPages > 1 ? pagerHtml : '';

    Array.prototype.forEach.call(pager.querySelectorAll('a[data-page]'), function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        state.page = parseInt(a.getAttribute('data-page'), 10);
        load();
      });
    });
  }

  function load() {
    var auth = getAuthHeader();
    if (!auth) { showLogin(); return; }

    var url = 'data.php?page=' + state.page + (state.q ? '&q=' + encodeURIComponent(state.q) : '');
    fetch(url, { headers: { Authorization: auth } })
      .then(function (res) {
        if (res.status === 401) { logout(); throw new Error('unauthorized'); }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        showPanel();
        render(data);
        exportBtn.href = 'export.php' + (state.q ? '?q=' + encodeURIComponent(state.q) : '');
      })
      .catch(function (err) {
        if (err.message !== 'unauthorized') console.error('[admin] 목록 로드 실패', err);
      });
  }

  function logout() {
    localStorage.removeItem(STORAGE_KEY);
    showLogin();
  }

  loginForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var user = document.getElementById('user').value;
    var pass = document.getElementById('password').value;
    var token = btoa(unescape(encodeURIComponent(user + ':' + pass)));

    loginError.style.display = 'none';

    fetch('data.php', { headers: { Authorization: 'Basic ' + token } })
      .then(function (res) {
        if (res.status === 401) { loginError.style.display = 'block'; return null; }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        if (!data) return;
        localStorage.setItem(STORAGE_KEY, token);
        state.q = '';
        state.page = 1;
        showPanel();
        render(data);
      })
      .catch(function (err) {
        console.error('[admin] 로그인 실패', err);
        loginError.style.display = 'block';
      });
  });

  searchForm.addEventListener('submit', function (e) {
    e.preventDefault();
    state.q = searchInput.value.trim();
    state.page = 1;
    resetBtn.hidden = state.q === '';
    load();
  });

  resetBtn.addEventListener('click', function (e) {
    e.preventDefault();
    searchInput.value = '';
    state.q = '';
    state.page = 1;
    resetBtn.hidden = true;
    load();
  });

  exportBtn.addEventListener('click', function (e) {
    var auth = getAuthHeader();
    if (!auth) return;
    e.preventDefault();
    var url = 'export.php' + (state.q ? '?q=' + encodeURIComponent(state.q) : '');
    fetch(url, { headers: { Authorization: auth } })
      .then(function (res) { return res.blob(); })
      .then(function (blob) {
        var a = document.createElement('a');
        var objUrl = URL.createObjectURL(blob);
        a.href = objUrl;
        a.download = 'wonder-leads.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(objUrl);
      });
  });

  logoutBtn.addEventListener('click', logout);

  load();
})();
</script>
</body>
</html>
