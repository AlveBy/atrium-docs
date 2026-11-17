<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Docs\Docs;
use Atrium\Docs\Renderer;
use Atrium\Docs\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;

/**
 * The corners of the docs service: the order of the guides, a snippet of
 * letters, a link that looks like a scheme, the highlight theme and the
 * language of a file.
 */
final class DocsInternalsTest extends TestCase
{
    public function test_the_guides_of_the_config_come_first_in_its_order_and_the_rest_by_name(): void
    {
        config(['atrium-docs.order' => ['zeta', 'alpha']]);
        $docs = $this->docsOf([
            'docs/alpha.md' => '# A',
            'docs/beta.md' => '# B',
            'docs/zeta.md' => '# Z',
            'docs/omega.md' => '# O',
        ]);
        $version = $docs->version('main');
        $this->assertNotNull($version);

        $this->assertSame(['zeta', 'alpha', 'beta', 'omega'], array_keys($docs->pages($version)));
    }

    public function test_a_snippet_finds_a_capital_word_deep_in_the_text_and_counts_letters_for_its_end(): void
    {
        $method = new ReflectionMethod(Docs::class, 'snippet');

        $this->assertSame(
            '…'.str_repeat('я', 59).' <mark>МИР</mark> '.str_repeat('ю', 136).'…',
            $method->invoke(null, str_repeat('я', 100).' МИР '.str_repeat('ю', 300), ['мир']),
        );
        $this->assertSame(str_repeat('я', 150), $method->invoke(null, str_repeat('я', 150), ['zz']));
    }

    public function test_a_relative_link_with_a_colon_further_in_is_not_taken_for_an_address(): void
    {
        $docs = $this->docsOf(['docs/guide.md' => '# Guide', 'docs/a/b:c.md' => '# Odd']);
        $version = $docs->version('main');
        $this->assertNotNull($version);
        $resolve = new ReflectionMethod(Docs::class, 'resolve');

        $this->assertSame(
            'https://github.com/acme/acme/blob/main/docs/a/b:c.md',
            $resolve->invoke($docs, $version, 'docs/guide.md', 'a/b:c.md', false, 'en'),
        );
        $this->assertSame('x:y', $resolve->invoke($docs, $version, 'docs/guide.md', 'x:y', false, 'en'));
    }

    /**
     * @return array<string,array{array<string,mixed>,string|array<string,string>}>
     */
    public static function themes(): array
    {
        return [
            'nothing configured' => [[], 'atrium-light'],
            'a light one' => [['light' => 'github-light'], 'github-light'],
            'a light and a dark one' => [['light' => 'github-light', 'dark' => 'github-dark'], ['light' => 'github-light', 'dark' => 'github-dark']],
            'a dark one only' => [['dark' => 'github-dark'], ['light' => 'atrium-light', 'dark' => 'github-dark']],
            'an empty dark one' => [['light' => 'github-light', 'dark' => ''], 'github-light'],
            'no dark one' => [['light' => 'github-light', 'dark' => null], 'github-light'],
            'a dark one that is no name' => [['light' => 'github-light', 'dark' => 5], 'github-light'],
            'a light one that is a number' => [['light' => 5], '5'],
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  string|array<string,string>  $expected
     */
    #[DataProvider('themes')]
    public function test_the_highlight_theme_is_read_from_the_config(array $config, string|array $expected): void
    {
        config(['atrium-docs.highlight' => $config]);
        $docs = $this->app->make(Docs::class);
        $renderer = (new ReflectionMethod(Docs::class, 'renderer'))->invoke($docs);

        $this->assertSame($expected, (new ReflectionProperty(Renderer::class, 'theme'))->getValue($renderer));
    }

    public function test_the_language_of_a_file_is_the_spelling_of_the_site(): void
    {
        Language::query()->create(['code' => 'pt-BR', 'name' => 'Português', 'sort' => 2]);
        $this->app->make(ContentLanguages::class)->flush();
        $docs = $this->app->make(Docs::class);
        $content = new ReflectionMethod(Docs::class, 'contentCode');

        $this->assertSame('pt-BR', $content->invoke($docs, 'pt-br'));
        $this->assertSame('xx-yy', $content->invoke($docs, 'XX_yy'));

        app()->setLocale('pt-br');
        $this->assertSame('pt-BR', $content->invoke($docs, null));
    }
}
