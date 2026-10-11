<?php
// Shared helpers for the site editor: storage, sessions, auth, CSRF and the draft copy of the site.
declare(strict_types=1);

const SITE_FILES = ['index.html', 'css/style.css', 'css/theme-gold.css', 'js/main.js'];
const IMAGE_SLOTS = [
    'hero.jpg' => 'Hero (top of page)',
    'project-1-before.jpg' => 'Before/after 1 – before',
    'project-1-after.jpg' => 'Before/after 1 – after',
    'project-2-before.jpg' => 'Before/after 2 – before',
    'project-2-after.jpg' => 'Before/after 2 – after',
    'project-3-before.jpg' => 'Before/after 3 – before',
    'project-3-after.jpg' => 'Before/after 3 – after',
    'gallery-1.jpg' => 'Gallery 1 (large)',
    'gallery-2.jpg' => 'Gallery 2',
    'gallery-3.jpg' => 'Gallery 3',
    'gallery-4.jpg' => 'Gallery 4',
    'gallery-5.jpg' => 'Gallery 5',
    'gallery-6.jpg' => 'Gallery 6',
    'cta-bg.jpg' => 'Final call-to-action background',
];
const DEFAULT_MODEL = 'claude-opus-5-5';
const KEEP_HISTORY = 30;
const KEEP_VERSIONS = 20;

define('SITE_DIR', dirname(__DIR__));
define('DATA_DIR', __DIR__ . '/data');
define('DRAFT_DIR', DATA_DIR . '/draft');

// ---------- storage ----------

function ensure_data_dir(): void
{
    foreach ([DATA_DIR, DATA_DIR . '/history', DATA_DIR . '/versions'] as $d) {
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
    }
    $ht = DATA_DIR . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    }
    if (!is_file(DATA_DIR . '/index.html')) {
        file_put_contents(DATA_DIR . '/index.html', '');
    }
}

/** Settings and users are stored as PHP files so that, even if .htaccess is ignored, the web server runs them instead of showing them. */
function load_php(string $name, array $default = []): array
{
    $f = DATA_DIR . '/' . $name;
    if (!is_file($f)) {
        return $default;
    }
    $v = include $f;
    return is_array($v) ? $v : $default;
}

function save_php(string $name, array $value): void
{
    ensure_data_dir();
    write_atomic(DATA_DIR . '/' . $name, "<?php\nreturn " . var_export($value, true) . ";\n");
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(DATA_DIR . '/' . $name, true);
    }
}

function load_json(string $name, array $default = []): array
{
    $f = DATA_DIR . '/' . $name;
    if (!is_file($f)) {
        return $default;
    }
    $v = json_decode((string) file_get_contents($f), true);
    return is_array($v) ? $v : $default;
}

function save_json(string $name, array $value): void
{
    ensure_data_dir();
    write_atomic(DATA_DIR . '/' . $name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function write_atomic(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $tmp = $path . '.tmp' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $content) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Could not write ' . basename($path));
    }
}

/** Runs $fn while holding an exclusive lock, so two people can't change the draft at the same moment. */
function with_lock(callable $fn)
{
    ensure_data_dir();
    $h = fopen(DATA_DIR . '/.lock', 'c');
    flock($h, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $dir . '/' . $f;
        is_dir($p) ? rrmdir($p) : unlink($p);
    }
    rmdir($dir);
}

function new_id(): string
{
    return date('Ymd-His') . '-' . bin2hex(random_bytes(3));
}

function prune_dirs(string $parent, int $keep): void
{
    $ids = list_ids($parent);
    foreach (array_slice($ids, $keep) as $id) {
        rrmdir($parent . '/' . $id);
    }
}

/** Snapshot ids, newest first. */
function list_ids(string $parent): array
{
    if (!is_dir($parent)) {
        return [];
    }
    $ids = array_values(array_filter(scandir($parent), fn ($f) => $f[0] !== '.' && is_dir($parent . '/' . $f)));
    rsort($ids);
    return $ids;
}

// ---------- site files & draft ----------

function draft_init(): void
{
    ensure_data_dir();
    if (is_dir(DRAFT_DIR)) {
        return;
    }
    foreach (SITE_FILES as $f) {
        $src = SITE_DIR . '/' . $f;
        if (is_file($src)) {
            write_atomic(DRAFT_DIR . '/' . $f, (string) file_get_contents($src));
        }
    }
    mkdir(DRAFT_DIR . '/images', 0750, true);
}

