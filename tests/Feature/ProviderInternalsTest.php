<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\DocsServiceProvider;
use Atrium\Docs\Tests\TestCase;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * What the provider hands to the app: the files it offers to publish, the
 * layout it picks, the stylesheet and the shape of the routes.
 */
final class ProviderInternalsTest extends TestCase
{
    private const VERSION = '[A-Za-z0-9][A-Za-z0-9._-]{0,63}';

    /**
     * @return array<string,string>
     */
    private function published(string $tag): array
    {
        $paths = [];

        foreach (ServiceProvider::pathsToPublish(DocsServiceProvider::class, $tag) as $from => $to) {
            $paths[(string) realpath($from)] = $to;
        }

        return $paths;
    }

    public function test_the_config_is_offered_to_the_app_and_merged_under_its_own_name(): void
    {
        $this->assertSame([(string) realpath(__DIR__.'/../../config/atrium-docs.php') => config_path('atrium-docs.php')], $this->published('atrium-docs-config'));
        $this->assertSame('docs', config('atrium-docs.prefix'));
        $this->assertTrue(config('atrium-docs.routes'));
        $this->assertIsString(config('atrium-docs.source'));
    }

    public function test_the_views_are_offered_to_the_app(): void
    {
        $this->assertSame([(string) realpath(__DIR__.'/../../resources/views') => resource_path('views/vendor/atrium-docs')], $this->published('atrium-docs-views'));
    }

    public function test_the_texts_are_offered_to_the_app(): void
    {
        $this->assertSame([(string) realpath(__DIR__.'/../../lang') => $this->app->langPath('vendor/atrium-docs')], $this->published('atrium-docs-lang'));
    }

    public function test_the_views_and_the_texts_of_the_package_are_loaded(): void
    {
        $this->assertTrue(view()->exists('atrium-docs::page'));
        $this->assertNotSame('atrium-docs::docs.search_title', __('atrium-docs::docs.search_title'));
    }

    public function test_the_layout_of_the_config_wins_when_it_names_one(): void
    {
        config(['atrium-docs.layout' => 'my::layout']);
        $this->assertSame('my::layout', DocsServiceProvider::layout());

        config(['atrium-docs.layout' => '']);
        $this->assertSame('atrium-docs::layout', DocsServiceProvider::layout());

        config(['atrium-docs.layout' => 5]);
        $this->assertSame('atrium-docs::layout', DocsServiceProvider::layout());

        config(['atrium-docs.layout' => null]);
        $this->assertSame('atrium-docs::layout', DocsServiceProvider::layout());
    }

    public function test_the_layout_is_the_themes_when_the_theme_is_installed(): void
    {
        $this->app->register(ThemeServiceProvider::class);

        $this->assertSame('atrium-theme::layout', DocsServiceProvider::layout());
    }

    public function test_the_stylesheet_is_the_file_of_the_package(): void
    {
        $this->assertSame((string) file_get_contents(__DIR__.'/../../resources/css/docs.css'), DocsServiceProvider::styles());
        $this->assertStringContainsString('.docs-body', DocsServiceProvider::styles());
    }

    public function test_a_page_wants_a_version_of_plain_characters_and_a_path_of_slugs(): void
    {
        $route = Route::getRoutes()->getByName('atrium.docs.show');

        $this->assertNotNull($route);
        $this->assertSame('docs/{version}/{page?}', $route->uri());
        $this->assertSame(['version' => self::VERSION, 'page' => '[A-Za-z0-9/_-]*'], $route->wheres);
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_a_search_wants_the_same_version(): void
    {
        $route = Route::getRoutes()->getByName('atrium.docs.search');

        $this->assertNotNull($route);
        $this->assertSame('docs/{version}/search', $route->uri());
        $this->assertSame(['version' => self::VERSION], $route->wheres);
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_a_picture_has_one_address_in_every_language_and_no_session(): void
    {
        $route = Route::getRoutes()->getByName('atrium.docs.asset');

        $this->assertNotNull($route);
        $this->assertSame('docs/{version}/_assets/{path}', $route->uri());
        $this->assertSame(['version' => self::VERSION, 'path' => '.+'], $route->wheres);
        $this->assertNotContains('web', $route->gatherMiddleware());
        $this->assertCount(1, array_filter(Route::getRoutes()->getRoutes(), fn ($other): bool => str_ends_with($other->uri(), '/_assets/{path}')));
    }

    public function test_the_start_of_the_docs_is_the_prefix_alone(): void
    {
        $route = Route::getRoutes()->getByName('atrium.docs.index');

        $this->assertNotNull($route);
        $this->assertSame('docs', $route->uri());
    }

    #[DefineEnvironment('slashedPrefix')]
    public function test_the_slashes_around_a_prefix_do_not_count(): void
    {
        $this->assertSame('help/me', Route::getRoutes()->getByName('atrium.docs.index')?->uri());
        $this->assertSame('help/me/{version}/{page?}', Route::getRoutes()->getByName('atrium.docs.show')?->uri());
        $this->assertSame('help/me/{version}/_assets/{path}', Route::getRoutes()->getByName('atrium.docs.asset')?->uri());
    }

    protected function slashedPrefix($app): void
    {
        $app['config']->set('atrium-docs.prefix', '/help/me/');
    }
}
