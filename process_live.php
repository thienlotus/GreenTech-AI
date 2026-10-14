<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ai_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Truy cập không hợp lệ.']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$frameData = trim((string)($payload['frame_data'] ?? ''));
if ($frameData === '') {
    echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu khung hình camera.']);
    exit;
}

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($payload['session_id'] ?? ''));
if ($sessionId === '') {
    try {
        $sessionId = 'live_' . bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $sessionId = 'live_' . (string)time();
    }
}

$timestampMs = isset($payload['timestamp_ms']) ? (int)$payload['timestamp_ms'] : (int)round(microtime(true) * 1000);

$requestPayload = [
    'frame_data' => $frameData,
    'session_id' => $sessionId,
    'timestamp_ms' => $timestampMs,
];

$encodedPayload = json_encode($requestPayload, JSON_UNESCAPED_UNICODE);
if ($encodedPayload === false) {
    echo json_encode(['success' => false, 'error' => 'Không thể mã hóa dữ liệu gửi tới AI.']);
    exit;
}

$apiUrl = get_ai_live_track_endpoint();
$maxAttempts = get_ai_live_retry_attempts();
$timeout = get_ai_live_timeout_seconds();
$connectTimeout = get_ai_live_connect_timeout_seconds();
$relaxSslVerify = should_relax_ai_ssl_verify();

$response = false;
$httpCode = 0;
$curlError = '';

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Expect:'
    ]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'GreenTech-LiveMonitor/1.0');

    if (stripos($apiUrl, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$relaxSslVerify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $relaxSslVerify ? 0 : 2);
    }

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = (string)curl_error($ch);
    curl_close($ch);

    if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
        break;
    }

    $retryableHttp = in_array($httpCode, [408, 425, 429, 500, 502, 503, 504, 522, 524], true);
    $retryableNetwork = $response === false;
    if (($retryableHttp || $retryableNetwork) && $attempt < $maxAttempts) {
        $delayMs = min(1200, 300 * $attempt);
        usleep($delayMs * 1000);
    }
}

if ($response === false || $httpCode < 200 || $httpCode >= 300) {
    $detail = trim(($httpCode > 0 ? ('HTTP ' . $httpCode . ' ') : '') . $curlError);
    $errorMessage = 'Không thể kết nối tới AI Live Tracking.';
    if ($detail !== '') {
        $errorMessage .= ' ' . $detail;
    }

    echo json_encode([
        'success' => false,
        'error' => $errorMessage,
        'session_id' => $sessionId,
    ]);
    exit;
}

$decodedResponse = json_decode((string)$response, true);
if (!is_array($decodedResponse)) {
    echo json_encode([
        'success' => false,
        'error' => 'AI trả về dữ liệu không hợp lệ.',
        'session_id' => $sessionId,
    ]);
    exit;
}

if (!isset($decodedResponse['session_id'])) {
    $decodedResponse['session_id'] = $sessionId;
}

echo json_encode($decodedResponse, JSON_UNESCAPED_UNICODE);
