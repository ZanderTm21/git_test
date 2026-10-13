<?php
// "Claude on subscription" mode: requests go to a private GitHub repository, where a GitHub Action runs Claude Code
// on the Claude subscription. The editor pushes the draft there, opens an issue per request, and pulls the result back.
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

const GH_BRANCH = 'site-draft';
const GH_LABEL = 'site-edit';
const GH_JOB_TIMEOUT = 20 * 60;

function gh_config(): array
{
    $c = load_php('config.php');
    return ['repo' => $c['github_repo'] ?? '', 'token' => $c['github_token'] ?? '', 'api' => $c['github_api'] ?? 'https://api.github.com'];
}

function gh_ready(): bool
{
    $c = load_php('config.php');
    return ($c['mode'] ?? 'api') === 'github' && !empty($c['github_repo']) && !empty($c['github_token']) && !empty($c['github_connected']);
}

/** One GitHub API call. Returns the decoded JSON; throws RuntimeException with a readable message on errors. */
function gh(string $method, string $path, ?array $body = null, array $okCodes = [200, 201])
{
    $c = gh_config();
    $client = new \GuzzleHttp\Client(['timeout' => 40, 'http_errors' => false]);
    $url = rtrim($c['api'], '/') . ($path[0] === '/' ? $path : '/repos/' . $c['repo'] . '/' . $path);
    $opts = ['headers' => [
        'Authorization' => 'Bearer ' . $c['token'],
        'Accept' => 'application/vnd.github+json',
        'X-GitHub-Api-Version' => '2022-11-28',
        'User-Agent' => 'rosinetti-site-editor',
    ]];
    if ($body !== null) {
        $opts['json'] = $body;
    }
    try {
        $res = $client->request($method, $url, $opts);
    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        throw new RuntimeException('Could not reach GitHub. Try again in a minute.');
    }
    $code = $res->getStatusCode();
    $data = json_decode((string) $res->getBody(), true);
    if (!in_array($code, $okCodes, true)) {
        $msg = is_array($data) ? ($data['message'] ?? '') : '';
        if ($code === 401) {
            throw new RuntimeException('GitHub rejected the access token. An admin needs to check it under Settings.');
        }
        if ($code === 403 || $code === 404) {
            throw new RuntimeException("GitHub refused access to {$c['repo']} ({$code}: {$msg}). Check the repository name and the token's permissions.");
        }
        throw new RuntimeException("GitHub error {$code}: {$msg}");
    }
    return $data;
}

function git_blob_sha(string $content): string
{
    return sha1('blob ' . strlen($content) . "\0" . $content);
}

/** Files the repository should hold for the site: path in repo => local path. */
function site_file_map(): array
{
    $map = [];
    foreach (SITE_FILES as $f) {
        $map['site/' . $f] = DRAFT_DIR . '/' . $f;
    }
    foreach (editable_images() as $img) {
        $map['site/images/' . $img] = image_path($img);
    }
    return $map;
}

/** Remote tree of a commit: path => blob sha. */
function gh_tree(string $commitSha): array
{
    $commit = gh('GET', 'git/commits/' . $commitSha);
    $tree = gh('GET', 'git/trees/' . $commit['tree']['sha'] . '?recursive=1');
    $out = [];
    foreach ($tree['tree'] as $t) {
        if ($t['type'] === 'blob') {
            $out[$t['path']] = $t['sha'];
        }
    }
    return ['tree' => $commit['tree']['sha'], 'files' => $out];
}

/** Commits $files (repo path => content) on top of $branch. Returns the new head sha (or the old one if nothing changed). */
function gh_commit(string $branch, array $files, string $message): string
{
    $head = gh('GET', 'git/ref/heads/' . $branch)['object']['sha'];
    $remote = gh_tree($head);
    $entries = [];
    foreach ($files as $path => $content) {
        if (($remote['files'][$path] ?? null) === git_blob_sha($content)) {
            continue;
        }
        $blob = gh('POST', 'git/blobs', ['content' => base64_encode($content), 'encoding' => 'base64']);
        $entries[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blob['sha']];
    }
    if (!$entries) {
        return $head;
    }
    $tree = gh('POST', 'git/trees', ['base_tree' => $remote['tree'], 'tree' => $entries]);
    $commit = gh('POST', 'git/commits', ['message' => $message, 'tree' => $tree['sha'], 'parents' => [$head]]);
    gh('PATCH', 'git/refs/heads/' . $branch, ['sha' => $commit['sha']]);
    return $commit['sha'];
}

