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

$allowedAge = ['20대', '30대', '40대', '50대 이상', ''];
$allowedTime = ['오전', '오후', '저녁', '무관', ''];

$age = in_array($data['age'] ?? '', $allowedAge, true) ? $data['age'] : '';
$time = in_array($data['time'] ?? '', $allowedTime, true) ? $data['time'] : '';
$source = mb_substr((string)($data['source'] ?? ''), 0, 20);
$calcSummary = mb_substr((string)($data['calc'] ?? ''), 0, 255);
$agreeMarketing = !empty($data['agreeMarketing']) ? 1 : 0;
$referrer = mb_substr((string)($data['referrer'] ?? ''), 0, 500);
$landingUrl = mb_substr((string)($data['landingUrl'] ?? ''), 0, 500);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

require __DIR__ . '/db.php';

try {
    $pdo = wonder_db();
    $stmt = $pdo->prepare(
        'INSERT INTO leads
            (name, phone, age, contact_time, source, calc_summary, agree_marketing, referrer, landing_url, ip_address)
         VALUES
            (:name, :phone, :age, :contact_time, :source, :calc_summary, :agree_marketing, :referrer, :landing_url, :ip_address)'
    );
    $stmt->execute([
        ':name' => $name,
        ':phone' => $phoneDigits,
        ':age' => $age,
        ':contact_time' => $time,
        ':source' => $source,
        ':calc_summary' => $calcSummary,
        ':agree_marketing' => $agreeMarketing,
        ':referrer' => $referrer,
        ':landing_url' => $landingUrl,
        ':ip_address' => $ip,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
    exit;
}

echo json_encode(['ok' => true]);
