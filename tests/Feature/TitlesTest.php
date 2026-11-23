<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Tests\TestCase;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Event;

final class TitlesTest extends TestCase
{
    public function test_the_title_is_the_first_heading_as_plain_text(): void
    {
        $this->assertSame('Atrium Docs', Docs::heading("# Atrium Docs\n\nText."));
        $this->assertSame('The guide', Docs::heading("Intro\n\n```md\n# Not this\n```\n\n## Sub\n# The guide #\n"));
        $this->assertSame('Install the atrium_docs module', Docs::heading('# Install the **`atrium_docs`** [module](x.md)'));
        $this->assertNull(Docs::heading("## Only a section\n\nText."));
    }

    public function test_a_cyrillic_title_is_not_cut_in_the_middle_of_a_letter(): void
    {
        // The second byte of "х" (D1 85) is NEL to a byte-wise \R.
        $this->assertSame('Двухфакторный вход', Docs::heading("# Двухфакторный вход\n\nТекст."));
        $this->assertSame('Вход через соцсети', Docs::heading("Вступление\r\n# Вход через соцсети\r\n"));
        $this->assertSame('Title', Docs::heading("Intro\r# Title\r"));
    }

    public function test_titles_read_from_the_files_match_the_rendered_ones(): void
    {
        $docs = $this->app->make(Docs::class);
        $version = $docs->version('main');
        $this->assertNotNull($version);

        foreach (['en', 'ru', 'de'] as $language) {
            foreach ($docs->pages($version) as $page) {
                $rendered = $docs->render($version, $page, $language)->title;
                $this->assertSame($rendered !== '' ? $rendered : $page->slug, $docs->title($version, $page, $language), "{$page->slug} in {$language}");
            }
        }
    }

    public function test_a_page_renders_only_itself_not_every_page_of_the_sidebar(): void
    {
        Event::fake([KeyWritten::class]);

        $this->get('/docs/main/guide')->assertOk()->assertSee('About');

        $rendered = 0;
        Event::assertDispatched(KeyWritten::class, function (KeyWritten $event) use (&$rendered): bool {
            $rendered += str_starts_with($event->key, 'atrium-docs:') ? 1 : 0;

            return true;
        });
        $this->assertSame(1, $rendered);
    }
}
