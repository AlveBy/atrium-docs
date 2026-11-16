<?php

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every colour of the highlighting themes keeps WCAG AA (4.5:1) on the
 * background of the code block.
 */
final class HighlightContrastTest extends TestCase
{
    /**
     * @return iterable<string,array{string}>
     */
    public static function themes(): iterable
    {
        yield 'light' => ['atrium-light'];
        yield 'dark' => ['atrium-dark'];
    }

    #[DataProvider('themes')]
    public function test_every_token_colour_reaches_4_5_to_1(string $name): void
    {
        $theme = json_decode((string) file_get_contents(__DIR__.'/../../resources/themes/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
        $background = $theme['colors']['editor.background'];
        $colours = [$theme['colors']['editor.foreground']];

        foreach ($theme['tokenColors'] as $token) {
            $this->assertArrayNotHasKey('background', $token['settings'], 'A token with a background of its own');

            if (isset($token['settings']['foreground'])) {
                $colours[] = $token['settings']['foreground'];
            }
        }

        foreach (array_unique($colours) as $colour) {
            $this->assertGreaterThanOrEqual(4.5, self::ratio($colour, $background), "{$colour} on {$background} in {$name}");
        }
    }

    private static function ratio(string $a, string $b): float
    {
        [$x, $y] = [self::luminance($a), self::luminance($b)];

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $hex = strlen($hex) === 3 ? (string) preg_replace('/(.)/', '$1$1', $hex) : substr($hex, 0, 6);
        $channels = array_map(static function (int $value): float {
            $value /= 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
