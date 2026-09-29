<?php
define('WONDER_APP', true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$name = trim((string)($data['name'] ?? ''));
$phoneDigits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));

if ($name === '' || mb_strlen($name) > 50) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

if (!preg_match('/^01[016789]\d{7,8}$/', $phoneDigits)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_phone']);
    exit;
}

if (empty($data['agreeRequired'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'agreement_required']);
    exit;
}

$allowedAge = ['20대', '30대', '40대', '50대', '50대 이상', '60대', ''];
$allowedTime = ['오전', '점심', '오후', '저녁', '무관', ''];
$allowedJob = ['직장인', '주부', '자영업', '프리랜서', '무직·구직', '기타', ''];

$age = in_array($data['age'] ?? '', $allowedAge, true) ? $data['age'] : '';
$time = in_array($data['time'] ?? '', $allowedTime, true) ? $data['time'] : '';
$job = in_array($data['job'] ?? '', $allowedJob, true) ? $data['job'] : '';
$region = mb_substr(trim((string)($data['region'] ?? '')), 0, 100);
$source = mb_substr((string)($data['source'] ?? ''), 0, 20);
$calcSummary = mb_substr((string)($data['calc'] ?? ''), 0, 255);
$agreeMarketing = !empty($data['agreeMarketing']) ? 1 : 0;
$referrer = mb_substr((string)($data['referrer'] ?? ''), 0, 500);
$landingUrl = mb_substr((string)($data['landingUrl'] ?? ''), 0, 500);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
    $utm[$k] = mb_substr((string)($data[$k] ?? ''), 0, 100);
}

require __DIR__ . '/db.php';
require __DIR__ . '/bigquery.php';
require __DIR__ . '/tele_transmit.php';

$now = new DateTime('now', new DateTimeZone('Asia/Seoul'));

$lead = [
    'lead_id' => wonder_uuid4(),
    'submitted_at' => $now->format('Y-m-d\TH:i:sP'),
    'name' => $name,
    'phone' => $phoneDigits,
    'age' => $age,
    'contact_time' => $time,
    'region' => $region,
    'job' => $job,
    'source' => $source,
    'landing_page' => 'job',
    'calc_summary' => $calcSummary,
    'agree_marketing' => (bool)$agreeMarketing,
    'referrer' => $referrer,
    'landing_url' => $landingUrl,
    'ip_address' => $ip,
] + $utm;

try {
    $teleResult = wonder_tele_send($lead);
} catch (Throwable $e) {
    $teleResult = ['attempted' => true, 'result_code' => 'E500', 'receipt_no' => null, 'message' => $e->getMessage()];
}

$row = $lead + [
    'match_hash' => null,
    'api_result_code' => $teleResult['result_code'],
    'api_receipt_no' => $teleResult['receipt_no'],
    'api_message' => $teleResult['message'],
    'api_sent_at' => $teleResult['attempted'] ? gmdate('Y-m-d\TH:i:s\Z') : null,
    'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
];

try {
    wonder_bq_insert_row($row);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error', 'debug' => $e->getMessage()]);
    exit;
}

echo json_encode(['ok' => true]);
