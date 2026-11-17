<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * How the sections are ranked, how many come back and what the snippet
 * of each shows.
 */
final class SearchInternalsTest extends TestCase
{
    private Docs $docs;

    protected function setUp(): void
    {
        parent::setUp();

        $page = static fn (string $title, string $heading, string $text): string => "# {$title}\n\nIntro.\n\n## {$heading}\n\n{$text}\n";
        $files = [
            'docs/a1.md' => $page('Page a1', 'Four', 'zz zz zz zz'),
            'docs/a2.md' => $page('Page a2', 'Five-a', 'zz zz zz zz zz'),
            'docs/a3.md' => $page('Page a3', 'Five-b', 'zz zz zz zz zz'),
            'docs/a4.md' => $page('Page a4', 'Six', 'zz zz zz zz zz zz'),
            'docs/a5.md' => $page('Page a5', 'Has zz', 'nothing here'),
            'docs/b1.md' => $page('Page b1', 'Z1', 'qq qq qq qq qq rr rr rr rr rr ss ss ss ss ss'),
            'docs/b2.md' => $page('Page b2', 'Z2 qq', 'rr rr rr ss ss'),
            'docs/b3.md' => $page('Page b3', 'Z3', 'qq qq qq qq qq rr rr rr rr rr ss'),
            'docs/b4.md' => $page('Page b4', 'Z4 qq', 'rr ss'),
            'docs/b5.md' => $page('Page b5', 'Z5 qq rr ss', 'qq rr ss'),
            'docs/d1.md' => $page('Заголовок', 'Раздел', 'ПРИВЕТ мир я'),
            'docs/d2.md' => $page('Страница', 'Привет', 'текст'),
            'docs/e1.md' => $page('Pre', 'fix', 'plain'),
            'docs/e2.md' => $page('Zebra', 'Stripes', 'plain'),
            'docs/tr.md' => $page('Translated', 'Original', 'plain'),
            'docs/tr.ru.md' => $page('Переведено', 'Оригинал', 'ww'),
        ];

        foreach (range(1, 25) as $number) {
            $files[sprintf('docs/c%02d.md', $number)] = $page('Page c'.$number, 'Common '.$number, 'vv');
        }

        $this->docs = $this->docsOf($files);
    }

    /**
     * @return array<int,string>
     */
    private function headings(string $query, string $language = 'en', ?int $limit = null): array
    {
        $version = $this->docs->version('main');
        $this->assertNotNull($version);
        $results = $limit === null ? $this->docs->search($version, $language, $query) : $this->docs->search($version, $language, $query, $limit);

        return array_column($results, 'heading');
    }

    public function test_a_word_in_the_text_counts_up_to_five_times(): void
    {
        $this->assertSame(['Has zz', 'Five-a', 'Five-b', 'Six', 'Four'], $this->headings('zz'));
    }

    public function test_a_word_in_a_heading_weighs_ten_texts_and_a_word_in_both_still_counts(): void
    {
        $this->assertSame(['Z5 qq rr ss', 'Z1', 'Z2 qq', 'Z4 qq', 'Z3'], $this->headings('qq rr ss'));
    }

    public function test_a_result_carries_no_score(): void
    {
        $version = $this->docs->version('main');
        $this->assertNotNull($version);

        $this->assertSame(['page', 'title', 'heading', 'url', 'snippet'], array_keys($this->docs->search($version, 'en', 'zz')[0]));
    }

    public function test_twenty_results_come_back_unless_the_limit_says_otherwise(): void
    {
        $this->assertCount(20, $this->headings('vv'));
        $this->assertCount(3, $this->headings('vv', 'en', 3));
        $this->assertSame(['Common 1', 'Common 2'], $this->headings('vv', 'en', 2));
        $this->assertCount(25, $this->headings('vv', 'en', 30));
    }

    public function test_a_query_is_read_in_any_case_with_spaces_around_and_words_twice(): void
    {
        $this->assertSame($this->headings('zz'), $this->headings("  ZZ \n zz  "));
        $this->assertSame([], $this->headings('z'));
        $this->assertSame([], $this->headings('   '));

        $version = $this->docs->version('main');
        $this->assertNotNull($version);
        $results = $this->docs->search($version, 'en', 'zz zz');
        $six = array_values(array_filter($results, fn (array $result): bool => $result['heading'] === 'Six'))[0];

        $this->assertSame(str_repeat('<mark>zz</mark> ', 5).'<mark>zz</mark>', $six['snippet']);
    }

    public function test_a_word_of_one_letter_is_too_short_in_any_alphabet(): void
    {
        $this->assertSame([], $this->headings('я'));
        $this->assertSame(['Раздел'], $this->headings('мир'));
    }

