<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require_once __DIR__ . '/../api/db.php';

$q = trim((string)($_GET['q'] ?? ''));
$pdo = wonder_db();

$where = '';
$params = [];
if ($q !== '') {
    $where = 'WHERE name LIKE :q OR phone LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$stmt = $pdo->prepare("SELECT * FROM leads $where ORDER BY created_at DESC");
$stmt->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="wonder-leads-' . date('Ymd-His') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', '이름', '연락처', '연령대', '연락가능시간', '유입경로', '마케팅동의', '계산기결과', '유입페이지', '신청일시']);

while ($row = $stmt->fetch()) {
    fputcsv($out, [
        $row['id'],
        $row['name'],
        $row['phone'],
        $row['age'],
        $row['contact_time'],
        $row['source'],
        $row['agree_marketing'] ? '동의' : '미동의',
        $row['calc_summary'],
        $row['landing_url'],
        $row['created_at'],
    ]);
}
fclose($out);