/** One-time setup: workflow + instructions on the default branch, site files on the draft branch, the label. */
function gh_connect(): void
{
    $repo = gh('GET', '/repos/' . gh_config()['repo']);
    $default = $repo['default_branch'];
    $tpl = __DIR__ . '/github-template';
    $files = [];
    foreach (['.github/workflows/site-edit.yml', 'CLAUDE.md', 'tools/edit_image.py', '.gitignore'] as $f) {
        $files[$f] = (string) file_get_contents($tpl . '/' . $f);
    }
    try {
        gh('GET', 'git/ref/heads/' . $default);
    } catch (RuntimeException $e) {
        throw new RuntimeException('The repository is empty. Create it again with "Add a README file" ticked, then connect.');
    }
    $mainHead = gh_commit($default, $files, 'Set up site editor automation');
    // Draft branch: same setup files plus the site.
    try {
        gh('GET', 'git/ref/heads/' . GH_BRANCH);
    } catch (RuntimeException $e) {
        gh('POST', 'git/refs', ['ref' => 'refs/heads/' . GH_BRANCH, 'sha' => $mainHead]);
    }
    foreach (site_file_map() as $path => $local) {
        $files[$path] = (string) file_get_contents($local);
    }
    gh_commit(GH_BRANCH, $files, 'Sync site from editor');
    try {
        gh('POST', 'labels', ['name' => GH_LABEL, 'color' => 'c9a24a', 'description' => 'Change request from the site editor']);
    } catch (RuntimeException $e) {
        // label already exists
    }
    $c = load_php('config.php');
    $c['github_connected'] = true;
    save_php('config.php', $c);
}

/** Pushes the current draft, opens the request issue and records the pending job. */
function gh_submit(array $user, string $request, array $recentChat): void
{
    $files = [];
    foreach (site_file_map() as $path => $local) {
        $files[$path] = (string) file_get_contents($local);
    }
    $head = gh_commit(GH_BRANCH, $files, 'Sync site from editor before request');
    $synced = gh_tree($head)['files'];

    $context = '';
    foreach ($recentChat as $m) {
        $context .= '> ' . ($m['role'] === 'ai' ? 'Editor AI' : $m['who']) . ': ' . str_replace("\n", ' ', mb_strimwidth($m['text'], 0, 300, '…')) . "\n";
    }
    $body = ($context !== '' ? "Recent conversation in the editor:\n{$context}\n" : '') . "Request from {$user['name']}:\n\n{$request}";
    $issue = gh('POST', 'issues', [
        'title' => 'Site edit: ' . mb_strimwidth(preg_replace('/\s+/', ' ', $request), 0, 70, '…'),
        'body' => $body,
        'labels' => [GH_LABEL],
    ]);
    save_json('github_job.json', [
        'issue' => $issue['number'], 'by' => $user['name'], 'request' => $request,
        'started' => time(), 'synced' => $synced,
    ]);
}

function gh_pending(): ?array
{
    $job = load_json('github_job.json');
    return $job ? $job : null;
}

/**
 * Checks the pending request. Returns null while Claude is still working, otherwise
 * ['reply' => text, 'changed' => [...], 'error' => bool] after applying the result to the draft.
 */
function gh_poll(): ?array
{
    $job = gh_pending();
    if (!$job) {
        return null;
    }
    $comments = gh('GET', 'issues/' . $job['issue'] . '/comments?per_page=50');
    $done = null;
    foreach ($comments as $cm) {
        $text = (string) ($cm['body'] ?? '');
        if (str_contains($text, '<!-- site-edit:done -->') || str_contains($text, '<!-- site-edit:failed -->')) {
            $done = $cm;
        }
    }
    if (!$done) {
        if (time() - $job['started'] > GH_JOB_TIMEOUT) {
            @unlink(DATA_DIR . '/github_job.json');
            return ['reply' => 'Claude didn\'t answer within 20 minutes, so this request was stopped. Check the Actions tab on GitHub, or send it again.', 'changed' => [], 'error' => true];
        }
        return null;
    }
    $body = (string) $done['body'];
    $failed = str_contains($body, '<!-- site-edit:failed -->');
    $reply = trim(preg_replace('/<!--\s*site-edit:(done|failed)\s*-->/', '', $body));

    $changed = [];
    if (!$failed) {
        $head = gh('GET', 'git/ref/heads/' . GH_BRANCH)['object']['sha'];
        $remote = gh_tree($head)['files'];
        $updates = [];
        foreach ($remote as $path => $sha) {
            if (!str_starts_with($path, 'site/') || ($job['synced'][$path] ?? null) === $sha) {
                continue; // only take what Claude changed
            }
            $rel = substr($path, 5);
            $isSiteFile = in_array($rel, SITE_FILES, true);
            $isImage = str_starts_with($rel, 'images/') && valid_image_name(substr($rel, 7)) && !str_contains(substr($rel, 7), '/');
            if (!$isSiteFile && !$isImage) {
                continue;
            }
            $blob = gh('GET', 'git/blobs/' . $sha);
            $updates[$rel] = base64_decode((string) $blob['content']);
        }
        if ($updates) {
            $job2 = $job;
            with_lock(function () use ($updates, $job2) {
                $images = [];
                foreach (array_keys($updates) as $rel) {
                    if (str_starts_with($rel, 'images/')) {
                        $images[] = substr($rel, 7);
                    }
                }
                snapshot_draft($job2['request'], $job2['by'], $images);
                foreach ($updates as $rel => $content) {
                    write_atomic(DRAFT_DIR . '/' . $rel, $content);
                }
            });
            $changed = array_keys($updates);
        }
    }
    @unlink(DATA_DIR . '/github_job.json');
    return ['reply' => $reply !== '' ? $reply : 'Done.', 'changed' => $changed, 'error' => $failed];
}
