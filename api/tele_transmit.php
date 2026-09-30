<?php
if (!defined('WONDER_APP')) {
    http_response_code(403);
    exit;
}

/**
 * 이름/연령대 등은 이 사이트 UI에서 쓰는 한글 값 → 원더 API 규격서의 코드값으로 변환합니다.
 */
function wonder_tele_age_code(string $age): ?string {
    $map = ['20대' => '20S', '30대' => '30S', '40대' => '40S', '50대' => '50S', '50대 이상' => '50S', '60대' => '60S'];
    return $map[$age] ?? null;
}

function wonder_tele_time_code(string $time): ?string {
    $map = ['오전' => 'AM', '점심' => 'LUNCH', '오후' => 'PM', '저녁' => 'EVENING'];
    return $map[$time] ?? null; // '무관' 등은 코드가 없어 미전송
}

function wonder_tele_job_code(string $job): ?string {
    $map = [
        '직장인' => 'OFFICE', '주부' => 'HOMEMAKER', '자영업' => 'SELF_EMPLOYED',
        '프리랜서' => 'FREELANCER', '무직·구직' => 'JOBSEEKER', '기타' => 'ETC',
    ];
    return $map[$job] ?? null;
}

function wonder_uuid4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

/**
 * 원더 리드 전송 연동 API 규격서 IF-01 페이로드 구성.
 * region/preferred_time/age_band/job 등은 값이 없으면 키 자체를 생략합니다(규격서 "빈 값" 규칙).
 */
function wonder_tele_build_payload(array $lead, array $teleConfig): array {
    $payload = [
        'lead_id' => $lead['lead_id'],
        'submitted_at' => $lead['submitted_at'],
        'phone' => $lead['phone'],
        'name' => $lead['name'],
        'consent_privacy' => true,
        'consent_marketing' => (bool)$lead['agree_marketing'],
        'landing_page' => $lead['landing_page'] ?: 'job',
    ];

    if (!empty($lead['region'])) {
        $payload['region'] = $lead['region'];
    }
    if (!empty($lead['contact_time'])) {
        $code = wonder_tele_time_code($lead['contact_time']);
        if ($code) $payload['preferred_time'] = $code;
    }
    if (!empty($lead['age'])) {
        $code = wonder_tele_age_code($lead['age']);
        if ($code) $payload['age_band'] = $code;
    }
    if (!empty($lead['job'])) {
        $code = wonder_tele_job_code($lead['job']);
        if ($code) $payload['job'] = $code;
    }
    if (!empty($teleConfig['consent_version'])) {
        $payload['consent_version'] = $teleConfig['consent_version'];
    }
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
        if (!empty($lead[$k])) $payload[$k] = $lead[$k];
    }
    if (!empty($teleConfig['hash_pepper'])) {
        $payload['match_hash'] = hash_hmac('sha256', $lead['phone'], $teleConfig['hash_pepper']);
    }

    return $payload;
}

/**
 * tele APP으로 리드를 전송합니다. 엔드포인트 미설정 시 아무 것도 하지 않고 반환합니다
 * (원더 쪽 규격 확정 전까지는 이 기능이 비활성 상태로 안전하게 대기합니다).
 *
 * @return array{attempted: bool, result_code: ?string, receipt_no: ?string, message: ?string}
 */
function wonder_tele_send(array $lead): array {
    $config = wonder_config();
    $teleConfig = [
        'endpoint' => $config['tele_endpoint'] ?? '',
        'auth_token' => $config['tele_auth_token'] ?? '',
        'hash_pepper' => $config['tele_hash_pepper'] ?? '',
        'consent_version' => $config['consent_version'] ?? '',
    ];

    if (empty($teleConfig['endpoint'])) {
        return ['attempted' => false, 'result_code' => null, 'receipt_no' => null, 'message' => null];
    }

    $payload = wonder_tele_build_payload($lead, $teleConfig);
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $headers = [
        'Content-Type: application/json; charset=UTF-8',
        'Idempotency-Key: ' . $lead['lead_id'],
    ];
    if (!empty($teleConfig['auth_token'])) {
        $headers[] = 'Authorization: Bearer ' . $teleConfig['auth_token'];
    }

    $resultCode = null;
    $resultMessage = null;
    $receiptNo = null;
    $maxAttempts = 3;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($teleConfig['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $respBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // 재시도 대상 (규격서 5장): 타임아웃/네트워크 오류, 429, 5xx
        if ($curlErr || $httpCode === 429 || $httpCode >= 500) {
            if ($attempt < $maxAttempts) {
                sleep($attempt);
                continue;
            }
            $resultCode = 'E500';
            $resultMessage = $curlErr ?: ('HTTP ' . $httpCode);
            break;
        }

        $decoded = json_decode((string)$respBody, true);
        if (is_array($decoded)) {
            $resultCode = $decoded['result_code'] ?? ('HTTP' . $httpCode);
            $resultMessage = $decoded['result_message'] ?? null;
            $receiptNo = $decoded['receipt_no'] ?? null;

            // FR-04 ACK 검증(당사 제안): ① HTTP 성공 ② 결과코드=정상/중복 ③ 응답 리드식별자=요청값 ④ 접수번호 존재
            $ackVerified = $httpCode < 300
                && in_array($resultCode, ['0000', '0001'], true)
                && ($decoded['lead_id'] ?? null) === $lead['lead_id']
                && !empty($receiptNo);

            if (!$ackVerified && in_array($resultCode, ['0000', '0001'], true)) {
                // 결과코드는 정상인데 다른 조건(리드식별자 불일치 등)이 안 맞으면 검증 실패로 취급
                $resultCode = 'E_ACK_MISMATCH';
                $resultMessage = 'ACK 검증 실패: 응답 리드 식별자 또는 접수번호 불일치';
            }
        } else {
            $resultCode = 'HTTP' . $httpCode;
            $resultMessage = mb_substr((string)$respBody, 0, 255);
        }
        break;
    }

    return [
        'attempted' => true,
        'result_code' => $resultCode,
        'receipt_no' => $receiptNo,
        'message' => $resultMessage ? mb_substr((string)$resultMessage, 0, 255) : null,
    ];
}
