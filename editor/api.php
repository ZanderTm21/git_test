<?php
// JSON actions for the editor UI. Every action needs a signed-in user and the CSRF token.
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $code = 400): never
{
    out(['error' => $message], $code);
}

$user = current_user();
if (!$user) {
    fail('You are signed out. Reload the page to sign in again.', 401);
}
$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    fail('This request was blocked. Reload the page and try again.', 403);
}
$in = json_decode((string) file_get_contents('php://input'), true) ?: [];

function need_admin(array $user): void
{
    if ($user['role'] !== 'admin') {
        fail('Only an admin can do this.', 403);
    }
}

function state(array $user): array
{
    $s = [
        'user' => $user,
        'chat' => array_slice(load_json('chat.json'), -60),
        'dirty' => draft_dirty(),
        'canUndo' => count(list_ids(DATA_DIR . '/history')) > 0,
        'slots' => IMAGE_SLOTS,
        'draftImages' => draft_images(),
        'editableImages' => editable_images(),
        'hasKey' => !empty(load_php('config.php')['api_key']),
        'mode' => (load_php('config.php')['mode'] ?? 'api') === 'github' ? 'github' : 'api',
        'pending' => (function () {
            $j = load_json('github_job.json');
            return $j ? ['by' => $j['by'], 'request' => $j['request'], 'started' => $j['started']] : null;
        })(),
    ];
    if ($user['role'] === 'admin') {
        $s['users'] = [];
        foreach (users() as $name => $u) {
            $s['users'][] = ['name' => $name, 'role' => $u['role'], 'can_publish' => !empty($u['can_publish'])];
        }
        $s['versions'] = [];
        foreach (list_ids(DATA_DIR . '/versions') as $id) {
            $m = json_decode((string) @file_get_contents(DATA_DIR . "/versions/{$id}/meta.json"), true) ?: [];
            $s['versions'][] = ['id' => $id, 'by' => $m['by'] ?? '?', 'at' => $m['at'] ?? 0];
        }
        $s['model'] = load_php('config.php')['model'] ?? DEFAULT_MODEL;
        $cfg = load_php('config.php');
        $s['github'] = ['repo' => $cfg['github_repo'] ?? '', 'hasToken' => !empty($cfg['github_token']), 'connected' => !empty($cfg['github_connected'])];
    }
    return $s;
}

draft_init();

