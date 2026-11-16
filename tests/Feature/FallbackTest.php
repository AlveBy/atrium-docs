<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;

final class FallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->languages();
    }

    public function test_a_translated_page_is_shown_in_its_language(): void
    {
        $root = $this->root();

        $this->get('/ru/docs/main/guide')
            ->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee('<h1>Руководство</h1>', false)
            ->assertSee('<h2 id="начало">Начало', false)
            ->assertDontSee('class="docs-notice"', false)
            ->assertSee('<link rel="canonical" href="'.$root.'/ru/docs/main/guide">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="'.$root.'/ru/docs/main/guide">', false)
            ->assertSee('href="https://github.com/acme/acme/edit/main/docs/guide.ru.md"', false)
            // A link to an original from a translation stays in Russian.
            ->assertSee('href="'.$root.'/ru/docs/main/modules/shop"', false);

        $this->get('/ru/docs/main')
            ->assertOk()
            ->assertSee('Одна админка для многих сайтов.')
            ->assertSee('href="'.$root.'/ru/docs/main/guide"', false);
    }

    public function test_a_page_without_a_translation_is_shown_in_english_with_a_notice(): void
    {
        $root = $this->root();
        $response = $this->get('/de/docs/main/guide')->assertOk();

        $response
            ->assertSee('<html lang="de">', false)
            ->assertSee('<h1>The guide</h1>', false)
            ->assertSee('<article class="docs-body" lang="en">', false)
            ->assertSee('Diese Seite ist noch nicht in Ihre Sprache übersetzt und wird daher auf Englisch angezeigt.')
            ->assertSee('href="https://github.com/acme/acme/new/main/docs?filename=guide.de.md"', false)
            // Search engines index the English page, not its copy.
            ->assertSee('<link rel="canonical" href="'.$root.'/docs/main/guide">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$root.'/docs/main/guide">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="'.$root.'/ru/docs/main/guide">', false)
            ->assertDontSee('hreflang="de" href="'.$root.'/de/docs/main/guide"', false)
            // Links of the English text keep the reader in German.
            ->assertSee('href="'.$root.'/de/docs/main/modules/shop#usage"', false)
            ->assertSee('href="'.$root.'/de/docs/main/about"', false);
    }

    public function test_the_sidebar_titles_follow_the_language(): void
    {
        $this->get('/ru/docs/main/about')
            ->assertOk()
            ->assertSee('>Руководство</a>', false)
            ->assertSee('>About</a>', false);
    }

    public function test_a_language_the_site_does_not_have_is_not_found(): void
    {
        $this->get('/fr/docs/main/guide')->assertNotFound();
    }
}
