<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Rendered;
use Atrium\Docs\Renderer;
use Atrium\Docs\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What the renderer makes of Markdown, down to the id of a heading, the
 * border of a description and the text the search reads.
 */
final class RendererInternalsTest extends TestCase
{
    private function render(string $markdown): Rendered
    {
        return (new Renderer)->render($markdown, fn (string $url, bool $image): string => $url);
    }

    public function test_a_link_that_runs_a_script_loses_its_address_and_a_web_link_keeps_it(): void
    {
        $html = $this->render('[bad](javascript:alert(1)) [good](https://example.com/page)')->html;

        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringContainsString('<a>bad</a>', $html);
        $this->assertStringContainsString('<a href="https://example.com/page">good</a>', $html);
    }

    public function test_only_the_headings_of_the_second_to_the_fourth_level_get_an_anchor(): void
    {
        $html = $this->render("# Title\n\n# Another\n\n## Two\n\n### Three\n\n#### Four\n\n##### Five")->html;

        $this->assertStringContainsString('<h1>Another</h1>', $html);
        $this->assertStringContainsString('<h5>Five</h5>', $html);
        $this->assertSame(3, substr_count($html, 'class="docs-anchor"'));
        $this->assertStringContainsString('<h2 id="two">Two<a href="#two" class="docs-anchor" aria-hidden="true" tabindex="-1" title="">#</a></h2>', $html);
        $this->assertStringContainsString('<h4 id="four">', $html);
    }

    public function test_headings_with_the_same_words_get_ids_of_their_own(): void
    {
        $html = $this->render("## Same\n\n## Same\n\n## Same")->html;

        $this->assertStringContainsString('<h2 id="same">', $html);
        $this->assertStringContainsString('<h2 id="same-1">', $html);
        $this->assertStringContainsString('<h2 id="same-2">', $html);
    }

    public function test_raw_html_stays_in_the_page(): void
    {
        $this->assertStringContainsString('<span class="note">Hi</span>', $this->render('A <span class="note">Hi</span>')->html);
    }

    public function test_a_wide_table_scrolls_in_a_box_of_its_own(): void
    {
        $html = $this->render("| a | b |\n|---|---|\n| 1 | 2 |")->html;

        $this->assertStringStartsWith('<div class="docs-table" tabindex="0"><table>', $html);
        $this->assertStringEndsWith("</table></div>\n", $html);
        $this->assertSame(1, substr_count($html, '<div class="docs-table"'));
    }

    public function test_the_title_is_the_first_first_level_heading_without_the_blanks_around_it(): void
    {
        $rendered = $this->render("# Title [](x)\n\nText");

        $this->assertSame('Title', $rendered->title);
        $this->assertStringNotContainsString('Title', $rendered->html);
    }

    public function test_the_line_of_language_links_goes_but_a_line_of_other_links_stays(): void
    {
        $gone = $this->render("# Title\n\n**English** · [Русский](guide.ru.md#top)\n\nText")->html;
        $kept = $this->render("# Title\n\n**English** · [Other](guide.mdx)\n\nText")->html;
        $keptToo = $this->render("# Title\n\n**English** · [Other](guide.md.html)\n\nText")->html;

        $this->assertStringNotContainsString('Русский', $gone);
        $this->assertStringContainsString('guide.mdx', $kept);
        $this->assertStringContainsString('guide.md.html', $keptToo);
    }

    public function test_the_outline_is_the_headings_of_the_contents_and_the_text_under_each(): void
    {
        $rendered = $this->render("Intro text\n\n## One\n\nText one\n\n### Two\n\nText <b>two</b>\n\n#### Four\n\nText four");

        $this->assertSame([
            ['level' => 2, 'id' => 'one', 'text' => 'One'],
            ['level' => 3, 'id' => 'two', 'text' => 'Two'],
        ], $rendered->headings);
        $this->assertSame([
            ['id' => '', 'heading' => '', 'text' => 'Intro text'],
            ['id' => 'one', 'heading' => 'One', 'text' => 'Text one'],
            ['id' => 'two', 'heading' => 'Two', 'text' => 'Text two'],
            ['id' => 'four', 'heading' => 'Four', 'text' => 'Text four'],
        ], $rendered->sections);
    }