switch ($action) {
    case 'state':
        out(state($user));

    case 'ask':
        $request = trim((string) ($in['message'] ?? ''));
        if ($request === '' || mb_strlen($request) > 4000) {
            fail('Write what you want changed (up to 4000 characters).');
        }
        if ((load_php('config.php')['mode'] ?? 'api') === 'github') {
            send_to_github($user, $request);
        }
        $config = load_php('config.php');
        if (empty($config['api_key'])) {
            fail('No Claude API key is set yet. An admin can add it under Settings.');
        }
        @set_time_limit(320);
        require_once __DIR__ . '/ai.php';
        chat_add($user['name'], 'user', $request);
        // Read the draft and recent chat now; the AI call itself runs without the lock so others aren't blocked for a minute.
        $files = read_files(DRAFT_DIR);
        $recent = array_slice(load_json('chat.json'), -9, 8);
        try {
            $result = ai_edit($config, $files, $request, $recent);
        } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
            chat_add('Editor', 'ai', 'The Claude API key was rejected. An admin needs to check it under Settings.', ['error' => true]);
            fail('The Claude API key was rejected. An admin needs to check it under Settings.', 502);
        } catch (\Anthropic\Core\Exceptions\RateLimitException $e) {
            fail('Too many requests to Claude right now. Wait a minute and try again.', 503);
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            fail('Claude is unavailable right now (' . ($e->type?->value ?? 'error') . '). Try again in a minute.', 503);
        } catch (\Anthropic\Core\Exceptions\APIConnectionException $e) {
            fail('Could not reach Claude. Check the server\'s internet connection and try again.', 503);
        } catch (RuntimeException $e) {
            chat_add('Editor', 'ai', $e->getMessage(), ['error' => true]);
            fail($e->getMessage(), 422);
        }
        $saved = with_lock(function () use ($files, $result, $user, $request) {
            if (read_files(DRAFT_DIR) !== $files) {
                return false; // someone else changed the draft while the AI was working
            }
            if ($result['changed']) {
                snapshot_draft($request, $user['name']);
                write_files(DRAFT_DIR, $result['files']);
            }
            return true;
        });
        if (!$saved) {
            $msg = 'Someone else changed the site while this was being worked on, so this change was not applied. Send your request again.';
            chat_add('Editor', 'ai', $msg, ['error' => true]);
            fail($msg, 409);
        }
        chat_add('Editor', 'ai', $result['reply'], ['changed' => $result['changed']]);
        out(state($user));

    case 'undo':
        with_lock(function () use ($user) {
            $ids = list_ids(DATA_DIR . '/history');
            if (!$ids) {
                fail('There is nothing to undo.');
            }
            $dir = DATA_DIR . '/history/' . $ids[0];
            write_files(DRAFT_DIR, read_files($dir));
            $meta = json_decode((string) @file_get_contents($dir . '/meta.json'), true) ?: [];
            foreach (($meta['images'] ?? []) as $f => $existed) {
                if (!valid_image_name((string) $f)) {
                    continue;
                }
                $existed ? copy($dir . '/images/' . $f, DRAFT_DIR . '/images/' . $f) : @unlink(DRAFT_DIR . '/images/' . $f);
            }
            rrmdir($dir);
            chat_add('Editor', 'ai', 'Undone: "' . mb_strimwidth((string) ($meta['label'] ?? 'last change'), 0, 80, '…') . '" (by ' . $user['name'] . ').');
        });
        out(state($user));

    case 'discard':
        with_lock(function () use ($user) {
            snapshot_draft('Before discarding the draft', $user['name']);
            write_files(DRAFT_DIR, read_files(SITE_DIR));
            foreach (draft_images() as $img) {
                unlink(DRAFT_DIR . '/images/' . $img);
            }
            chat_add('Editor', 'ai', $user['name'] . ' discarded the unpublished changes. The draft now matches the live site.');
        });
        out(state($user));

    case 'publish':
        if (!$user['can_publish']) {
            fail('You don\'t have permission to publish. Ask an admin.', 403);
        }
        with_lock(function () use ($user) {
            if (trim(read_files(DRAFT_DIR)['index.html']) === '') {
                fail('The draft page is empty, so nothing was published. Use Discard changes or ask for help.');
            }
            $id = new_id();
            $vdir = DATA_DIR . '/versions/' . $id;
            // Keep a copy of what is live now, so it can be restored.
            write_files($vdir, read_files(SITE_DIR));
            foreach (draft_images() as $img) {
                $live = SITE_DIR . '/images/' . $img;
                if (is_file($live)) {
                    @mkdir($vdir . '/images', 0750, true);
                    copy($live, $vdir . '/images/' . $img);
                }
            }
            file_put_contents($vdir . '/meta.json', json_encode(['by' => $user['name'], 'at' => time()]));
            prune_dirs(DATA_DIR . '/versions', KEEP_VERSIONS);

            write_files(SITE_DIR, read_files(DRAFT_DIR));
            @mkdir(SITE_DIR . '/images', 0755, true);
            foreach (draft_images() as $img) {
                copy(DRAFT_DIR . '/images/' . $img, SITE_DIR . '/images/' . $img);
            }
            chat_add('Editor', 'ai', $user['name'] . ' published the changes. They are live now.');
        });
        out(state($user));

    case 'image_edit':
        $file = (string) ($in['file'] ?? '');
        $instruction = trim((string) ($in['instruction'] ?? ''));
        if (!in_array($file, editable_images(), true)) {
            fail('Choose a photo to edit.');
        }
        if ($instruction === '' || mb_strlen($instruction) > 2000) {
            fail('Write how the photo should change (up to 2000 characters).');
        }
        if ((load_php('config.php')['mode'] ?? 'api') === 'github') {
            send_to_github($user, '[Photo images/' . $file . '] ' . $instruction);
        }
        if (!function_exists('imagecreatefromstring')) {
            fail('Photo editing needs the GD extension, which is turned off on this server.');
        }
        $config = load_php('config.php');
        if (empty($config['api_key'])) {
            fail('No Claude API key is set yet. An admin can add it under Settings.');
        }
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        require_once __DIR__ . '/image.php';
        chat_add($user['name'], 'user', '[Photo ' . $file . '] ' . $instruction);
        $src = image_path($file);
        $before = md5_file($src);
        try {
            $plan = ai_image_ops($config, $src, $file, $instruction);
            $ops = array_values(array_filter($plan['ops'], fn ($o) => in_array($o['op'] ?? '', IMAGE_OPS, true)));
            $edited = $ops ? apply_image_ops(load_image($src), $ops, strtolower(pathinfo($file, PATHINFO_EXTENSION))) : null;
        } catch (\Anthropic\Core\Exceptions\AuthenticationException $e) {
            fail('The Claude API key was rejected. An admin needs to check it under Settings.', 502);
        } catch (\Anthropic\Core\Exceptions\APIStatusException | \Anthropic\Core\Exceptions\APIConnectionException $e) {
            fail('Claude is unavailable right now. Try again in a minute.', 503);
        } catch (RuntimeException $e) {
            chat_add('Editor', 'ai', $e->getMessage(), ['error' => true]);
            fail($e->getMessage(), 422);
        }
        if ($edited) {
            $saved = with_lock(function () use ($file, $src, $before, $edited, $instruction, $user) {
                if (md5_file(image_path($file)) !== $before) {
                    return false;
                }
                snapshot_draft('[Photo ' . $file . '] ' . $instruction, $user['name'], [$file]);
                @mkdir(DRAFT_DIR . '/images', 0750, true);
                save_image($edited, DRAFT_DIR . '/images/' . $file);
                return true;
            });
            if (!$saved) {
                fail('Someone else changed this photo at the same time, so this edit was not applied. Try again.', 409);
            }
        }
        chat_add('Editor', 'ai', (string) $plan['reply'], ['changed' => $edited ? ['images/' . $file] : []]);
        out(state($user));

    case 'upload':
        $slot = (string) ($_GET['slot'] ?? '');
        if (!isset(IMAGE_SLOTS[$slot])) {
            fail('Choose where the photo goes.');
        }
        $f = $_FILES['photo'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            fail('The upload failed. Photos can be up to 15 MB.');
        }
        if ($f['size'] > 15 * 1024 * 1024) {
            fail('That photo is too large. The limit is 15 MB.');
        }
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            fail('Upload a JPG, PNG or WebP photo.');
        }
        @mkdir(DRAFT_DIR . '/images', 0750, true);
        save_photo($f['tmp_name'], $info, DRAFT_DIR . '/images/' . $slot);
        chat_add('Editor', 'ai', $user['name'] . ' uploaded a photo for "' . IMAGE_SLOTS[$slot] . '".');
        out(state($user));

    case 'password':
        $old = (string) ($in['old'] ?? '');
        $new = (string) ($in['new'] ?? '');
        $all = users();
        if (!password_verify($old, $all[$user['name']]['hash'])) {
            fail('Your current password is wrong.');
        }
        if (strlen($new) < 10) {
            fail('Use at least 10 characters for the new password.');
        }
        $all[$user['name']]['hash'] = password_hash($new, PASSWORD_DEFAULT);
        $all[$user['name']]['stamp'] = bin2hex(random_bytes(8));
        save_php('users.php', $all);
        log_in($user['name']);
        out(['ok' => true, 'csrf' => csrf_token()]);

    case 'user_save':
        need_admin($user);
        $name = strtolower(trim((string) ($in['name'] ?? '')));
        $pass = (string) ($in['password'] ?? '');
        $role = ($in['role'] ?? '') === 'admin' ? 'admin' : 'editor';
        $canPublish = !empty($in['can_publish']);
        $all = users();
        if (!valid_username($name)) {
            fail('Usernames are 3–32 characters: lowercase letters, numbers, dot, dash or underscore.');
        }
        if (!isset($all[$name])) {
            if (strlen($pass) < 10) {
                fail('Give the new user a password of at least 10 characters.');
            }
            $all[$name] = make_user($pass, $role, $canPublish);
        } else {
            if ($name === $user['name'] && $role !== 'admin') {
                fail('You can\'t remove your own admin role.');
            }
            $all[$name]['role'] = $role;
            $all[$name]['can_publish'] = $role === 'admin' || $canPublish;
            if ($pass !== '') {
                if (strlen($pass) < 10) {
                    fail('Use at least 10 characters for the password.');
                }
                $all[$name]['hash'] = password_hash($pass, PASSWORD_DEFAULT);
                $all[$name]['stamp'] = bin2hex(random_bytes(8)); // signs them out everywhere
            }
        }
        save_php('users.php', $all);
        out(state($user));

    case 'user_delete':
        need_admin($user);
        $name = (string) ($in['name'] ?? '');
        $all = users();
        if ($name === $user['name']) {
            fail('You can\'t delete your own account.');
        }
        unset($all[$name]);
        save_php('users.php', $all);
        out(state($user));

    case 'settings':
        need_admin($user);
        $config = load_php('config.php');
        $key = trim((string) ($in['api_key'] ?? ''));
        if ($key !== '') {
            if (!str_starts_with($key, 'sk-ant-')) {
                fail('That doesn\'t look like a Claude API key. It should start with "sk-ant-".');
            }
            $config['api_key'] = $key;
        }
        $model = trim((string) ($in['model'] ?? ''));
        if ($model !== '') {
            if (!preg_match('/^claude-[a-z0-9.-]+$/', $model)) {
                fail('That model name isn\'t valid.');
            }
            $config['model'] = $model;
        }
        $mode = (string) ($in['mode'] ?? '');
        if (in_array($mode, ['api', 'github'], true)) {
            $config['mode'] = $mode;
        }
        $repo = trim((string) ($in['github_repo'] ?? ''));
        if ($repo !== '' && $repo !== ($config['github_repo'] ?? '')) {
            if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)) {
                fail('Write the repository as owner/name, for example lyonscale/rosinetti-site.');
            }
            $config['github_repo'] = $repo;
            $config['github_connected'] = false;
        }
        $ghToken = trim((string) ($in['github_token'] ?? ''));
        if ($ghToken !== '') {
            $config['github_token'] = $ghToken;
            $config['github_connected'] = false;
        }
        save_php('config.php', $config);
        out(state($user));

    case 'github_connect':
        need_admin($user);
        require_once __DIR__ . '/github.php';
        try {
            with_lock(fn () => gh_connect());
        } catch (RuntimeException $e) {
            fail($e->getMessage(), 422);
        }
        chat_add('Editor', 'ai', 'GitHub is connected. Requests now go to Claude on the subscription.');
        out(state($user));

    case 'poll':
        if (gh_pending_job()) {
            require_once __DIR__ . '/github.php';
            try {
                $res = gh_poll();
            } catch (RuntimeException $e) {
                fail($e->getMessage(), 503);
            }
            if ($res) {
                chat_add('Claude', 'ai', $res['reply'], ['changed' => $res['changed']] + ($res['error'] ? ['error' => true] : []));
            }
        }
        out(state($user));

    case 'restore':
        need_admin($user);
        $id = (string) ($in['id'] ?? '');
        if (!in_array($id, list_ids(DATA_DIR . '/versions'), true)) {
            fail('That version no longer exists.');
        }
        with_lock(function () use ($id, $user) {
            $vdir = DATA_DIR . '/versions/' . $id;
            snapshot_draft('Before restoring version ' . $id, $user['name']);
            write_files(DRAFT_DIR, read_files($vdir));
            foreach (glob($vdir . '/images/*') ?: [] as $img) {
                if (isset(IMAGE_SLOTS[basename($img)])) {
                    copy($img, DRAFT_DIR . '/images/' . basename($img));
                }
            }
            chat_add('Editor', 'ai', $user['name'] . ' loaded the version from ' . $id . ' into the draft. Publish it to make it live.');
        });
        out(state($user));

    default:
        fail('Unknown action.', 404);
}

