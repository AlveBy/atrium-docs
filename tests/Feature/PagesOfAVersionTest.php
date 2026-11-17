<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Page;
use Atrium\Docs\Tests\TestCase;
use Atrium\Docs\Version;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which files of a version become pages, what they are called and where
 * their links lead.
 */
final class PagesOfAVersionTest extends TestCase
{
    public function test_only_markdown_files_with_a_page_name_become_guides(): void
    {
        $this->write('docs/extra.md', "# Extra\n\nText.");
        $this->write('docs/two words.md', "# Two words\n\nText.");
        $this->write('docs/bad.name.md', "# Bad name\n\nText.");
        mkdir($this->storage.'/v1.0.0/docs/folder.md');

        $slugs = array_keys($this->docs()->pages($this->version()));

        $this->assertContains('extra', $slugs);
        $this->assertNotContains('two words', $slugs);
        $this->assertNotContains('two-words', $slugs);
        $this->assertNotContains('bad', $slugs);
        $this->assertNotContains('bad.name', $slugs);
        $this->assertNotContains('folder', $slugs);
    }

    public function test_a_page_lists_the_languages_it_is_written_in(): void
    {
        $guide = $this->docs()->page($this->version(), 'guide');

        $this->assertNotNull($guide);
        $this->assertSame(['en', 'ru'], $guide->languages());
        $this->assertSame(['en'], (new Page('about', Page::GUIDE, ['en' => 'docs/about.md']))->languages());
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function titles(): array
    {
        return [
            'a heading with #' => ["# Hash title\n\nText.", 'Hash title'],
            // Not seen by the quick reader: the page is rendered for it.
            'an underlined heading' => ["Underlined title\n================\n\nText.", 'Underlined title'],
            'no heading: the name of the page' => ["Just text.\n\n## A section", 'extra'],
        ];
    }

    #[DataProvider('titles')]
    public function test_the_title_of_a_page(string $markdown, string $title): void
    {
        $this->write('docs/extra.md', $markdown);
        $version = $this->version();
        $page = $this->docs()->page($version, 'extra');
        $this->assertNotNull($page);

        $this->assertSame($title, $this->docs()->title($version, $page, 'en'));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function shortQueries(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'one letter' => ['a'],
            'single letters' => ['a b c'],
        ];
    }

    #[DataProvider('shortQueries')]
    public function test_words_shorter_than_two_letters_are_not_searched(string $query): void
    {
        $this->write('docs/extra.md', "# Extra\n\na b c a a.");

        $this->assertSame([], $this->docs()->search($this->version(), 'en', $query));
    }

    public function test_a_word_of_two_letters_is_searched(): void
    {
        $this->write('docs/extra.md', "# Extra\n\nQx marks the spot.");

        $results = $this->docs()->search($this->version(), 'en', 'qx z');

        $this->assertSame(['extra'], array_column($results, 'page'));
        $this->assertSame('<mark>Qx</mark> marks the spot.', $results[0]['snippet']);
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function links(): array
    {
        return [
            'out of the repository' => ['../../outside.md', '../../outside.md'],
            'out of the repository, with an anchor' => ['../../outside.md#part', '../../outside.md#part'],
            'out of the repository from the root' => ['../../../etc/passwd', '../../../etc/passwd'],
            'a file of the repository' => ['../LICENSE', 'https://github.com/acme/acme/blob/v1.0.0/LICENSE'],
            'a file of the repository, with an anchor' => ['../LICENSE#mit', 'https://github.com/acme/acme/blob/v1.0.0/LICENSE#mit'],
        ];
    }

    #[DataProvider('links')]
    public function test_a_link_that_leaves_the_repository_is_left_as_written(string $link, string $href): void
    {
        $this->write('docs/extra.md', "# Extra\n\nSee [the file]({$link}).");
        $version = $this->version();
        $page = $this->docs()->page($version, 'extra');
        $this->assertNotNull($page);

        $this->assertStringContainsString('<a href="'.e($href).'">the file</a>', $this->docs()->render($version, $page, 'en')->html);
    }

    private function write(string $file, string $markdown): void
    {
        if (! is_dir($this->storage.'/v1.0.0')) {
            $this->release('v1.0.0', 'The guide');
        }

        file_put_contents($this->storage.'/v1.0.0/'.$file, $markdown);
    }

    private function version(): Version
    {
        if (! is_dir($this->storage.'/v1.0.0')) {
            $this->release('v1.0.0', 'The guide');
        }

        $version = $this->docs()->version('v1.0.0');
        $this->assertNotNull($version);

        return $version;
    }

    private function docs(): Docs
    {
        return $this->app->make(Docs::class);
    }
}
