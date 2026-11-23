<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Tests\TestCase;

/**
 * A real cache serializes what it keeps, and Laravel unserializes no
 * objects from it by default: the rendered pages must come back whole.
 */
final class CacheTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'file');
        $app['config']->set('cache.stores.file.path', $this->storage.'/cache');
        $app['config']->set('cache.serializable_classes', false);
    }

    public function test_pages_and_search_survive_a_serializing_cache(): void
    {
        $this->get('/docs/main/guide')->assertOk()->assertSee('<h1>The guide</h1>', false);

        // A new request: everything now comes from the cache.
        $this->app->forgetInstance(Docs::class);

        $this->get('/docs/main/guide')->assertOk()->assertSee('<h1>The guide</h1>', false)->assertSee('id="field-options"', false);
        $this->getJson('/docs/main/_search?q=usage')->assertOk()->assertJsonPath('results.0.page', 'modules/shop');
        $this->getJson('/docs/main/_search?q=usage')->assertOk()->assertJsonPath('results.0.page', 'modules/shop');
    }
}
