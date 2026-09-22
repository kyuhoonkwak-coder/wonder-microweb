<?php
define('WONDER_APP', true);
require __DIR__ . '/../api/db.php';

header('Content-Type: application/json; charset=utf-8');

$cookieId = $_COOKIE['PHPSESSID'] ?? null;

$pdo = wonder_db();
$row = null;
if ($cookieId) {
    $stmt = $pdo->prepare('SELECT id, data, last_access FROM sessions WHERE id = :id');
    $stmt->execute([':id' => $cookieId]);
    $row = $stmt->fetch();
}

echo json_encode([
    'cookie_header_raw' => $_SERVER['HTTP_COOKIE'] ?? null,
    'cookie_phpsessid' => $cookieId,
    'db_row_found' => $row !== false && $row !== null,
    'db_row' => $row ?: null,
    'php_sapi' => php_sapi_name(),
    'hostname' => gethostname(),
], JSON_PRETTY_PRINT);
