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

    // Cloud Run 등 배포 환경에서는 환경변수로 접속 정보를 주입합니다.
    // (api/config.php는 로컬 개발 전용 파일로 .gitignore에 포함되어 배포 이미지에는 없습니다)
    $envHost = getenv('DB_HOST');
    if ($envHost) {
        $config = [
            'host' => $envHost,
            'port' => (int)(getenv('DB_PORT') ?: 3306),
            'dbname' => getenv('DB_NAME') ?: 'wonder-microweb',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'admin_user' => getenv('ADMIN_USER') ?: '',
            'admin_password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
        ];
    } else {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function wonder_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = wonder_config();

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['dbname'],
        $config['charset']
    );

    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
