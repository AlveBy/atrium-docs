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

    public function test_the_storage_is_made_before_git_is_asked(): void
    {
        $this->assertDirectoryDoesNotExist($this->storage);

        $this->artisan('atrium:docs:pull', ['ref' => 'main', '--source' => $this->repository.'/none'])->assertFailed();

        $this->assertDirectoryExists($this->storage);
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

    /**
     * Pretends git cloned a repository holding $files into the directory
     * the command names last.
     *
     * @param  array<string,string>  $files
     */
    private function clones(array $files): void
    {
        Process::fake(function ($process) use ($files) {
            $directory = end($process->command);

            foreach ($files as $path => $contents) {
                (new Filesystem)->ensureDirectoryExists(dirname($directory.'/'.$path));
                file_put_contents($directory.'/'.$path, $contents);
            }

            return Process::result();
        });
    }

    public function test_git_clones_one_commit_of_the_ref_within_five_minutes_into_a_directory_of_its_own(): void
    {
        $this->clones(['README.md' => '# Readme']);

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0', '--source' => 'https://example.test/repo.git'])->assertSuccessful();

        Process::assertRan(function ($process): bool {
            $command = $process->command;

            return $process->timeout === 300
                && array_slice($command, 0, 7) === ['git', 'clone', '--quiet', '--depth', '1', '--branch', 'v1.0.0']
                && $command[7] === '--'
                && $command[8] === 'https://example.test/repo.git'
                && preg_match('#^'.preg_quote($this->storage, '#').'/\.clone-[0-9a-f]{8}$#', $command[9]) === 1
                && count($command) === 10;
        });
    }

    public function test_the_source_is_the_one_of_the_config_unless_the_option_says_otherwise(): void
    {
        config(['atrium-docs.source' => 'https://config.example.test/repo.git']);
        $this->clones(['README.md' => '# Readme']);

        $this->artisan('atrium:docs:pull', ['ref' => 'main'])->assertSuccessful();

        Process::assertRan(fn ($process): bool => $process->command[8] === 'https://config.example.test/repo.git');
    }

    public function test_a_trailing_slash_of_the_storage_is_not_doubled(): void
    {
        config(['atrium-docs.storage' => $this->storage.'/']);
        $this->clones(['README.md' => '# Readme']);

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0'])
            ->expectsOutput('Docs of v1.0.0: 1 files in '.$this->storage.'/v1.0.0.')
            ->assertSuccessful();

        $this->assertFileExists($this->storage.'/v1.0.0/README.md');
    }

    public function test_the_copy_keeps_folders_and_pictures_of_any_case_and_leaves_the_rest(): void
    {
        $this->clones([
            'README.md' => '# Readme',
            'CHANGELOG.md' => '# Changes',
            'LICENSE' => 'MIT',
            'docs/guide.md' => '# Guide',
            'docs/sub/deep.md' => '# Deep',
            'docs/img/PIC.PNG' => 'png',
            'docs/img/vector.svg' => 'svg',
            'docs/notes.txt' => 'text',
            'packages/faq/README.md' => '# Faq',
            'packages/faq/README.de.md' => '# Faq de',
            'packages/faq/CHANGELOG.md' => '# Not a readme',
            'src/notes.md' => '# Not docs',
        ]);

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0'])
            ->expectsOutput('Docs of v1.0.0: 8 files in '.$this->storage.'/v1.0.0.')
            ->assertSuccessful();

        $kept = [];

        foreach ((new Filesystem)->allFiles($this->storage.'/v1.0.0') as $file) {
            $kept[] = $file->getRelativePathname();
        }

        sort($kept);
        $this->assertSame([
            'CHANGELOG.md',
            'README.md',
            'docs/guide.md',
            'docs/img/PIC.PNG',
            'docs/img/vector.svg',
            'docs/sub/deep.md',
            'packages/faq/README.de.md',
            'packages/faq/README.md',
        ], $kept);
    }

    public function test_a_copy_replaces_the_old_one_and_a_staging_left_by_a_crash(): void
    {
        $this->clones(['README.md' => '# New']);
        (new Filesystem)->ensureDirectoryExists($this->storage.'/v1.0.0');
        file_put_contents($this->storage.'/v1.0.0/old.md', 'old');
        (new Filesystem)->ensureDirectoryExists($this->storage.'/v1.0.0.tmp');
        file_put_contents($this->storage.'/v1.0.0.tmp/stale.md', 'stale');

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0'])->assertSuccessful();

        $this->assertSame(['README.md'], array_map('basename', glob($this->storage.'/v1.0.0/*') ?: []));
        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0.tmp');
        $this->assertSame([], glob($this->storage.'/.clone-*') ?: []);
    }

    public function test_the_error_of_git_is_shown_alone_and_trimmed(): void
    {
        Process::fake(['*' => Process::result(errorOutput: "  fatal: boom \n", exitCode: 128)]);

        $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0', '--source' => 'https://example.test/repo.git'])
            ->expectsOutput('git could not fetch "v1.0.0" from https://example.test/repo.git:')
            ->expectsOutput('fatal: boom')
            ->doesntExpectOutputToContain('has no README')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0');
        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0.tmp');
    }

    public function test_the_staging_is_removed_when_the_copy_cannot_be_moved_into_place(): void
    {
        $this->clones(['README.md' => '# Readme']);
        (new Filesystem)->ensureDirectoryExists($this->storage);
        file_put_contents($this->storage.'/v1.0.0', 'a file in the way');

        try {
            $this->artisan('atrium:docs:pull', ['ref' => 'v1.0.0']);
            $this->fail('The move should have failed.');
        } catch (\ErrorException) {
            // Expected: the target is a file.
        }

        $this->assertDirectoryDoesNotExist($this->storage.'/v1.0.0.tmp');
        $this->assertSame([], glob($this->storage.'/.clone-*') ?: []);
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
