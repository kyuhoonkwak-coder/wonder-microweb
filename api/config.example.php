<?php
// DB 접속 정보. 운영 배포 시에는 환경변수로 옮기세요.
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

return [
    'host' => '34.47.77.125',
    'port' => 3306,
    'dbname' => 'wonder-microweb',
    'user' => 'root',
    'password' => 'your_password_here',
    'charset' => 'utf8mb4',

    'admin_user' => 'admin',
    // php -r 'echo password_hash("your_password", PASSWORD_DEFAULT);' 로 생성
    'admin_password_hash' => '',
];
