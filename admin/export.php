<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require_once __DIR__ . '/../api/bigquery.php';

$q = trim((string)($_GET['q'] ?? ''));

$settings = wonder_bq_settings();
$table = sprintf('`%s.%s.%s`', $settings['project_id'], $settings['dataset'], $settings['table']);
$where = 'WHERE (@q = \'\' OR name LIKE CONCAT(\'%\', @q, \'%\') OR phone LIKE CONCAT(\'%\', @q, \'%\'))';

try {
    $result = wonder_bq_query(
        "SELECT * EXCEPT (created_at),
                FORMAT_TIMESTAMP('%Y-%m-%d %H:%M:%S', created_at, 'Asia/Seoul') AS created_at
         FROM $table $where ORDER BY created_at DESC",
        ['q' => $q]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo 'BigQuery 조회 실패: ' . $e->getMessage();
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="wonder-leads-' . date('Ymd-His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', '이름', '연락처', '연령대', '연락가능시간', '지역', '직업', '유입경로', '마케팅동의', '계산기결과', '유입페이지', 'API결과', '접수번호', '신청일시']);

foreach ($result['rows'] as $row) {
    fputcsv($out, [
        $row['lead_id'],
        $row['name'],
        $row['phone'],
        $row['age'],
        $row['contact_time'],
        $row['region'],
        $row['job'],
        $row['source'],
        $row['agree_marketing'] === 'true' ? '동의' : '미동의',
        $row['calc_summary'],
        $row['landing_page'],
        $row['api_result_code'],
        $row['api_receipt_no'],
        $row['created_at'],
    ]);
}
fclose($out);
