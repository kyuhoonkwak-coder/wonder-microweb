<?php
require __DIR__ . '/_auth.php';
admin_require_login();

header('Content-Type: application/json; charset=utf-8');

$keys = ['INSTANCE_CONNECTION_NAME', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'ADMIN_USER'];
$out = [];
foreach ($keys as $k) {
    $v = getenv($k);
    $out[$k] = $v === false ? null : $v;
}
$out['server_INSTANCE_CONNECTION_NAME'] = $_SERVER['INSTANCE_CONNECTION_NAME'] ?? null;
$out['cloudsql_dir_exists'] = is_dir('/cloudsql');
$out['cloudsql_dir_contents'] = is_dir('/cloudsql') ? scandir('/cloudsql') : null;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
