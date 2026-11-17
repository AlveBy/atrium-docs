<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Docs\Docs;
use Atrium\Docs\Http\Controllers\DocsController;
use Atrium\Docs\Tests\TestCase;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;

/**
 * What the pages answer when something is missing, how the query of a
 * search is cut, what a picture is served with and which page search
 * engines are sent to.
 */
final class ControllerInternalsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), ThemeServiceProvider::class];
    }

    /**
     * @return array<string,array{string}>
     */
    public static function actions(): array
    {
        return ['index' => ['index'], 'show' => ['show'], 'search' => ['search'], 'asset' => ['asset']];
    }

    #[DataProvider('actions')]
    public function test_an_action_is_public(string $action): void
    {
        $this->assertTrue((new ReflectionMethod(DocsController::class, $action))->isPublic());
    }

    public function test_without_any_version_there_is_nothing_to_open(): void
    {
        config(['atrium-docs.versions' => []]);
        $this->app->forgetInstance(Docs::class);

        $this->get('/docs')->assertStatus(404);
        $this->get('/docs/guide')->assertStatus(404);
    }

    public function test_what_does_not_exist_is_not_found(): void
    {
        $this->get('/docs/nope/guide')->assertStatus(404);
        $this->get('/docs/nope/search')->assertStatus(404);
        $this->get('/docs/nope/_assets/docs/images/admin.png')->assertStatus(404);
        $this->get('/docs/main/nope')->assertStatus(404);
        $this->get('/docs/main/_assets/docs/images/none.png')->assertStatus(404);
        $this->get('/docs/main/_assets/docs/secret.txt')->assertStatus(404);
        $this->get('/docs/nope')->assertStatus(404);
    }

    public function test_a_page_of_the_default_version_is_reached_without_the_version_and_with_a_slash(): void
    {
        $this->get('/docs/guide')->assertStatus(301)->assertRedirect($this->root().'/docs/main/guide');
        $this->get('/docs/modules/shop')->assertStatus(301)->assertRedirect($this->root().'/docs/main/modules/shop');
        $this->get('/docs/guide/')->assertRedirect($this->root().'/docs/main/guide');
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function queries(): array
    {
        return [
            'blanks around' => ['%20%20usage%20%20', 'usage'],
            'a hundred letters' => [str_repeat('a', 100), str_repeat('a', 100)],
            'a hundred and one letters' => [str_repeat('a', 101), str_repeat('a', 100)],
            'a hundred and one letters of two bytes' => [rawurlencode(str_repeat('я', 101)), str_repeat('я', 100)],
            'blanks in front are cut before the count' => ['%20%20%20'.str_repeat('a', 100), str_repeat('a', 100)],
            'a blank at the cut stays' => [str_repeat('a', 99).'%20b', str_repeat('a', 99).' '],
            'nothing' => ['', ''],
            'only blanks' => ['%20%20', ''],
        ];
    }

    #[DataProvider('queries')]
    public function test_the_query_of_a_search_is_trimmed_and_cut_to_a_hundred_letters(string $encoded, string $query): void
    {
        $this->getJson('/docs/main/search?q='.$encoded)->assertOk()->assertJsonPath('query', $query);
    }

    public function test_the_controller_trims_the_query_itself_when_no_middleware_does(): void
    {
        $request = Request::create('/docs/main/search', 'GET', ['q' => "  \t".str_repeat('a', 100)], server: ['HTTP_ACCEPT' => 'application/json']);

        $response = $this->app->make(DocsController::class)->search($request, 'main');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(str_repeat('a', 100), $response->getData(true)['query']);
    }

    public function test_a_search_without_a_query_has_no_results(): void
    {
        $this->getJson('/docs/main/search')->assertOk()->assertExactJson(['query' => '', 'results' => []]);
    }

    public function test_the_language_switch_of_a_search_keeps_the_query(): void
    {
        $this->languages();
        $root = $this->root();

        $this->get('/docs/main/search?q=two%20words')->assertOk()
            ->assertSee('href="'.$root.'/ru/docs/main/search?q=two%20words"', false)
            ->assertSee('href="'.$root.'/de/docs/main/search?q=two%20words"', false)
            ->assertSee('name="robots" content="noindex"', false);
        $this->get('/docs/main/search')->assertOk()
            ->assertSee('href="'.$root.'/ru/docs/main/search"', false)
            ->assertDontSee('/docs/main/search?q=', false);
    }

    public function test_a_picture_of_a_release_may_be_kept_for_a_year_and_a_picture_of_a_branch_for_an_hour(): void
    {
        $this->release('v1.0.0', 'Guide');
        $csp = "default-src 'none'; style-src 'unsafe-inline'";

        $release = $this->get('/docs/v1.0.0/_assets/docs/images/admin.png')->assertOk();
        $branch = $this->get('/docs/main/_assets/docs/images/admin.png')->assertOk();

        $this->assertSame('immutable, max-age=31536000, public', $release->headers->get('Cache-Control'));
        $this->assertSame('max-age=3600, public', $branch->headers->get('Cache-Control'));

        foreach ([$release, $branch] as $response) {
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertSame($csp, $response->headers->get('Content-Security-Policy'));
        }
    }

    public function test_the_page_of_an_older_version_that_the_default_has_too_is_indexed_there_and_one_that_it_lacks_is_indexed_where_it_is(): void
    {
        $this->release('v0.9.0', 'Old');
        $this->release('v1.0.0', 'New');
        unlink($this->storage.'/v0.9.0/docs/about.md');
        file_put_contents($this->storage.'/v0.9.0/docs/gone.md', '# Gone');
        $this->app->forgetInstance(Docs::class);
        $root = $this->root();

        $this->get('/docs/v0.9.0/guide')->assertSee('<link rel="canonical" href="'.$root.'/docs/v1.0.0/guide">', false);
        $this->get('/docs/v0.9.0/gone')->assertSee('<link rel="canonical" href="'.$root.'/docs/v0.9.0/gone">', false);
        $this->get('/docs/v1.0.0/guide')->assertSee('<link rel="canonical" href="'.$root.'/docs/v1.0.0/guide">', false);
    }

    public function test_the_canonical_page_is_in_the_language_it_is_written_in(): void
    {
        $this->languages();
        $root = $this->root();

        $this->get('/ru/docs/main/guide')->assertSee('<link rel="canonical" href="'.$root.'/ru/docs/main/guide">', false);
        $this->get('/ru/docs/main/about')->assertSee('<link rel="canonical" href="'.$root.'/docs/main/about">', false);
        $this->get('/docs/main/about')->assertSee('<link rel="canonical" href="'.$root.'/docs/main/about">', false);
    }

    public function test_a_site_without_the_source_language_keeps_the_canonical_in_the_language_asked_for(): void
    {
        $this->languages();
        Language::query()->where('code', 'en')->update(['code' => 'xx']);
        $this->app->make(ContentLanguages::class)->flush();

        $this->assertFalse($this->app->make(ContentLanguages::class)->has('en'));
        $this->get('/ru/docs/main/about')->assertOk()->assertSee('<link rel="canonical" href="'.$this->root().'/ru/docs/main/about">', false);
    }
}