    public function test_the_index_is_kept_under_the_name_of_the_docs(): void
    {
        $version = $this->docs->version('main');
        $this->assertNotNull($version);
        $this->docs->search($version, 'en', 'zz');

        $this->assertTrue(Cache::has('atrium-docs:index:'.md5(implode('|', [$version->fingerprint(), 'en']))));
    }

    public function test_capitals_of_any_alphabet_are_found(): void
    {
        $this->assertSame(['Привет', 'Раздел'], $this->headings('ПРИВЕТ'));
        $this->assertSame(['Привет', 'Раздел'], $this->headings('привет'));
    }

    public function test_the_title_and_the_heading_are_searched_apart(): void
    {
        $this->assertSame([], $this->headings('prefix'));
        $this->assertSame(['fix'], $this->headings('fix'));
        // The title is in the first section (the text before any heading) and in the section below it.
        $this->assertSame(['', 'Stripes'], $this->headings('zebra'));
    }

    public function test_the_index_is_kept_per_language(): void
    {
        $this->languages();
        $version = $this->docs->version('main');
        $this->assertNotNull($version);

        $this->assertSame(['Оригинал'], array_column($this->docs->search($version, 'ru', 'ww'), 'heading'));
        $this->assertSame([], $this->docs->search($version, 'en', 'ww'));
        $this->assertSame(['Оригинал'], array_column($this->docs->search($version, 'ru', 'ww'), 'heading'));
    }

    public function test_the_index_follows_the_files(): void
    {
        $version = $this->docs->version('main');
        $this->assertNotNull($version);
        $this->assertCount(5, $this->docs->search($version, 'en', 'zz'));

        $file = $version->file('docs/a5.md');
        sleep(1);
        file_put_contents($file, "# Page a5\n\n## Has no more\n\nnothing here at all\n");
        clearstatcache();
        $this->app->forgetInstance(Docs::class);
        $fresh = $this->app->make(Docs::class);
        $version = $fresh->version('main');
        $this->assertNotNull($version);

        $this->assertCount(4, $fresh->search($version, 'en', 'zz'));
    }

    /**
     * @return array<string,array{string,array<int,string>,string}>
     */
    public static function snippets(): array
    {
        $a = str_repeat('a', 100);

        return [
            'a word found only in the heading' => ['plain text', ['zz'], 'plain text'],
            'the words marked' => ['abc zz def', ['zz'], 'abc <mark>zz</mark> def'],
            'sixty characters before the first word' => [$a.' zz '.str_repeat('b', 300), ['zz'], '…'.str_repeat('a', 59).' <mark>zz</mark> '.str_repeat('b', 137).'…'],
            'the first of two words' => [str_repeat('a', 200).' bb '.str_repeat('c', 20).' qq '.str_repeat('d', 300), ['qq', 'bb'], '…'.str_repeat('a', 59).' <mark>bb</mark> '.str_repeat('c', 20).' <mark>qq</mark> '.str_repeat('d', 113).'…'],
            'a text of two hundred characters has no dots' => [str_repeat('x', 200), ['x'], str_repeat('<mark>x</mark>', 200)],
            'a text of two hundred and one has a trailing one' => [str_repeat('x', 201), ['x'], str_repeat('<mark>x</mark>', 200).'…'],
            'a window that ends with the text' => [str_repeat('y', 100).'zz'.str_repeat('y', 138), ['zz'], '…'.str_repeat('y', 60).'<mark>zz</mark>'.str_repeat('y', 138)],
            'a word at the sixtieth' => [str_repeat('y', 60).'zz tail', ['zz'], str_repeat('y', 60).'<mark>zz</mark> tail'],
            'a word at the sixty-first' => [str_repeat('y', 61).'zz tail', ['zz'], '…'.str_repeat('y', 60).'<mark>zz</mark> tail'],
            'letters of one byte and of two' => [str_repeat('я', 100).' zz '.str_repeat('ю', 300), ['zz'], '…'.str_repeat('я', 59).' <mark>zz</mark> '.str_repeat('ю', 137).'…'],
            'markup and an ampersand' => ['R&D <b>team</b>', ['r&d'], '<mark>R&amp;D</mark> &lt;b&gt;team&lt;/b&gt;'],
            'a word with signs of a pattern' => ['I like c++ a lot', ['c++'], 'I like <mark>c++</mark> a lot'],
            'capitals of any alphabet' => ['Привет МИР', ['мир'], 'Привет <mark>МИР</mark>'],
        ];
    }

    /**
     * @param  array<int,string>  $terms
     */
    #[DataProvider('snippets')]
    public function test_the_snippet_shows_the_text_around_the_first_word_found(string $text, array $terms, string $expected): void
    {
        $method = new ReflectionMethod(Docs::class, 'snippet');

        $this->assertSame($expected, $method->invoke(null, $text, $terms));
    }
}
