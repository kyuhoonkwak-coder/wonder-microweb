<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require_once __DIR__ . '/../api/bigquery.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$settings = wonder_bq_settings();
$table = sprintf('`%s.%s.%s`', $settings['project_id'], $settings['dataset'], $settings['table']);

$where = 'WHERE (@q = \'\' OR name LIKE CONCAT(\'%\', @q, \'%\') OR phone LIKE CONCAT(\'%\', @q, \'%\'))';
$params = [':q' => $q];

try {
    $countResult = wonder_bq_query("SELECT COUNT(*) AS c FROM $table $where", ['q' => $q]);
    $total = (int)($countResult['rows'][0]['c'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $listResult = wonder_bq_query(
        "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
        ['q' => $q]
    );

    echo json_encode([
        'total' => $total,
        'page' => $page,
        'totalPages' => $totalPages,
        'leads' => $listResult['rows'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'bq_error', 'message' => $e->getMessage()]);
}
