<?php
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

function wonder_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    // Cloud Run 등 배포 환경에서는 환경변수로 설정을 주입합니다.
    // (api/config.php는 로컬 개발 전용 파일로 .gitignore에 포함되어 배포 이미지에는 없습니다)
    $envAdminUser = getenv('ADMIN_USER');

    if ($envAdminUser) {
        $config = [
            'admin_user' => $envAdminUser,
            'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',

            'bq_project_id' => getenv('BQ_PROJECT_ID') ?: 'wonder-microweb',
            'bq_dataset' => getenv('BQ_DATASET') ?: 'wonder_microweb',
            'bq_table' => getenv('BQ_TABLE') ?: 'leads',
            'bq_sa_key_path' => getenv('BQ_SA_KEY_PATH') ?: '',

            'tele_endpoint' => getenv('TELE_ENDPOINT') ?: '',
            'tele_auth_token' => getenv('TELE_AUTH_TOKEN') ?: '',
            'tele_hash_pepper' => getenv('TELE_HASH_PEPPER') ?: '',
            'consent_version' => getenv('CONSENT_VERSION') ?: 'WONDER-MICROWEB-v1',
        ];
    } else {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}
