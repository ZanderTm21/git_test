<?php
// Serves a site image for the preview: the draft's uploaded version if there is one, otherwise the live file.
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

if (!current_user()) {
    http_response_code(403);
    exit;
}
$f = (string) ($_GET['f'] ?? '');
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
if (!preg_match('/^[A-Za-z0-9._-]+$/', $f) || !isset($types[$ext])) {
    http_response_code(404);
    exit;
}
foreach ([DRAFT_DIR . '/images/' . $f, SITE_DIR . '/images/' . $f] as $path) {
    if (is_file($path)) {
        header('Content-Type: ' . $types[$ext]);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
http_response_code(404);
