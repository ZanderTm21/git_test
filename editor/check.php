<?php
// One-off server check for the editor. Shows only technical facts (no passwords or keys). Delete it when done.
declare(strict_types=1);

session_name('rosinetti_check');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
$sessionStarted = @session_start();
$step = (int) ($_GET['step'] ?? 1);
if ($step === 1) {
    $_SESSION['probe'] = 'ok';
}
$sessionKept = ($_SESSION['probe'] ?? '') === 'ok';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');

if ($step === 1) {
    // Reload once so we can see whether the session survived the round trip.
    header('Location: check.php?step=2');
    echo "Checking…";
    exit;
}

$dataDir = __DIR__ . '/data';
$siteDir = dirname(__DIR__);
$lines = [];
$ok = fn ($cond) => $cond ? 'OK ' : 'NO ';

$lines[] = $ok(version_compare(PHP_VERSION, '8.1', '>=')) . 'PHP version: ' . PHP_VERSION . ' (needs 8.1 or newer)';
foreach (['curl', 'mbstring', 'json', 'openssl', 'gd'] as $ext) {
    $lines[] = $ok(extension_loaded($ext)) . "PHP extension: {$ext}";
}
$savePath = session_save_path() ?: sys_get_temp_dir();
$lines[] = $ok($sessionStarted) . 'Session starts';
$lines[] = $ok(is_writable($savePath)) . 'Session folder writable';
$lines[] = $ok(isset($_COOKIE['rosinetti_check'])) . 'Browser sent the session cookie back';
$lines[] = $ok($sessionKept) . 'Session kept between page loads';
$lines[] = $ok(is_dir($dataDir) && is_writable($dataDir)) . 'editor/data folder writable';
$lines[] = $ok(is_writable($siteDir . '/index.html')) . 'Site index.html writable (needed to publish)';
$lines[] = $ok(is_file(__DIR__ . '/vendor/autoload.php')) . 'Claude library present (editor/vendor)';
$lines[] = $ok(is_file($dataDir . '/setup.php')) . 'Setup code file present (editor/data/setup.php)';
$lines[] = '   Users file exists: ' . (is_file($dataDir . '/users.php') ? 'yes' : 'no');
$lines[] = '   HTTPS seen by PHP: ' . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'yes' : 'no')
    . ' | forwarded proto: ' . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '-');
$lines[] = '   Script path: ' . ($_SERVER['SCRIPT_NAME'] ?? '-');
$lines[] = '   Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? '-');

$reach = 'not tested (curl missing)';
if (function_exists('curl_init')) {
    $c = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($c, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    curl_exec($c);
    $code = (int) curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    curl_close($c);
    $reach = $code > 0 ? "reachable (HTTP {$code})" : "NOT reachable ({$err})";
    $lines[] = $ok($code > 0) . 'Claude API ' . $reach;
}

echo "Rosinetti editor – server check\n\n" . implode("\n", $lines) . "\n\nCopy everything above and send it back. Then delete check.php.\n";
