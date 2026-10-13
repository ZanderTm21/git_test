<?php
// Asks Claude for find/replace edits to the site files and applies them to a copy in memory.
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Anthropic\Client;

const EDIT_SYSTEM_PROMPT = <<<'TXT'
You edit the website of Rosinetti, a company doing tiling, bathrooms and interiors. The site is a static one-page website made of the files you are given. A team member describes a change in plain language, often in Romanian or English, and you make it.

Respond with JSON:
- "reply": a short message to the team member, in the language they wrote in, saying what you changed, or why you changed nothing.
- "edits": the find/replace operations to apply, in order.

How edits work:
- "find" is copied exactly from the current file and appears exactly once in it. Include a little surrounding text when needed to make it unique.
- Edits apply in order, so a later edit sees the result of earlier ones in the same file.
- Change what was asked and keep everything else as it is: the visual style, class names, the before/after sliders, the quote pop-up, the animations and the mobile layout should keep working.

Photos: team members upload photos in the editor's Photos panel. They land in images/ under these fixed names: {SLOTS}. You can point the page at those names, but you cannot create, download or fetch images. When someone wants a new photo, tell them which slot to upload it to. To change how an existing photo or logo looks (crop, brighten, recolor, remove a plain background…), tell them to use "Edit a photo" in the Photos panel. A request may include "[Attached photo: images/NAME]": that photo is already uploaded to images/ and you can place it on the page (for example as a gallery item or a background) by its name.

Don't add tracking, third-party scripts, or forms that send data somewhere else unless the team member explicitly asks for that.
If the request is unclear, ask one short question in "reply" and return no edits.
TXT;

/** @param array<string,string> $files */
function ai_request_edits(array $config, array $files, string $request, array $recentChat, string $retryNote = ''): array
{
    if (isset($GLOBALS['ai_edit_override']) && is_callable($GLOBALS['ai_edit_override'])) {
        return ($GLOBALS['ai_edit_override'])($files, $request, $retryNote);
    }

    $client = new Client(
        apiKey: $config['api_key'],
        requestOptions: ['timeout' => 280, 'maxRetries' => 2],
    );

    $dump = '';
    foreach ($files as $path => $content) {
        $dump .= "<file path=\"{$path}\">\n{$content}\n</file>\n\n";
    }

    $history = '';
    foreach ($recentChat as $m) {
        $history .= ($m['role'] === 'ai' ? 'Editor AI' : $m['who']) . ': ' . $m['text'] . "\n";
    }

    $ask = ($history !== '' ? "Recent conversation in the editor:\n{$history}\n" : '')
        . "New request:\n{$request}"
        . ($retryNote !== '' ? "\n\n{$retryNote}" : '');

    $schema = [
        'type' => 'object',
        'properties' => [
            'reply' => ['type' => 'string'],
            'edits' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'file' => ['type' => 'string', 'enum' => SITE_FILES],
                        'find' => ['type' => 'string'],
                        'replace' => ['type' => 'string'],
                    ],
                    'required' => ['file', 'find', 'replace'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['reply', 'edits'],
        'additionalProperties' => false,
    ];

    $message = $client->beta->messages->create(
        model: $config['model'] ?? DEFAULT_MODEL,
        maxTokens: 32000,
        system: [[
            'type' => 'text',
            'text' => str_replace('{SLOTS}', implode(', ', array_keys(IMAGE_SLOTS)), EDIT_SYSTEM_PROMPT),
        ]],
        messages: [[
            'role' => 'user',
            'content' => [
                // The files are the large, stable part: cached so a retry in the same request is cheap.
                ['type' => 'text', 'text' => "Current site files:\n\n" . $dump, 'cacheControl' => ['type' => 'ephemeral']],
                ['type' => 'text', 'text' => $ask],
            ],
        ]],
        outputConfig: [
            'effort' => 'medium',
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ],
        // If a request is declined by a safety classifier, the API retries it on a fallback model.
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );

    if ($message->stopReason === 'refusal') {
        throw new RuntimeException('The AI declined this request. Try wording it differently.');
    }
    if ($message->stopReason === 'max_tokens') {
        throw new RuntimeException('That change was too big to make in one go. Try splitting it into smaller requests.');
    }
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data) && isset($data['reply'], $data['edits'])) {
                return $data;
            }
        }
    }
    throw new RuntimeException('The AI returned an unexpected answer. Please try again.');
}

/**
 * Applies edits to a copy of the files. Returns [newFiles, errors]; files are unchanged when there are errors.
 * @param array<string,string> $files
 */
function apply_edits(array $files, array $edits): array
{
    $errors = [];
    foreach ($edits as $i => $e) {
        $n = $i + 1;
        $file = $e['file'] ?? '';
        $find = (string) ($e['find'] ?? '');
        if (!array_key_exists($file, $files)) {
            $errors[] = "Edit {$n}: unknown file \"{$file}\".";
            continue;
        }
        $count = $find === '' ? 0 : substr_count($files[$file], $find);
        if ($count !== 1) {
            $errors[] = "Edit {$n} ({$file}): the \"find\" text appears {$count} times, it must appear exactly once.";
            continue;
        }
        $files[$file] = str_replace($find, (string) $e['replace'], $files[$file]);
    }
    return [$files, $errors];
}

/** Full edit round: ask, apply, and retry once with the errors if some edits didn't match. */
function ai_edit(array $config, array $files, string $request, array $recentChat): array
{
    $result = ai_request_edits($config, $files, $request, $recentChat);
    [$newFiles, $errors] = apply_edits($files, $result['edits']);
    if ($errors) {
        $note = "Your previous answer could not be applied, nothing was changed:\n- " . implode("\n- ", $errors)
            . "\nSend the complete list of edits again against the files exactly as shown above.";
        $result = ai_request_edits($config, $files, $request, $recentChat, $note);
        [$newFiles, $errors] = apply_edits($files, $result['edits']);
        if ($errors) {
            throw new RuntimeException("The AI's changes didn't match the current files, so nothing was changed. Try rephrasing the request.");
        }
    }
    $changed = array_keys(array_filter($newFiles, fn ($c, $f) => $c !== $files[$f], ARRAY_FILTER_USE_BOTH));
    return ['reply' => (string) $result['reply'], 'files' => $newFiles, 'changed' => $changed];
}
