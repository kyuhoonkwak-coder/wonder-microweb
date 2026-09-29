<?php
require __DIR__ . '/_auth.php';
admin_require_login();
require_once __DIR__ . '/../api/bigquery.php';

header('Content-Type: application/json; charset=utf-8');

$out = ['settings' => wonder_bq_settings()];

$chEmail = curl_init('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/email');
curl_setopt_array($chEmail, [
    CURLOPT_HTTPHEADER => ['Metadata-Flavor: Google'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 2,
]);
$out['runtime_service_account'] = curl_exec($chEmail);
curl_close($chEmail);

try {
    $token = wonder_bq_access_token();
    $out['token_ok'] = true;
    $out['token_prefix'] = substr($token, 0, 12) . '...';
} catch (Throwable $e) {
    $out['token_ok'] = false;
    $out['token_error'] = $e->getMessage();
}

if (!empty($out['token_ok'])) {
    try {
        $result = wonder_bq_query('SELECT 1 AS ok');
        $out['query_ok'] = true;
        $out['query_result'] = $result;
    } catch (Throwable $e) {
        $out['query_ok'] = false;
        $out['query_error'] = $e->getMessage();
    }

    try {
        wonder_bq_insert_row([
            'lead_id' => 'diag-' . bin2hex(random_bytes(6)),
            'name' => '진단테스트',
            'phone' => '01000000000',
            'agree_marketing' => false,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
        $out['insert_ok'] = true;
    } catch (Throwable $e) {
        $out['insert_ok'] = false;
        $out['insert_error'] = $e->getMessage();
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
