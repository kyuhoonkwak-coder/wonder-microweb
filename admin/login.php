<?php
require __DIR__ . '/_auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = admin_config();
    $user = trim((string)($_POST['user'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if (hash_equals($config['admin_user'], $user) && password_verify($pass, $config['admin_password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_user'] = $user;
        header('Location: index.php');
        exit;
    }

    $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
}

if (!empty($_SESSION['admin_user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>관리자 로그인 | 원더</title>
<style>
  body { font-family: -apple-system, "Pretendard", sans-serif; background:#F5F5ED; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
  form { background:#fff; padding:32px; border-radius:12px; width:280px; box-shadow:0 4px 20px rgba(0,0,0,.08); }
  h1 { font-size:18px; margin:0 0 20px; }
  label { display:block; font-size:13px; color:#555; margin:12px 0 4px; }
  input { width:100%; box-sizing:border-box; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; }
  button { width:100%; margin-top:20px; padding:11px; background:#1a1a1a; color:#fff; border:none; border-radius:6px; font-size:14px; cursor:pointer; }
  .error { color:#c0392b; font-size:13px; margin-top:12px; }
</style>
</head>
<body>
  <form method="post" autocomplete="off">
    <h1>원더 상담신청 관리자</h1>
    <label for="user">아이디</label>
    <input type="text" id="user" name="user" autocomplete="username" required>
    <label for="password">비밀번호</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <button type="submit">로그인</button>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
  </form>
</body>
</html>
