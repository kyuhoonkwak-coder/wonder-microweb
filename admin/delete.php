<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require_once __DIR__ . '/../api/bigquery.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$leadId = trim((string)($data['lead_id'] ?? ''));

if ($leadId === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'lead_id_required']);
    exit;
}

$settings = wonder_bq_settings();
$table = sprintf('`%s.%s.%s`', $settings['project_id'], $settings['dataset'], $settings['table']);

try {
    wonder_bq_query("DELETE FROM $table WHERE lead_id = @id", ['id' => $leadId]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    if (strpos($e->getMessage(), 'streaming buffer') !== false) {
        echo json_encode([
            'ok' => false,
            'error' => 'streaming_buffer',
            'message' => '방금 들어온 신청 건입니다. BigQuery 특성상 접수 후 약 90분이 지나야 삭제할 수 있습니다. 잠시 후 다시 시도해주세요.',
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'bq_error', 'message' => '삭제 중 오류가 발생했습니다.']);
    }
}
