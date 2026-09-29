<?php
// 로컬 개발용 설정 템플릿. 운영(Cloud Run)에서는 환경변수로 대체됩니다 (db.php의 wonder_config 참고).
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

return [
    'admin_user' => 'admin',
    // php -r 'echo password_hash("your_password", PASSWORD_DEFAULT);' 로 생성
    'admin_password_hash' => '',

    // BigQuery
    'bq_project_id' => 'wonder-microweb',
    'bq_dataset' => 'wonder_microweb',
    'bq_table' => 'leads',
    'bq_sa_key_path' => '',

    // tele APP 리드 전송 연동
    'tele_endpoint' => '',
    'tele_auth_token' => '',
    'tele_hash_pepper' => '',
    'consent_version' => 'WONDER-MICROWEB-v1',
];