function gh_pending_job(): bool
{
    return is_file(DATA_DIR . '/github_job.json');
}

/** Subscription mode: hand the request to Claude through GitHub, then return right away. */
function send_to_github(array $user, string $request): never
{
    require_once __DIR__ . '/github.php';
    if (!gh_ready()) {
        fail('GitHub isn\'t connected yet. An admin can connect it under Settings.');
    }
    $recent = array_slice(load_json('chat.json'), -8);
    try {
        $sent = with_lock(function () use ($user, $request, $recent) {
            if (gh_pending_job()) {
                return false;
            }
            gh_submit($user, $request, $recent);
            return true;
        });
    } catch (RuntimeException $e) {
        fail($e->getMessage(), 503);
    }
    if (!$sent) {
        fail('Claude is still working on the previous request. This one can be sent as soon as it\'s done.', 409);
    }
    chat_add($user['name'], 'user', $request);
    chat_add('Editor', 'ai', 'Sent to Claude. The preview updates here when it\'s done, usually in 1–3 minutes.');
    out(state($user));
}

/** Re-encodes the photo as a JPEG (max 2400px, upright), which also strips anything that isn't image data. */
function save_photo(string $tmp, array $info, string $dest): void
{
    if (!function_exists('imagecreatefromstring')) {
        if ($info[2] !== IMAGETYPE_JPEG) {
            fail('This server can only take JPG photos. Convert the photo to JPG and try again.');
        }
        copy($tmp, $dest);
        return;
    }
    $img = @imagecreatefromstring((string) file_get_contents($tmp));
    if (!$img) {
        fail('That photo could not be read. Try saving it again as JPG.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) {
            $img = imagerotate($img, $rot, 0);
        }
    }
    $w = imagesx($img);
    $hgt = imagesy($img);
    $scale = min(1, 2400 / max($w, $hgt));
    if ($scale < 1) {
        $img = imagescale($img, (int) round($w * $scale), (int) round($hgt * $scale), IMG_BICUBIC);
    }
    imagejpeg($img, $dest, 85);
}
