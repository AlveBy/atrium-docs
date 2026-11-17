<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Rendered;
use Atrium\Docs\Renderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Markdown of a page turned into its title, its HTML and its outline,
 * without the links between translations the site replaces.
 */
final class RendererTest extends TestCase
{
    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function titles(): array
    {
        return [
            'the first heading of level one' => ["# Guide\n\nText.", 'Guide'],
            'a level two heading before it does not count' => ["## Part\n\n# Guide\n\nText.", 'Guide'],
            'spaces around it are dropped' => ['#    Guide   ', 'Guide'],
            'no heading of level one' => ["## Part\n\nText.", ''],
            'a heading inside a quote is not the title' => ["> # Quoted\n\nText.", ''],
            'nothing' => ['', ''],
        ];
    }

    #[DataProvider('titles')]
    public function test_the_title_is_the_first_heading_of_level_one(string $markdown, string $title): void
    {
        $rendered = $this->render($markdown);

        $this->assertSame($title, $rendered->title);

        if ($title !== '') {
            // Taken out of the page: the layout prints it once.
            $this->assertStringNotContainsString('<h1>', $rendered->html);
        }
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function languageLinks(): array
    {
        return [
            'the current language and a link' => ['**English** · [Русский](guide.ru.md)', true],
            'a link with an anchor' => ['**English** · [Русский](guide.ru.md#start)', true],
            'two links' => ['[English](guide.md) · [Русский](guide.ru.md)', true],
            'a link to a page of the site' => ['**English** · [Русский](https://example.com/ru)', false],
            'a link to a picture' => ['**English** · [Русский](guide.png)', false],
            'words between the links' => ['**English** or [Русский](guide.ru.md)', false],
            'an emphasis instead of bold' => ['*English* · [Русский](guide.ru.md)', false],
            'one link alone' => ['[Русский](guide.ru.md)', false],
            'bold words without a link' => ['**English** · **Русский** · **Deutsch**', false],
        ];
    }

    #[DataProvider('languageLinks')]
    public function test_links_between_translations_make_way_for_the_language_switch(string $line, bool $dropped): void
    {
        $html = $this->render("# Guide\n\n{$line}\n\nThe text.")->html;

        $this->assertStringContainsString('<p>The text.</p>', $html);
        $this->assertSame(! $dropped, str_contains($html, 'Русский') || str_contains($html, 'English'));
    }

    public function test_only_the_first_three_blocks_are_searched_for_language_links(): void
    {
        $third = $this->render("# Guide\n\nOne.\n\nTwo.\n\n**English** · [Русский](guide.ru.md)\n\nEnd.")->html;
        $fourth = $this->render("# Guide\n\nOne.\n\nTwo.\n\nThree.\n\n**English** · [Русский](guide.ru.md)\n\nEnd.")->html;

        $this->assertStringNotContainsString('Русский', $third);
        $this->assertStringContainsString('<p>Two.</p>', $third);
        $this->assertStringContainsString('Русский', $fourth);
        $this->assertStringContainsString('<p>Three.</p>', $fourth);
    }

    public function test_only_the_first_language_line_is_dropped(): void
    {
        $html = $this->render("# Guide\n\n**English** · [Русский](guide.ru.md)\n\n**English** · [Deutsch](guide.de.md)")->html;

        $this->assertStringNotContainsString('Русский', $html);
        $this->assertStringContainsString('Deutsch', $html);
    }

    public function test_every_link_and_picture_goes_through_the_resolver(): void
    {
        $seen = [];
        $html = (new Renderer)->render("# Guide\n\nSee [the start](start.md) and ![a shot](shot.png).", function (string $url, bool $image) use (&$seen): string {
            $seen[] = [$url, $image];

            return '/resolved/'.$url;
        })->html;

        $this->assertSame([['start.md', false], ['shot.png', true]], $seen);
        $this->assertStringContainsString('href="/resolved/start.md"', $html);
        $this->assertStringContainsString('src="/resolved/shot.png"', $html);
    }

    private function render(string $markdown): Rendered
    {
        return (new Renderer)->render($markdown, fn (string $url): string => $url);
    }
}
