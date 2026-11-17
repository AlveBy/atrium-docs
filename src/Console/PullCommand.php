<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Console;

use Atrium\Docs\Docs;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;

/**
 * Copies the documentation of a tag or a branch out of the git repository
 * into storage, as one more version of the docs:
 *
 *   php artisan atrium:docs:pull v0.2.0
 *   php artisan atrium:docs:pull main          (again after each push)
 *
 * Only the Markdown and the pictures are kept: README.md, CHANGELOG.md,
 * docs/ and the READMEs of packages/*. The old copy is replaced only once
 * the new one is complete.
 */
final class PullCommand extends Command
{
    protected $signature = 'atrium:docs:pull
        {ref : A tag (v0.2.0) or a branch (main)}
        {--source= : The git repository; atrium-docs.source by default}';

    protected $description = 'Copy the documentation of a release or a branch into storage';

    public function handle(Filesystem $files): int
    {
        $ref = $this->argument('ref');

        if (! Docs::validName($ref)) {
            $this->error("\"{$ref}\" is not a tag or a branch name.");

            return self::FAILURE;
        }

        $source = $this->option('source') ?: config('atrium-docs.source');
        $storage = rtrim((string) config('atrium-docs.storage'), '/');
        $clone = $storage.'/.clone-'.bin2hex(random_bytes(4));
        $target = $storage.'/'.$ref;
        $staging = $target.'.tmp';

        $files->ensureDirectoryExists($storage);

        try {
            $result = Process::timeout(300)->run(['git', 'clone', '--quiet', '--depth', '1', '--branch', $ref, '--', $source, $clone]);

            if ($result->failed()) {
                $this->error("git could not fetch \"{$ref}\" from {$source}:");
                $this->line(trim($result->errorOutput()));

                return self::FAILURE;
            }

            $files->deleteDirectory($staging);
            $copied = $this->copy($files, $clone, $staging);

            if ($copied === 0) {
                $this->error("\"{$ref}\" has no README.md or docs/ to show.");

                return self::FAILURE;
            }

            $files->deleteDirectory($target);
            $files->move($staging, $target);
        } finally {
            $files->deleteDirectory($clone);
            $files->deleteDirectory($staging);
        }

        $this->info("Docs of {$ref}: {$copied} files in {$target}.");

        return self::SUCCESS;
    }

    private function copy(Filesystem $files, string $from, string $to): int
    {
        $copied = 0;
        $wanted = [
            ...glob($from.'/*.md') ?: [],
            ...$this->tree($files, $from.'/docs'),
            ...glob($from.'/packages/*/README*.md') ?: [],
        ];

        foreach ($wanted as $file) {
            if (! is_file($file) || is_link($file)) {
                continue;
            }

            $relative = substr($file, strlen($from));
            $files->ensureDirectoryExists(dirname($to.$relative));
            $files->copy($file, $to.$relative);
            $copied++;
        }

        return $copied;
    }

    /**
     * @return array<int,string>
     */
    private function tree(Filesystem $files, string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $found = [];

        foreach ($files->allFiles($directory) as $file) {
            if (in_array(strtolower($file->getExtension()), ['md', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif'], true)) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}
