<?php
// Photo editing: Claude looks at the photo and picks operations from a fixed list; GD applies them on the server.
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Anthropic\Client;

const IMAGE_OPS = ['crop', 'resize', 'rotate', 'flip_horizontal', 'flip_vertical', 'brightness', 'contrast', 'saturation',
    'grayscale', 'warmth', 'tint', 'recolor', 'remove_background', 'sharpen', 'blur'];
const MAX_PIXEL_OPS_MP = 4.5; // per-pixel operations are skipped on larger images to stay within server time limits

const IMAGE_SYSTEM_PROMPT = <<<'TXT'
You edit a photo or logo used on the website of Rosinetti (tiling, bathrooms, interiors). A team member says how the image should change, often in Romanian or English. Look at the image and turn the request into a short list of operations that the server applies in order.

Respond with JSON:
- "reply": a short message to the team member, in their language, saying what you did, or why you couldn't.
- "ops": the operations, in order. Each op has every field; set the ones it doesn't use to null.

Operations:
- crop: keep the area x, y, width, height, given as fractions (0 to 1) of the image's current width and height, measured from the top-left corner.
- resize: amount = the longest side in pixels after resizing (only make images smaller).
- rotate: degrees clockwise (90, 180, 270, or a small angle to straighten).
- flip_horizontal, flip_vertical: mirror the image.
- brightness, contrast, saturation: amount from -100 to 100 (0 = no change; 15 to 30 is a noticeable but natural change).
- grayscale: black and white.
- warmth: amount from -100 (cooler, bluer) to 100 (warmer, more golden).
- tint: blend towards color (hex like #c9a24a) by amount 0 to 100.
- recolor: make the whole image one color family based on color (hex), keeping its shading and transparency. Good for logos and icons.
- remove_background: make the plain background transparent. color = the background color as hex, or null to take it from the corners; amount = tolerance 10 to 120 (default 45). Only works on PNG images; on photos with complex backgrounds it won't give good results.
- sharpen: amount 1 to 100. blur: amount 1 to 100.

You can only change how the existing image looks. You cannot add, remove or replace objects or people, generate new content, or write text onto the image. If that's what is asked, return no ops and explain briefly. If the request is unclear, ask one short question and return no ops.
TXT;

function image_schema(): array
{
    $num = ['anyOf' => [['type' => 'number'], ['type' => 'null']]];
    return [
        'type' => 'object',
        'properties' => [
            'reply' => ['type' => 'string'],
            'ops' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'op' => ['type' => 'string', 'enum' => IMAGE_OPS],
                        'x' => $num, 'y' => $num, 'width' => $num, 'height' => $num,
                        'amount' => $num, 'degrees' => $num,
                        'color' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
                    ],
                    'required' => ['op', 'x', 'y', 'width', 'height', 'amount', 'degrees', 'color'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['reply', 'ops'],
        'additionalProperties' => false,
    ];
}

function load_image(string $path)
{
    $im = @imagecreatefromstring((string) file_get_contents($path));
    if (!$im) {
        throw new RuntimeException('That image could not be opened.');
    }
    if (!imageistruecolor($im)) {
        imagepalettetotruecolor($im);
    }
    imagealphablending($im, false);
    imagesavealpha($im, true);
    return $im;
}

function save_image($im, string $path): void
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $tmp = $path . '.tmp' . bin2hex(random_bytes(3));
    if ($ext === 'png') {
        imagesavealpha($im, true);
        $ok = imagepng($im, $tmp, 7);
    } elseif ($ext === 'webp') {
        imagesavealpha($im, true);
        $ok = imagewebp($im, $tmp, 88);
    } else {
        // JPEG has no transparency: put the image on white first.
        $flat = imagecreatetruecolor(imagesx($im), imagesy($im));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
        $ok = imagejpeg($flat, $tmp, 88);
    }
    if (!$ok || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('The edited image could not be saved.');
    }
}

/** A copy of the image no larger than $max px on its longest side, encoded for Claude to look at. */
function image_for_ai(string $path, int $max = 1280): array
{
    $im = load_image($path);
    $w = imagesx($im);
    $h = imagesy($im);
    $scale = min(1, $max / max($w, $h));
    if ($scale < 1) {
        $im = imagescale($im, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC);
        imagealphablending($im, false);
        imagesavealpha($im, true);
    }
    ob_start();
    $png = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png';
    $png ? imagepng($im, null, 6) : imagejpeg($im, null, 85);
    return ['data' => base64_encode((string) ob_get_clean()), 'media' => $png ? 'image/png' : 'image/jpeg', 'w' => $w, 'h' => $h];
}

function ai_image_ops(array $config, string $path, string $file, string $instruction): array
{
    if (isset($GLOBALS['ai_image_override']) && is_callable($GLOBALS['ai_image_override'])) {
        return ($GLOBALS['ai_image_override'])($path, $instruction);
    }
    $img = image_for_ai($path);
    $client = new Client(apiKey: $config['api_key'], requestOptions: ['timeout' => 200, 'maxRetries' => 2]);
    $message = $client->beta->messages->create(
        model: $config['model'] ?? DEFAULT_MODEL,
        maxTokens: 16000,
        system: [['type' => 'text', 'text' => IMAGE_SYSTEM_PROMPT]],
        messages: [[
            'role' => 'user',
            'content' => [
                ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $img['media'], 'data' => $img['data']]],
                ['type' => 'text', 'text' => "Image: images/{$file} ({$img['w']}×{$img['h']} px, " . strtoupper(pathinfo($file, PATHINFO_EXTENSION)) . ").\n\nRequest:\n{$instruction}"],
            ],
        ]],
        outputConfig: ['effort' => 'medium', 'format' => ['type' => 'json_schema', 'schema' => image_schema()]],
        fallbacks: 'default',
        betas: ['server-side-fallback-2026-07-01'],
    );
    if ($message->stopReason === 'refusal') {
        throw new RuntimeException('The AI declined this request. Try wording it differently.');
    }
    foreach ($message->content as $block) {
        if ($block->type === 'text') {
            $data = json_decode($block->text, true);
            if (is_array($data) && isset($data['reply'], $data['ops'])) {
                return $data;
            }
        }
    }
    throw new RuntimeException('The AI returned an unexpected answer. Please try again.');
}

