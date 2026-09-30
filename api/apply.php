<?php
define('WONDER_APP', true);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/bigquery.php';
require_once __DIR__ . '/tele_transmit.php';
require_once __DIR__ . '/security.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// FR-01: 허니팟 — 실제 이용자에게는 보이지 않는 필드. 값이 있으면 봇으로 간주하고
// 정상 응답처럼 보이게 반환한 뒤 실제 처리는 하지 않습니다.
if (trim((string)($data['website'] ?? '')) !== '') {
    wonder_audit_log('honeypot_blocked', ['ip' => $ip]);
    echo json_encode(['ok' => true]);
    exit;
}

// FR-01: IP당 60초 20건 제한
if (!wonder_rate_limit_check($ip, 20, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'rate_limited']);
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

$utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
    $utm[$k] = mb_substr((string)($data[$k] ?? ''), 0, 100);
}

// FR-02: 동일 번호 24시간 내 재제출 시 신규 리드로 적재하지 않음
try {
    $settings = wonder_bq_settings();
    $table = sprintf('`%s.%s.%s`', $settings['project_id'], $settings['dataset'], $settings['table']);
    $dupCheck = wonder_bq_query(
        "SELECT lead_id FROM $table
         WHERE phone = @phone AND created_at >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL 24 HOUR)
         LIMIT 1",
        ['phone' => $phoneDigits]
    );
    if (!empty($dupCheck['rows'])) {
        wonder_audit_log('duplicate_within_24h', ['lead_id' => $dupCheck['rows'][0]['lead_id']]);
        echo json_encode(['ok' => true, 'duplicate' => true]);
        exit;
    }
} catch (Throwable $e) {
    // 중복 확인 실패는 신규 접수를 막을 이유가 아니므로 계속 진행합니다.
}

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
unset($row['submitted_at']); // BigQuery leads 테이블에는 없는 필드 (tele API 페이로드 전용)

try {
    wonder_bq_insert_row($row);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

// NFR-10: 감사 로그 — 원문(이름·연락처) 대신 리드 식별자·시각·결과코드만 기록
wonder_audit_log('lead_received', [
    'lead_id' => $lead['lead_id'],
    'phone_masked' => wonder_mask_phone($phoneDigits),
    'name_masked' => wonder_mask_name($name),
    'api_result_code' => $teleResult['result_code'],
]);

echo json_encode(['ok' => true]);
