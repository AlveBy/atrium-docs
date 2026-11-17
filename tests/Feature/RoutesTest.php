<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

/**
 * The module's routes, or none when the app draws its own.
 */
final class RoutesTest extends TestCase
{
    public function test_the_module_draws_its_routes_by_default(): void
    {
        $this->assertTrue(Route::has('atrium.docs.show'));
        $this->get('/docs/main/guide')->assertOk();
    }

    #[DefineEnvironment('withoutRoutes')]
    public function test_routes_off_leaves_the_addresses_to_the_app(): void
    {
        $this->assertFalse(Route::has('atrium.docs.show'));
        $this->assertFalse(Route::has('atrium.docs.search'));
        $this->get('/docs/main/guide')->assertNotFound();
    }

    protected function withoutRoutes($app): void
    {
        $app['config']->set('atrium-docs.routes', false);
    }
}
