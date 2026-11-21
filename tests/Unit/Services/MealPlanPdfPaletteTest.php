<?php

declare(strict_types=1);

use App\Enums\MealCategory;

/**
 * Guards the palette mirror in resources/views/pdf/meal-plan.blade.php and meal-plan-menu.blade.php: dompdf cannot read CSS
 * variables, so the template carries hexes that must stay in sync with resources/css/app.css :root.
 */

/**
 * @param  string  $view  Template name under resources/views/pdf/, without `.blade.php`.
 * @return array{templateWithoutMirror: string, css: string, mirror: array<string, array{oklch: string, hex: string}>}
 */
function pdfPaletteSources(string $view): array
{
    $template = (string) file_get_contents(resource_path('views/pdf/'.$view.'.blade.php'));
    $css = (string) file_get_contents(resource_path('css/app.css'));

    preg_match('~/\*.*?\*/~s', $template, $comment);
    $mirrorComment = $comment[0] ?? '';

    preg_match_all(
        '~^\s*\*\s+(--[a-z0-9-]+)\s+(oklch\([^)]*\))\s+(#[0-9a-fA-F]{6})\s*$~m',
        $mirrorComment,
        $matches,
        PREG_SET_ORDER,
    );

    $mirror = [];
    foreach ($matches as $match) {
        $mirror[$match[1]] = ['oklch' => $match[2], 'hex' => strtolower($match[3])];
    }

    return [
        'templateWithoutMirror' => $mirrorComment === '' ? $template : str_replace($mirrorComment, '', $template),
        'css' => $css,
        'mirror' => $mirror,
    ];
}

function normalizePaletteValue(string $value): string
{
    return trim((string) preg_replace('/\s+/', ' ', $value));
}

/**
 * The `:root { ... }` block of app.css, without the `.dark` block.
 */
function paletteRootBlock(string $css): string
{
    preg_match('/(?<![\w.-]):root\s*\{(.*?)\}/s', $css, $block);

    return $block[1] ?? '';
}

/**
 * Converts `oklch(L C H)` to the sRGB-clipped hex a browser on an sRGB screen would show.
 */
function clippedHexFromOklch(string $oklch): string
{
    preg_match('/oklch\(\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\)/', $oklch, $parts);
    [, $lightness, $chroma, $hue] = array_map('floatval', $parts);

    $a = $chroma * cos(deg2rad($hue));
    $b = $chroma * sin(deg2rad($hue));

    $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

    $linear = [
        4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
        -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
        -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
    ];

    $hex = '#';
    foreach ($linear as $channel) {
        $channel = min(1.0, max(0.0, $channel));
        $gamma = $channel <= 0.0031308 ? 12.92 * $channel : 1.055 * ($channel ** (1 / 2.4)) - 0.055;
        $hex .= sprintf('%02x', (int) round($gamma * 255));
    }

    return $hex;
}

dataset('pdf palette templates', [
    'full plan' => ['meal-plan', 12],
    'menu only' => ['meal-plan-menu', 10],
]);

it('lists the expected number of mirrored tokens in the template comment', function (string $view, int $minimum): void {
    expect(count(pdfPaletteSources($view)['mirror']))->toBeGreaterThanOrEqual($minimum);
})->with('pdf palette templates');

it('keeps every mirrored token equal to app.css :root', function (string $view): void {
    $sources = pdfPaletteSources($view);
    $root = paletteRootBlock($sources['css']);

    expect($root)->not->toBe('');

    foreach ($sources['mirror'] as $token => $entry) {
        $found = preg_match('/(?<![\w-])'.preg_quote($token, '/').'\s*:\s*([^;]+);/', $root, $declaration);

        expect($found)->toBe(1, "Token {$token} is missing from the :root block of app.css.");

        $actual = normalizePaletteValue($declaration[1]);
        $expected = normalizePaletteValue($entry['oklch']);

        expect($actual)->toBe($expected, "Token {$token} changed in app.css: PDF mirror expects {$expected}, app.css has {$actual}. Update resources/views/pdf/{$view}.blade.php.");
    }
})->with('pdf palette templates');

it('lists the sRGB-clipped hex of each mirrored OKLCH value', function (string $view): void {
    foreach (pdfPaletteSources($view)['mirror'] as $token => $entry) {
        $computed = clippedHexFromOklch($entry['oklch']);

        expect($entry['hex'])->toBe($computed, "Token {$token}: listed hex {$entry['hex']}, computed hex {$computed} from {$entry['oklch']}.");
    }
})->with('pdf palette templates');

it('uses only listed hexes and no functional or named colours in the template', function (string $view): void {
    $sources = pdfPaletteSources($view);
    $listed = array_column($sources['mirror'], 'hex');
    $body = $sources['templateWithoutMirror'];

    preg_match_all('/#[0-9a-fA-F]{3,8}\b/', $body, $hexes);
    foreach ($hexes[0] as $hex) {
        expect(in_array(strtolower($hex), $listed, true))->toBeTrue("Template uses unlisted hex {$hex}; add the token to the mirror comment or use a listed hex.");
    }

    expect(preg_match('/\b(rgba?|hsla?|oklch)\s*\(/i', $body, $functional))->toBe(0, 'Template uses a functional colour: '.($functional[0] ?? ''));
    expect(preg_match('/:\s*[^;{}"]*\b(white|black|gray|grey)\b/i', $body, $named))->toBe(0, 'Template uses a named colour: '.($named[0] ?? ''));
})->with('pdf palette templates');

it('uses every listed hex at least once', function (string $view): void {
    $sources = pdfPaletteSources($view);
    $body = strtolower($sources['templateWithoutMirror']);

    foreach ($sources['mirror'] as $token => $entry) {
        expect(str_contains($body, $entry['hex']))->toBeTrue("Token {$token}: listed hex {$entry['hex']} is not used by any rule (stale entry).");
    }
})->with('pdf palette templates');

it('has a colour rule in the template for every meal category', function (string $view): void {
    $body = pdfPaletteSources($view)['templateWithoutMirror'];

    foreach (MealCategory::values() as $value) {
        expect(preg_match('/td\.meal-'.preg_quote($value, '/').'\s*\{[^}]*background:\s*#[0-9a-f]{6}[^}]*color:\s*#[0-9a-f]{6}/i', $body))
            ->toBe(1, "Meal category {$value} has no td.meal-{$value} colour rule in resources/views/pdf/{$view}.blade.php.");
    }
})->with('pdf palette templates');
