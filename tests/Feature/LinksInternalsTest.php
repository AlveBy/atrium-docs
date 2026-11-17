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
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * Where the links and the pictures of a page lead, and which addresses
 * the page keeps for itself.
 */
final class LinksInternalsTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n";

    private Docs $docs;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languages();
        $this->docs = $this->docsOf([
            'README.md' => "# Start\n\nWelcome.\n",
            'docs/guide.md' => "# Guide\n\nText.\n",
            'docs/other.md' => "# Other\n\nText.\n",
            'docs/other.ru.md' => "# Другое\n\nТекст.\n",
            'docs/my file.md' => "# Spaced\n",
            'docs/img/pic.png' => self::PNG,
            'docs/img/UP.PNG' => self::PNG,
            'packages/faq/README.md' => "# Faq\n",
        ]);
        $this->version = $this->docs->version('main') ?? $this->fail('No version');
    }

    private function resolve(string $url, bool $image = false, string $language = 'en', string $from = 'docs/guide.md', ?Version $version = null): string
    {
        $method = new ReflectionMethod(Docs::class, 'resolve');

        return $method->invoke($this->docs, $version ?? $this->version, $from, $url, $image, $language);
    }

    /**
     * @return array<string,array{string}>
     */
    public static function untouched(): array
    {
        return [
            'nothing' => [''],
            'an anchor' => ['#top'],
            'a site' => ['https://example.test/a.md'],
            'a site in capitals' => ['HTTPS://example.test/a.md'],
            'a mailbox' => ['mailto:me@example.test'],
            'a scheme with signs' => ['git+ssh://example.test/a'],
            'the scheme of the page' => ['//cdn.example.test/a.png'],
        ];
    }

    #[DataProvider('untouched')]
    public function test_a_link_that_leaves_the_repository_stays_as_it_is(string $url): void
    {
        $this->assertSame($url, $this->resolve($url));
        $this->assertSame($url, $this->resolve($url, true));
    }

    public function test_a_link_to_a_page_leads_to_the_page_in_the_language_of_the_reader(): void
    {
        $this->assertSame('http://localhost/docs/main/other', $this->resolve('other.md'));
        $this->assertSame('http://localhost/docs/main/other#part', $this->resolve('other.md#part'));
        $this->assertSame('http://localhost/ru/docs/main/other', $this->resolve('other.md', false, 'ru'));
        $this->assertSame('http://localhost/docs/main/other', $this->resolve('../docs/other.md'));
        $this->assertSame('http://localhost/docs/main/guide', $this->resolve('./guide.md'));
        $this->assertSame('http://localhost/docs/main/modules/faq', $this->resolve('../packages/faq/README.md'));
    }

    public function test_a_link_to_a_translation_leads_to_that_language(): void
    {
        $this->assertSame('http://localhost/ru/docs/main/other', $this->resolve('other.ru.md'));
        $this->assertSame('http://localhost/ru/docs/main/other#x', $this->resolve('other.ru.md#x'));
        $this->assertSame('http://localhost/ru/docs/main/other', $this->resolve('other.ru.md', false, 'de'));
    }

    public function test_the_start_page_is_a_page_too(): void
    {
        $this->assertSame('http://localhost/docs/main', $this->resolve('../README.md'));
    }

    public function test_a_link_to_a_file_outside_the_docs_leads_to_github(): void
    {
        $this->assertSame('https://github.com/acme/acme/blob/main/src/Code.php', $this->resolve('../src/Code.php'));
        $this->assertSame('https://github.com/acme/acme/blob/main/src/Code.php#top', $this->resolve('../src/Code.php#top'));
        $this->assertSame('https://github.com/acme/acme/blob/main/docs/missing.md', $this->resolve('missing.md'));
        $this->assertSame('https://github.com/acme/acme/blob/main/docs/my file.md', $this->resolve('my%20file.md'));
        $this->assertSame('https://github.com/acme/acme/blob/main/src/Code.php', $this->resolve('../src/Code.php', false, 'en'));
    }

    public function test_a_link_of_a_page_in_a_folder_is_read_from_the_folder(): void
    {
        $this->assertSame('https://github.com/acme/acme/blob/main/packages/faq/src/Code.php', $this->resolve('src/Code.php', false, 'en', 'packages/faq/README.md'));
        $this->assertSame('http://localhost/docs/main/other', $this->resolve('../../docs/other.md', false, 'en', 'packages/faq/README.md'));
    }

    public function test_github_is_the_repository_without_a_trailing_slash_and_the_branch(): void
    {
        config(['atrium-docs.repository' => 'https://git.example.test/repo/', 'atrium-docs.branch' => 'dev']);

        $this->assertSame('https://git.example.test/repo/blob/dev/src/Code.php', $this->resolve('../src/Code.php'));
    }

    public function test_a_release_leads_to_its_own_tag_on_github(): void
    {
        config(['atrium-docs.versions' => ['main' => $this->version->path, 'v1.2.3' => $this->version->path]]);
        $this->app->forgetInstance(Docs::class);
        $this->docs = $this->app->make(Docs::class);
        $release = $this->docs->version('v1.2.3') ?? $this->fail('No release');
        config(['atrium-docs.branch' => 'dev']);

        $this->assertSame('https://github.com/acme/acme/blob/v1.2.3/src/Code.php', $this->resolve('../src/Code.php', false, 'en', 'docs/guide.md', $release));
        $this->assertSame('https://github.com/acme/acme/blob/dev/src/Code.php', $this->resolve('../src/Code.php'));
    }

    public function test_a_picture_is_served_by_the_site(): void
    {
        $asset = 'http://localhost/docs/main/_assets/docs/img/pic.png';

        $this->assertSame($asset, $this->resolve('img/pic.png', true));
        $this->assertSame($asset, $this->resolve('img/pic.png#frag', true));
        $this->assertSame($asset, $this->resolve('img/pic.png'));
        $this->assertSame('http://localhost/docs/main/_assets/docs/img/UP.PNG', $this->resolve('img/UP.PNG', true));
    }

    public function test_a_missing_picture_and_a_page_as_a_picture_lead_to_github(): void
    {
        $this->assertSame('https://github.com/acme/acme/blob/main/docs/img/none.png', $this->resolve('img/none.png', true));
        $this->assertSame('https://github.com/acme/acme/blob/main/docs/other.md', $this->resolve('other.md', true));
    }

    public function test_a_path_that_leaves_the_repository_stays_as_written(): void
    {
        $this->assertSame('../../../x.md', $this->resolve('../../../x.md'));
        $this->assertSame('../../../x.md#a', $this->resolve('../../../x.md#a'));
        $this->assertSame('../../../x.png#a', $this->resolve('../../../x.png#a', true));
    }

    public function test_the_edit_and_translate_links_go_to_github(): void
    {
        $this->assertSame('https://github.com/acme/acme/edit/main/docs/guide.md', $this->docs->editUrl('docs/guide.md'));

        config(['atrium-docs.repository' => 'https://git.example.test/repo/', 'atrium-docs.branch' => 'dev']);
        $this->assertSame('https://git.example.test/repo/edit/dev/docs/guide.md', $this->docs->editUrl('docs/guide.md'));

        $guide = $this->docs->page($this->version, 'guide') ?? $this->fail('No page');
        $faq = $this->docs->page($this->version, 'modules/faq') ?? $this->fail('No page');
        $start = $this->docs->page($this->version, '') ?? $this->fail('No page');

        $this->assertSame('https://git.example.test/repo/new/dev/docs?filename=guide.pt-BR.md', $this->docs->translateUrl($guide, 'pt-br'));
        $this->assertSame('https://git.example.test/repo/new/dev/packages/faq?filename=README.de.md', $this->docs->translateUrl($faq, 'de'));
        $this->assertSame('https://git.example.test/repo/new/dev/docs/readme?filename=README.de.md', $this->docs->translateUrl($start, 'de'));

        config(['atrium-docs.repository' => 'https://github.com/acme/acme']);
        $this->assertSame('https://github.com/acme/acme/new/dev/docs?filename=guide.de.md', $this->docs->translateUrl($guide, 'de'));
    }

    public function test_the_addresses_of_the_pages_and_of_the_search(): void
    {
        $this->assertSame('http://localhost/docs/main/guide', $this->docs->url($this->version, '/guide/', 'en'));
        $this->assertSame('http://localhost/ru/docs/main/guide', $this->docs->url($this->version, 'guide', 'ru'));
        $this->assertSame('http://localhost/docs/main/search', $this->docs->searchUrl($this->version, 'en'));
        $this->assertSame('http://localhost/ru/docs/main/search', $this->docs->searchUrl($this->version, 'ru'));
        $this->assertSame('http://localhost/docs/main/guide', $this->docs->url($this->version, 'guide'));
        $this->assertSame('http://localhost/docs/main/search', $this->docs->searchUrl($this->version));
    }

    public function test_a_language_that_is_a_variant_of_a_site_language_is_the_site_language(): void
    {
        $this->assertSame('http://localhost/ru/docs/main/guide', $this->docs->url($this->version, 'guide', 'RU'));
        $this->assertSame('http://localhost/xx/docs/main/guide', $this->docs->url($this->version, 'guide', 'xx'));
    }

    private function page(string $slug): Page
    {
        return $this->docs->page($this->version, $slug) ?? $this->fail('No page '.$slug);
    }

    public function test_a_rendered_page_is_kept_for_the_address_of_the_site(): void
    {
        $this->docs = $this->docsOf([
            'docs/guide.md' => "# Guide\n\n![Picture](img/pic.png)\n",
            'docs/img/pic.png' => self::PNG,
        ]);
        $this->version = $this->docs->version('main') ?? $this->fail('No version');
        $guide = $this->page('guide');

        URL::forceRootUrl('http://first.example.test');
        $first = $this->docs->render($this->version, $guide, 'en')->html;
        URL::forceRootUrl('http://second.example.test');
        $second = $this->docs->render($this->version, $guide, 'en')->html;
        URL::forceRootUrl(null);

        $this->assertStringContainsString('http://first.example.test/', $first);
        $this->assertStringContainsString('http://second.example.test/', $second);
    }

    public function test_a_rendered_page_is_kept_for_the_language(): void
    {
        $this->docs = $this->docsOf([
            'docs/guide.md' => "# Guide\n\n[Other](other.md)\n",
            'docs/other.md' => "# Other\n",
        ]);
        $this->version = $this->docs->version('main') ?? $this->fail('No version');
        $guide = $this->page('guide');

        $english = $this->docs->render($this->version, $guide, 'en')->html;
        $russian = $this->docs->render($this->version, $guide, 'ru')->html;
        $german = $this->docs->render($this->version, $guide, 'de')->html;

        $this->assertStringContainsString('http://localhost/docs/main/other', $english);
        $this->assertStringContainsString('http://localhost/ru/docs/main/other', $russian);
        $this->assertStringContainsString('http://localhost/de/docs/main/other', $german);
    }

    public function test_a_rendered_page_is_kept_for_the_file(): void
    {
        $this->docs = $this->docsOf([
            'docs/guide.md' => "# Guide\n",
            'docs/other.md' => "# Other\n",
        ]);
        $this->version = $this->docs->version('main') ?? $this->fail('No version');

        $this->assertSame('Guide', $this->docs->render($this->version, $this->page('guide'), 'en')->title);
        $this->assertSame('Other', $this->docs->render($this->version, $this->page('other'), 'en')->title);
    }

    public function test_a_rendered_page_follows_the_file(): void
    {
        $this->docs = $this->docsOf(['docs/guide.md' => "# Guide\n"]);
        $this->version = $this->docs->version('main') ?? $this->fail('No version');
        $this->assertSame('Guide', $this->docs->render($this->version, $this->page('guide'), 'en')->title);

        file_put_contents($this->version->file('docs/guide.md'), "# Changed guide\n");
        clearstatcache();

        $this->assertSame('Changed guide', $this->docs->render($this->version, $this->page('guide'), 'en')->title);
    }

    /**
     * @return array<string,array{string,?string}>
     */
    public static function headings(): array
    {
        return [
            'a heading' => ["# Title\n\ntext", 'Title'],
            'no heading' => ["text only\n## Sub\n", null],
            'a heading of a tab' => ["#\tTabbed", 'Tabbed'],
            'no space after the sign' => ['#Title', null],
            'indented by three spaces' => ['   # Three', 'Three'],
            'indented by four spaces' => ['    # Four', null],
            'closing signs' => ['# Closed ##', 'Closed'],
            'a sign inside' => ['# C# language', 'C# language'],
            'spaces around' => ["# Spaced   \t", 'Spaced'],
            'after a code block' => ["```\n# Not\n```\n# Real", 'Real'],
            'after a tilde block' => ["~~~\n# Not\n~~~\n# Real", 'Real'],
            'inside an unclosed block' => ["```\n# Not\n", null],
            'a block indented by three spaces' => ["   ```\n# Not\n   ```\n# Real", 'Real'],
            'a block indented by four spaces is no block' => ["    ```\n# Real", 'Real'],
            'carriage returns' => ["intro\r\n# Win\r\ntext", 'Win'],
            'the first of two' => ["# First\n# Second", 'First'],
            'emphasis' => ['# **Bold** and *italic*, __under__ and `code`', 'Bold and italic, under and code'],
            'a link' => ['# See [the docs](https://example.test/a) now', 'See the docs now'],
            'a picture' => ['# ![Logo](logo.png) Atrium', 'Logo Atrium'],
            'a link and a picture' => ['# [One](a) ![Two](b)', 'One Two'],
            'a tag' => ['# <b>Bold</b> text', 'Bold text'],
            'entities' => ['# Fish &amp; Chips &quot;q&quot; &apos;a&apos; &lt;', 'Fish & Chips "q" \'a\' <'],
            'markup left at the edge' => ['# **', ''],
            'markup around' => ['# * Edge *', 'Edge'],
        ];
    }

    #[DataProvider('headings')]
    public function test_the_title_is_the_first_heading_outside_the_code(string $markdown, ?string $expected): void
    {
        $this->assertSame($expected, Docs::heading($markdown));
    }
}
