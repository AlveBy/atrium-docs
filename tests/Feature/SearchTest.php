<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;

final class SearchTest extends TestCase
{
    public function test_search_finds_the_section_that_mentions_every_word(): void
    {
        $this->get('/docs/main/_search?q=visual+editor')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('One section mentions “visual editor”.')
            ->assertSee('href="'.$this->root().'/docs/main/guide#fields"', false)
            ->assertSee('<mark>visual</mark> <mark>editor</mark>', false);
    }

    public function test_a_word_in_a_heading_weighs_more(): void
    {
        $results = $this->getJson('/docs/main/_search?q=usage')->assertOk()->json('results');

        $this->assertSame('modules/shop', $results[0]['page']);
        $this->assertSame('Usage', $results[0]['heading']);
    }

    public function test_search_answers_json_for_the_search_box(): void
    {
        $this->getJson('/docs/main/_search?q=nothing-like-this')
            ->assertOk()
            ->assertExactJson(['query' => 'nothing-like-this', 'results' => []]);

        $this->get('/docs/main/_search?q=nothing-like-this')->assertSee('Nothing found for “nothing-like-this”.');
        $this->get('/docs/main/_search')->assertOk()->assertSee('Type a word or two');
    }

    public function test_search_looks_in_the_language_of_the_page(): void
    {
        $this->languages();

        $this->getJson('/ru/docs/main/_search?q='.rawurlencode('Установите'))
            ->assertOk()
            ->assertJsonPath('results.0.url', $this->root().'/ru/docs/main/guide#начало');

        // Untranslated pages are searched in English.
        $this->getJson('/ru/docs/main/_search?q=usage')->assertJsonPath('results.0.page', 'modules/shop');
    }

    public function test_the_query_is_escaped(): void
    {
        $this->get('/docs/main/_search?q=%3Cscript%3Ealert(1)%3C/script%3E')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
