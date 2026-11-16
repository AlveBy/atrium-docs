<?php

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

final class VersionsTest extends TestCase
{
    public function test_the_newest_release_is_the_default_version(): void
    {
        $this->release('v0.9.0', 'Guide of 0.9');
        $this->release('v0.10.0', 'Guide of 0.10');

        $docs = $this->app->make(Docs::class);

        $this->assertSame(['v0.10.0', 'v0.9.0', 'main'], array_keys($docs->versions()));
        $this->assertSame('v0.10.0', $docs->defaultVersion()?->name);

        $this->get('/docs')->assertRedirect($this->root().'/docs/v0.10.0');
        $this->get('/docs/v0.10.0/guide')->assertOk()->assertSee('<h1>Guide of 0.10</h1>', false)->assertDontSee('class="docs-notice"', false);
    }

    public function test_an_older_version_says_so_and_points_to_the_current_one(): void
    {
        $this->release('v0.9.0', 'Guide of 0.9');
        $this->release('v0.10.0', 'Guide of 0.10');
        $root = $this->root();

        $this->get('/docs/v0.9.0/guide')
            ->assertOk()
            ->assertSee('<h1>Guide of 0.9</h1>', false)
            ->assertSee('This is the documentation of v0.9.0, not of the current version.')
            ->assertSee('<link rel="canonical" href="'.$root.'/docs/v0.10.0/guide">', false)
            // Links of a release point to the release on GitHub.
            ->assertSee('href="https://github.com/acme/acme/blob/v0.9.0/LICENSE"', false)
            ->assertSee('<a href="'.$root.'/docs/main">main</a>', false)
            ->assertSee('<a href="'.$root.'/docs/v0.10.0">v0.10.0</a>', false);
    }

    public function test_a_configured_default_wins(): void
    {
        $this->release('v0.9.0', 'Guide of 0.9');
        config(['atrium-docs.default' => 'main']);

        $this->get('/docs')->assertRedirect($this->root().'/docs/main');
    }

    public function test_a_page_without_a_version_in_the_address_goes_to_the_default_version(): void
    {
        $this->get('/docs/guide')->assertRedirect($this->root().'/docs/main/guide')->assertStatus(301);
        $this->get('/docs/modules/shop')->assertRedirect($this->root().'/docs/main/modules/shop');
    }

    public function test_pull_copies_the_docs_of_a_tag_into_storage(): void
    {
        $repository = $this->storage.'-repo';
        (new Filesystem)->copyDirectory(self::fixture(), $repository);

        foreach ([
            ['git', 'init', '--quiet', '--initial-branch=main'],
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'add', '.'],
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Docs'],
            ['git', 'tag', 'v1.0.0'],
        ] as $command) {
            Process::path($repository)->run($command)->throw();
        }

        try {
            $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0', '--source' => $repository])
                ->expectsOutputToContain('Docs of v1.0.0')
                ->assertSuccessful();

            $this->assertFileExists($this->storage.'/v1.0.0/docs/guide.md');
            $this->assertFileExists($this->storage.'/v1.0.0/docs/images/admin.png');
            $this->assertFileExists($this->storage.'/v1.0.0/packages/shop/README.md');
            $this->assertFileDoesNotExist($this->storage.'/v1.0.0/docs/secret.txt');
            $this->assertSame([], glob($this->storage.'/.clone-*') ?: []);

            $this->app->forgetInstance(Docs::class);
            $this->get('/docs')->assertRedirect($this->root().'/docs/v1.0.0');

            $this->artisan('atrium:docs:pull', ['ref' => 'v9.9.9', '--source' => $repository])->assertFailed();
            $this->artisan('atrium:docs:pull', ['ref' => '--upload-pack=touch /tmp/x', '--source' => $repository])->assertFailed();
        } finally {
            (new Filesystem)->deleteDirectory($repository);
        }
    }
}
