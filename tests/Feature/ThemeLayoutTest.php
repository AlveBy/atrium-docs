<?php

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;
use Atrium\Theme\ThemeServiceProvider;

/**
 * With the public theme installed, docs pages are pages of the site: its
 * header, menu, language switch and colours.
 */
final class ThemeLayoutTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ThemeServiceProvider::class];
    }

    public function test_docs_pages_extend_the_theme_layout(): void
    {
        $this->languages();
        $root = $this->root();

        $this->get('/docs/main/guide')
            ->assertOk()
            ->assertSee('class="site-header"', false)
            ->assertSee('<h1>The guide</h1>', false)
            ->assertSee('<link rel="canonical" href="'.$root.'/docs/main/guide">', false)
            ->assertSee('hreflang="ru" href="'.$root.'/ru/docs/main/guide"', false)
            ->assertSee('.docs-body', false)
            // The theme's switch leads to the same page in German.
            ->assertSee('href="'.$root.'/de/docs/main/guide"', false);
    }

    public function test_a_layout_of_the_app_wins(): void
    {
        config(['atrium-docs.layout' => 'atrium-docs::layout']);

        $this->get('/docs/main/guide')->assertOk()->assertSee('class="docs-standalone"', false)->assertDontSee('class="site-header"', false);
    }
}
