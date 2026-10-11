<?php
// Shows the draft site to signed-in users, with CSS and JS inlined and photos served from the draft when uploaded there.
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

if (!current_user()) {
    http_response_code(403);
    exit('Sign in to the editor to see the preview.');
}
draft_init();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');

$files = read_files(DRAFT_DIR);
$html = $files['index.html'];

// Inline the draft's stylesheets and script so the preview shows unpublished CSS/JS changes.
$html = preg_replace_callback(
    '#<link\s+rel="stylesheet"\s+href="(css/[a-z0-9._-]+\.css)"\s*/?>#i',
    fn ($m) => isset($files[$m[1]]) ? "<style>\n" . $files[$m[1]] . "\n</style>" : $m[0],
    $html
);
$html = preg_replace_callback(
    '#<script\s+src="(js/[a-z0-9._-]+\.js)"\s*>\s*</script>#i',
    fn ($m) => isset($files[$m[1]]) ? "<script>\n" . $files[$m[1]] . "\n</script>" : $m[0],
    $html
);
// Photos and logos: draft upload if there is one, otherwise the live file.
$html = preg_replace('#(["\'(])images/([A-Za-z0-9._-]+)#', '$1asset.php?f=$2', $html);

echo $html;
