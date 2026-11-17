<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

/**
 * What `atrium:docs:pull` keeps of a repository, and what it leaves alone
 * when there is nothing to show or git fails.
 */
final class PullTest extends TestCase
{
    private string $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->storage.'-repo';
        (new Filesystem)->ensureDirectoryExists($this->repository);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->repository);

        parent::tearDown();
    }

    public function test_a_repository_without_docs_is_refused_and_the_old_copy_stays(): void
    {
        file_put_contents($this->repository.'/LICENSE', 'MIT');
        mkdir($this->repository.'/src');
        file_put_contents($this->repository.'/src/notes.md', '# Not docs');
        $this->commit();
        $this->release('v1.0.0', 'The old guide');

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0', '--source' => $this->repository])
            ->expectsOutputToContain('"v1.0.0" has no README.md or docs/ to show.')
            ->doesntExpectOutputToContain('Docs of v1.0.0')
            ->assertFailed();

        $this->assertStringContainsString('# The old guide', (string) file_get_contents($this->storage.'/v1.0.0/docs/guide.md'));
        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0.tmp');
        $this->assertSame([], glob($this->storage.'/.clone-*') ?: []);
    }

    public function test_links_and_folders_named_like_markdown_are_not_copied(): void
    {
        file_put_contents($this->repository.'/README.md', '# Readme');
        symlink('README.md', $this->repository.'/LINKED.md');
        mkdir($this->repository.'/folder.md');
        file_put_contents($this->repository.'/folder.md/inside.txt', 'text');
        $this->commit();

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0', '--source' => $this->repository])
            ->expectsOutputToContain('Docs of v1.0.0: 1 files in '.$this->storage.'/v1.0.0.')
            ->assertSuccessful();

        $this->assertFileExists($this->storage.'/v1.0.0/README.md');
        $this->assertFalse(is_link($this->storage.'/v1.0.0/LINKED.md') || file_exists($this->storage.'/v1.0.0/LINKED.md'));
        $this->assertFileDoesNotExist($this->storage.'/v1.0.0/folder.md');
        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0/docs');
    }

    public function test_a_tag_git_cannot_fetch_shows_what_git_said(): void
    {
        file_put_contents($this->repository.'/README.md', '# Readme');
        $this->commit();
        $this->release('v9.9.9', 'The old guide');

        $this->artisan('atrium:docs:pull', ['ref' => 'v9.9.9', '--source' => $this->repository])
            ->expectsOutputToContain('git could not fetch "v9.9.9" from '.$this->repository.':')
            ->expectsOutputToContain('Remote branch v9.9.9 not found')
            ->assertFailed();

        $this->assertFileExists($this->storage.'/v9.9.9/docs/guide.md');
        $this->assertSame([], glob($this->storage.'/.clone-*') ?: []);
    }

    public function test_a_ref_that_is_not_a_name_is_refused_before_git_runs(): void
    {
        Process::fake();

        $this->artisan('atrium:docs:pull', ['ref' => '../etc', '--source' => $this->repository])
            ->expectsOutputToContain('"../etc" is not a tag or a branch name.')
            ->assertFailed();

        Process::assertNothingRan();
    }

    private function commit(): void
    {
        foreach ([
            ['git', 'init', '--quiet', '--initial-branch=main'],
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'add', '.'],
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Docs'],
            ['git', 'tag', 'v1.0.0'],
        ] as $command) {
            Process::path($this->repository)->run($command)->throw();
        }
    }
}