function read_files(string $root): array
{
    $out = [];
    foreach (SITE_FILES as $f) {
        $p = $root . '/' . $f;
        $out[$f] = is_file($p) ? (string) file_get_contents($p) : '';
    }
    return $out;
}

function write_files(string $root, array $files): void
{
    foreach ($files as $f => $content) {
        if (in_array($f, SITE_FILES, true)) {
            write_atomic($root . '/' . $f, $content);
        }
    }
}

function draft_images(): array
{
    $dir = DRAFT_DIR . '/images';
    if (!is_dir($dir)) {
        return [];
    }
    return array_values(array_filter(scandir($dir), fn ($f) => isset(IMAGE_SLOTS[$f])));
}

/** True when the draft differs from what is live. */
function draft_dirty(): bool
{
    if (read_files(DRAFT_DIR) !== read_files(SITE_DIR)) {
        return true;
    }
    foreach (draft_images() as $img) {
        $live = SITE_DIR . '/images/' . $img;
        if (!is_file($live) || md5_file($live) !== md5_file(DRAFT_DIR . '/images/' . $img)) {
            return true;
        }
    }
    return false;
}

/** Save the draft's text files before a change, so it can be undone. */
function snapshot_draft(string $label, string $user): void
{
    $id = new_id();
    $dir = DATA_DIR . '/history/' . $id;
    write_files($dir, read_files(DRAFT_DIR));
    file_put_contents($dir . '/meta.json', json_encode(['label' => $label, 'by' => $user, 'at' => time()]));
    prune_dirs(DATA_DIR . '/history', KEEP_HISTORY);
}

// ---------- chat log ----------

function chat_add(string $who, string $role, string $text, array $extra = []): void
{
    $chat = load_json('chat.json');
    $chat[] = ['who' => $who, 'role' => $role, 'text' => $text, 'at' => time()] + $extra;
    save_json('chat.json', array_slice($chat, -200));
}

// ---------- sessions, users, auth ----------

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('rosinetti_editor');
    session_set_cookie_params([
        'lifetime' => 0,
        // Site-wide path: works whatever folder the editor sits in (spaces, capitals, rewrites).
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function users(): array
{
    return load_php('users.php');
}

function current_user(): ?array
{
    start_session();
    $name = $_SESSION['user'] ?? null;
    if (!is_string($name)) {
        return null;
    }
    $u = users()[$name] ?? null;
    // Signs people out when an admin removes them or resets their password.
    if (!$u || ($u['stamp'] ?? '') !== ($_SESSION['stamp'] ?? null)) {
        return null;
    }
    return ['name' => $name, 'role' => $u['role'], 'can_publish' => $u['role'] === 'admin' || !empty($u['can_publish'])];
}

function valid_username(string $u): bool
{
    return (bool) preg_match('/^[a-z0-9._-]{3,32}$/', $u);
}

function make_user(string $password, string $role, bool $canPublish): array
{
    return [
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role === 'admin' ? 'admin' : 'editor',
        'can_publish' => $role === 'admin' || $canPublish,
        'stamp' => bin2hex(random_bytes(8)),
        'created' => time(),
    ];
}

function login_attempt_allowed(string $key): bool
{
    $a = load_json('attempts.json');
    $recent = array_filter($a[$key] ?? [], fn ($t) => $t > time() - 900);
    return count($recent) < 5;
}

function login_failed(string $key): void
{
    $a = load_json('attempts.json');
    $a[$key] = array_values(array_filter($a[$key] ?? [], fn ($t) => $t > time() - 900));
    $a[$key][] = time();
    save_json('attempts.json', $a);
}

function log_in(string $name): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user'] = $name;
    $_SESSION['stamp'] = users()[$name]['stamp'];
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_ok(?string $token): bool
{
    return is_string($token) && hash_equals(csrf_token(), $token);
}

/**
 * True when a form was posted from this same site (browser-supplied Origin/Referer).
 * Used for the sign-in and setup forms so they keep working on hosts where the session cookie
 * isn't set yet; the editor's actions still require the session's CSRF token.
 */
function same_origin_post(): bool
{
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    $src = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    return $host !== '' && $src !== '' && strtolower((string) parse_url($src, PHP_URL_HOST)) === explode(':', $host)[0];
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
