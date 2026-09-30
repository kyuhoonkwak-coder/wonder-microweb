<?php
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

/**
 * 개발 요건서 NFR-05: 전화 = 앞 3자리+****+끝 2자리, 이름 = 첫 글자+*
 */
function wonder_mask_phone(string $digits): string {
    $len = strlen($digits);
    if ($len < 7) return str_repeat('*', $len);
    return substr($digits, 0, 3) . '****' . substr($digits, -2);
}

function wonder_mask_name(string $name): string {
    if ($name === '') return '';
    $first = mb_substr($name, 0, 1);
    return $first . str_repeat('*', max(1, mb_strlen($name) - 1));
}

/**
 * 원문(PII)을 남기지 않는 감사 로그. NFR-10: 리드 식별자·시각·결과코드만 기록.
 */
function wonder_audit_log(string $event, array $fields = []): void {
    error_log('[wonder_audit] ' . $event . ' ' . json_encode($fields, JSON_UNESCAPED_UNICODE));
}

/**
 * Firestore 기반 IP 요청 빈도 제한 (FR-01: IP당 60초 20건).
 * Firestore 접근이 실패하면(미설정 등) 안전하게 통과시킵니다 — 제한 기능 장애가
 * 서비스 전체를 막지 않도록 fail-open으로 동작합니다.
 */
function wonder_rate_limit_check(string $ip, int $maxRequests = 20, int $windowSeconds = 60): bool {
    if ($ip === '') return true;

    try {
        require_once __DIR__ . '/bigquery.php'; // wonder_bq_access_token 재사용
        $token = wonder_bq_access_token();
        $config = wonder_config();
        $projectId = $config['bq_project_id'] ?? 'wonder-microweb';
        $docId = preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip);
        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/rate_limits/%s',
            rawurlencode($projectId),
            rawurlencode($docId)
        );

        $now = time();
        $count = 0;
        $windowStart = $now;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $doc = json_decode((string)$resp, true);
            $existingCount = (int)($doc['fields']['count']['integerValue'] ?? 0);
            $existingStart = (int)($doc['fields']['window_start']['integerValue'] ?? 0);
            if ($now - $existingStart < $windowSeconds) {
                $count = $existingCount + 1;
                $windowStart = $existingStart;
            } else {
                $count = 1;
                $windowStart = $now;
            }
        } else {
            $count = 1;
            $windowStart = $now;
        }

        $body = json_encode([
            'fields' => [
                'count' => ['integerValue' => (string)$count],
                'window_start' => ['integerValue' => (string)$windowStart],
            ],
        ]);

        $ch = curl_init($url . '?updateMask.fieldPaths=count&updateMask.fieldPaths=window_start');
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);

        return $count <= $maxRequests;
    } catch (Throwable $e) {
        return true; // fail-open
    }
}