    public function test_a_page_that_opens_with_a_heading_has_no_empty_intro_and_a_heading_without_text_stays(): void
    {
        $rendered = $this->render("## Empty\n\n## Full\n\nText");

        $this->assertSame([
            ['id' => 'empty', 'heading' => 'Empty', 'text' => ''],
            ['id' => 'full', 'heading' => 'Full', 'text' => 'Text'],
        ], $rendered->sections);
    }

    public function test_a_page_without_headings_is_one_section(): void
    {
        $rendered = $this->render('Just text');

        $this->assertSame([], $rendered->headings);
        $this->assertSame([['id' => '', 'heading' => '', 'text' => 'Just text']], $rendered->sections);
        $this->assertSame([], $this->render('')->sections);
    }

    public function test_the_id_of_a_heading_written_in_html_is_decoded(): void
    {
        $rendered = $this->render("<h2 id=\"a&#039;b\">Raw</h2>\n\nText");

        $this->assertSame([['level' => 2, 'id' => "a'b", 'text' => 'Raw']], $rendered->headings);
        $this->assertSame("a'b", $rendered->sections[0]['id']);
    }

    public function test_the_description_is_the_first_paragraph_in_one_line(): void
    {
        $this->assertSame('Intro', $this->render("Intro\n\nSecond")->description);
        $this->assertSame('Line one line two', $this->render("Line one\nline two\n\nNext paragraph")->description);
        $this->assertSame('A bold & it\'s', $this->render('A **bold** &amp; it&#039;s')->description);
        $this->assertSame('', $this->render("# Only a title\n\n- a list")->description);
    }

    public function test_the_description_stops_at_one_hundred_and_sixty_characters(): void
    {
        $exact = str_repeat('ab ', 52).'abcd';
        $over = str_repeat('ab ', 52).'abcde';

        $this->assertSame(160, strlen($exact));
        $this->assertSame($exact, $this->render($exact)->description);
        $this->assertSame(str_repeat('ab ', 52).'a…', $this->render($over)->description);
    }

    public function test_the_description_counts_letters_not_bytes(): void
    {
        $exact = str_repeat('я', 160);
        $over = str_repeat('я', 161);

        $this->assertSame($exact, $this->render($exact)->description);
        $this->assertSame(str_repeat('я', 157).'…', $this->render($over)->description);
    }

    public function test_the_description_loses_a_blank_before_the_dots(): void
    {
        $text = str_repeat('a', 156).' bbbb';

        $this->assertSame(str_repeat('a', 156).'…', $this->render($text)->description);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function plains(): array
    {
        return [
            'tags go' => ['<p>One</p><p>Two</p>', 'One Two'],
            'line ends become a blank' => ['a<br>b<br/>c', 'a b c'],
            'items, rows and blocks become a blank' => ['<li>a</li><tr>b</tr><pre>c</pre><div>d</div>', 'a b c d'],
            'headings become a blank' => ['<h2>One</h2>Two', 'One Two'],
            'a tag that is no break joins' => ['a<b>b</b>c', 'abc'],
            'the anchor of a heading goes' => ['<h2 id="x">Title<a href="#x" class="docs-anchor">#</a></h2>', 'Title'],
            'a link that is no anchor stays' => ['<a href="#x">name</a>', 'name'],
            'entities become signs' => ['It&#039;s &amp; &quot;fine&quot; &lt;ok&gt;', 'It\'s & "fine" <ok>'],
            'blanks are one and trimmed' => ["  a \n\n b\t c  ", 'a b c'],
            'a no-break space is a blank' => ["a\u{00A0}b", 'a b'],
        ];
    }

    #[DataProvider('plains')]
    public function test_plain_text_is_what_the_page_says(string $html, string $text): void
    {
        $this->assertSame($text, Renderer::plain($html));
    }
}