function hex_rgb(?string $hex): ?array
{
    if (!$hex || !preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
        return null;
    }
    return [hexdec(substr($m[1], 0, 2)), hexdec(substr($m[1], 2, 2)), hexdec(substr($m[1], 4, 2))];
}

/** Runs $fn(r, g, b, a) => [r, g, b, a] over every pixel. */
function map_pixels($im, callable $fn): void
{
    $w = imagesx($im);
    $h = imagesy($im);
    if ($w * $h > MAX_PIXEL_OPS_MP * 1e6) {
        throw new RuntimeException('This image is too large for that change. Ask to make it smaller first.');
    }
    imagealphablending($im, false);
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($im, $x, $y);
            [$r, $g, $b, $a] = $fn(($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127);
            imagesetpixel($im, $x, $y, (max(0, min(127, (int) $a)) << 24) | (max(0, min(255, (int) $r)) << 16) | (max(0, min(255, (int) $g)) << 8) | max(0, min(255, (int) $b)));
        }
    }
}

function clampf($v, float $lo, float $hi, float $default): float
{
    return is_numeric($v) ? max($lo, min($hi, (float) $v)) : $default;
}

/** Applies the operations in order and returns the new image. */
function apply_image_ops($im, array $ops, string $ext)
{
    foreach ($ops as $o) {
        $op = $o['op'] ?? '';
        $amount = $o['amount'] ?? null;
        switch ($op) {
            case 'crop':
                $w = imagesx($im);
                $h = imagesy($im);
                $x = (int) round(clampf($o['x'], 0, 1, 0) * $w);
                $y = (int) round(clampf($o['y'], 0, 1, 0) * $h);
                $cw = (int) round(clampf($o['width'], 0.02, 1, 1) * $w);
                $ch = (int) round(clampf($o['height'], 0.02, 1, 1) * $h);
                $rect = ['x' => $x, 'y' => $y, 'width' => max(1, min($cw, $w - $x)), 'height' => max(1, min($ch, $h - $y))];
                $im = imagecrop($im, $rect) ?: $im;
                break;
            case 'resize':
                $max = (int) clampf($amount, 50, 4000, 1600);
                $w = imagesx($im);
                $h = imagesy($im);
                if (max($w, $h) > $max) {
                    $s = $max / max($w, $h);
                    $im = imagescale($im, (int) round($w * $s), (int) round($h * $s), IMG_BICUBIC);
                }
                break;
            case 'rotate':
                $deg = clampf($o['degrees'], -360, 360, 0);
                if ($deg != 0) {
                    $bg = $ext === 'png' || $ext === 'webp' ? imagecolorallocatealpha($im, 0, 0, 0, 127) : imagecolorallocate($im, 255, 255, 255);
                    $im = imagerotate($im, -$deg, $bg); // GD turns counter-clockwise
                }
                break;
            case 'flip_horizontal':
                imageflip($im, IMG_FLIP_HORIZONTAL);
                break;
            case 'flip_vertical':
                imageflip($im, IMG_FLIP_VERTICAL);
                break;
            case 'brightness':
                imagefilter($im, IMG_FILTER_BRIGHTNESS, (int) round(clampf($amount, -100, 100, 0) * 2.55));
                break;
            case 'contrast':
                imagefilter($im, IMG_FILTER_CONTRAST, (int) round(-clampf($amount, -100, 100, 0))); // GD: negative = more contrast
                break;
            case 'grayscale':
                imagefilter($im, IMG_FILTER_GRAYSCALE);
                break;
            case 'saturation':
                $k = 1 + clampf($amount, -100, 100, 0) / 100;
                map_pixels($im, function ($r, $g, $b, $a) use ($k) {
                    $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                    return [$l + ($r - $l) * $k, $l + ($g - $l) * $k, $l + ($b - $l) * $k, $a];
                });
                break;
            case 'warmth':
                $t = clampf($amount, -100, 100, 0) / 100;
                map_pixels($im, fn ($r, $g, $b, $a) => [$r + 30 * $t, $g + 8 * $t, $b - 30 * $t, $a]);
                break;
            case 'tint':
                $c = hex_rgb($o['color'] ?? null) ?? [201, 162, 74];
                $s = clampf($amount, 0, 100, 25) / 100;
                map_pixels($im, fn ($r, $g, $b, $a) => [$r + ($c[0] - $r) * $s, $g + ($c[1] - $g) * $s, $b + ($c[2] - $b) * $s, $a]);
                break;
            case 'recolor':
                $c = hex_rgb($o['color'] ?? null) ?? [201, 162, 74];
                // Dark tones map to a deep version of the color, light tones to a pale one, so shading survives.
                $dark = [$c[0] * 0.55, $c[1] * 0.55, $c[2] * 0.55];
                $light = [$c[0] + (255 - $c[0]) * 0.6, $c[1] + (255 - $c[1]) * 0.6, $c[2] + (255 - $c[2]) * 0.6];
                map_pixels($im, function ($r, $g, $b, $a) use ($dark, $c, $light) {
                    $l = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                    [$from, $to, $t] = $l < 0.5 ? [$dark, $c, $l * 2] : [$c, $light, ($l - 0.5) * 2];
                    return [$from[0] + ($to[0] - $from[0]) * $t, $from[1] + ($to[1] - $from[1]) * $t, $from[2] + ($to[2] - $from[2]) * $t, $a];
                });
                break;
            case 'remove_background':
                if ($ext !== 'png') {
                    throw new RuntimeException('Background removal needs a PNG image. This one is ' . strtoupper($ext) . ', which can\'t be transparent.');
                }
                $bg = hex_rgb($o['color'] ?? null);
                if (!$bg) {
                    $w = imagesx($im) - 1;
                    $h = imagesy($im) - 1;
                    $sum = [0, 0, 0];
                    foreach ([[0, 0], [$w, 0], [0, $h], [$w, $h]] as [$px, $py]) {
                        $cc = imagecolorat($im, $px, $py);
                        $sum[0] += ($cc >> 16) & 255;
                        $sum[1] += ($cc >> 8) & 255;
                        $sum[2] += $cc & 255;
                    }
                    $bg = [$sum[0] / 4, $sum[1] / 4, $sum[2] / 4];
                }
                $tol = clampf($amount, 5, 200, 45);
                map_pixels($im, function ($r, $g, $b, $a) use ($bg, $tol) {
                    $d = sqrt(($r - $bg[0]) ** 2 + ($g - $bg[1]) ** 2 + ($b - $bg[2]) ** 2);
                    if ($d >= $tol * 1.6) {
                        return [$r, $g, $b, $a];
                    }
                    $keep = max(0, ($d - $tol) / ($tol * 0.6)); // soft edge between tol and 1.6×tol
                    return [$r, $g, $b, max($a, 127 - $keep * 127)];
                });
                break;
            case 'sharpen':
                $s = clampf($amount, 1, 100, 30) / 100;
                $k = -$s;
                imageconvolution($im, [[0, $k, 0], [$k, 1 + 4 * $s, $k], [0, $k, 0]], 1, 0);
                break;
            case 'blur':
                $n = max(1, (int) round(clampf($amount, 1, 100, 20) / 10));
                for ($i = 0; $i < $n; $i++) {
                    imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
                }
                break;
        }
        imagealphablending($im, false);
        imagesavealpha($im, true);
    }
    return $im;
}
