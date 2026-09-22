<?php
define('WONDER_APP', true);

require_once __DIR__ . '/../api/db.php';

function admin_config(): array {
    return wonder_config();
}

// Firebase Hosting가 web.app 도메인에서 Cloud Run으로 요청을 넘길 때 Cookie 헤더를
// 걷어내서 세션 기반 로그인이 동작하지 않았습니다. 매 요청마다 자격증명을 다시 보내는
// HTTP Basic 인증으로 바꿔서 쿠키에 의존하지 않도록 합니다.
function admin_require_login(): void {
    $config = admin_config();
    $user = $_SERVER['PHP_AUTH_USER'] ?? '';
    $pass = $_SERVER['PHP_AUTH_PW'] ?? '';

    $ok = $user !== '' && hash_equals($config['admin_user'], $user)
        && password_verify($pass, $config['admin_password_hash']);

    if (!$ok) {
        header('WWW-Authenticate: Basic realm="Wonder Admin"');
        http_response_code(401);
        echo '로그인이 필요합니다.';
        exit;
    }
}
