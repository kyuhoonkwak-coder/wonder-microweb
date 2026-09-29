<?php
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

function wonder_bq_settings(): array {
    $config = wonder_config();
    return [
        'project_id' => $config['bq_project_id'] ?? 'wonder-microweb',
        'dataset' => $config['bq_dataset'] ?? 'wonder_microweb',
        'table' => $config['bq_table'] ?? 'leads',
        'sa_key_path' => $config['bq_sa_key_path'] ?? '',
    ];
}

function wonder_bq_b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function wonder_bq_access_token(): string {
    static $token = null;
    static $expiresAt = 0;
    if ($token !== null && time() < $expiresAt - 30) {
        return $token;
    }

    // 1) Cloud Run/GCE 메타데이터 서버 (운영 환경 — 별도 설정 불필요)
    $ch = curl_init('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Metadata-Flavor: Google'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
    ]);
    $resp = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode((string)$resp, true);
        if (!empty($data['access_token'])) {
            $token = $data['access_token'];
            $expiresAt = time() + (int)($data['expires_in'] ?? 3600);
            return $token;
        }
    }

    // 2) 서비스 계정 키 파일 (로컬 개발용)
    $settings = wonder_bq_settings();
    if ($settings['sa_key_path'] && is_readable($settings['sa_key_path'])) {
        $key = json_decode((string)file_get_contents($settings['sa_key_path']), true);
        $now = time();
        $header = wonder_bq_b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = wonder_bq_b64url(json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signInput = $header . '.' . $claim;
        openssl_sign($signInput, $signature, $key['private_key'], 'sha256WithRSAEncryption');
        $jwt = $signInput . '.' . wonder_bq_b64url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$resp, true);
        if (!empty($data['access_token'])) {
            $token = $data['access_token'];
            $expiresAt = time() + (int)($data['expires_in'] ?? 3600);
            return $token;
        }
    }

    throw new RuntimeException('BigQuery 인증 토큰을 가져올 수 없습니다');
}

function wonder_bq_insert_row(array $row): void {
    $settings = wonder_bq_settings();
    $token = wonder_bq_access_token();
    $url = sprintf(
        'https://bigquery.googleapis.com/bigquery/v2/projects/%s/datasets/%s/tables/%s/insertAll',
        rawurlencode($settings['project_id']),
        rawurlencode($settings['dataset']),
        rawurlencode($settings['table'])
    );

    $payload = [
        'rows' => [[
            'insertId' => $row['lead_id'] ?? uniqid('lead_', true),
            'json' => $row,
        ]],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException('BigQuery 삽입 실패(네트워크): ' . $err);
    }
    $decoded = json_decode((string)$resp, true);
    if ($httpCode >= 300 || !empty($decoded['insertErrors'])) {
        throw new RuntimeException('BigQuery 삽입 실패: ' . $resp);
    }
}

/**
 * @return array{rows: array<int, array<string, mixed>>, totalRows: int}
 */
function wonder_bq_query(string $sql, array $params = []): array {
    $settings = wonder_bq_settings();
    $token = wonder_bq_access_token();
    $url = sprintf('https://bigquery.googleapis.com/bigquery/v2/projects/%s/queries', rawurlencode($settings['project_id']));

    $queryParams = [];
    foreach ($params as $name => $value) {
        $type = is_int($value) ? 'INT64' : (is_bool($value) ? 'BOOL' : 'STRING');
        $queryParams[] = [
            'name' => $name,
            'parameterType' => ['type' => $type],
            'parameterValue' => ['value' => is_bool($value) ? ($value ? 'true' : 'false') : (string)$value],
        ];
    }

    $payload = [
        'query' => $sql,
        'useLegacySql' => false,
        'parameterMode' => 'NAMED',
        'queryParameters' => $queryParams,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=UTF-8',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        throw new RuntimeException('BigQuery 조회 실패(네트워크): ' . $err);
    }
    $decoded = json_decode((string)$resp, true);
    if ($httpCode >= 300) {
        throw new RuntimeException('BigQuery 조회 실패: ' . $resp);
    }

    $fields = array_map(function ($f) { return $f['name']; }, $decoded['schema']['fields'] ?? []);
    $rows = [];
    foreach ($decoded['rows'] ?? [] as $r) {
        $row = [];
        foreach ($r['f'] as $i => $cell) {
            $row[$fields[$i]] = $cell['v'];
        }
        $rows[] = $row;
    }

    return [
        'rows' => $rows,
        'totalRows' => (int)($decoded['totalRows'] ?? count($rows)),
    ];
}
