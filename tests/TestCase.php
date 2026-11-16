<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Docs\Docs;
use Atrium\Docs\DocsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected string $storage;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            AtriumServiceProvider::class,
            DocsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Acme');
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);

        $this->storage = sys_get_temp_dir().'/atrium-docs-'.getmypid().'-'.bin2hex(random_bytes(3));
        $config->set('atrium-docs.storage', $this->storage);
        $config->set('atrium-docs.versions', ['main' => self::fixture()]);
        $config->set('atrium-docs.default', null);
        $config->set('atrium-docs.order', ['guide']);
        $config->set('atrium-docs.repository', 'https://github.com/acme/acme');
        $config->set('atrium-docs.branch', 'main');
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->storage);

        parent::tearDown();
    }

    public static function fixture(): string
    {
        return __DIR__.'/Fixtures/repo';
    }

    /**
     * English (the default, made by the migration), Russian and German.
     */
    protected function languages(): void
    {
        Language::query()->create(['code' => 'ru', 'name' => 'Русский', 'sort' => 2]);
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 3]);
        $this->app->make(ContentLanguages::class)->flush();
    }

    /**
     * A copy of the fixture repository in storage, as `atrium:docs:pull`
     * leaves it, with $title as the guide's title.
     */
    protected function release(string $name, string $title): void
    {
        $files = new Filesystem;
        $files->copyDirectory(self::fixture(), $this->storage.'/'.$name);
        $guide = $this->storage.'/'.$name.'/docs/guide.md';
        file_put_contents($guide, preg_replace('/^# .*$/m', '# '.$title, (string) file_get_contents($guide), 1));
        $this->app->forgetInstance(Docs::class);
    }

    protected function root(): string
    {
        return rtrim(url('/'), '/');
    }
}
