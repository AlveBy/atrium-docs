<?php

declare(strict_types=1);

namespace Atrium\Docs;

/**
 * One version of the documentation: a name (a release tag such as v0.2.0,
 * or a branch such as main) and the folder holding its copy of the
 * repository.
 */
final readonly class Version
{
    public function __construct(
        public string $name,
        public string $path,
    ) {}

    /**
     * A release tag: v1.2.3 or 1.2.3, with an optional -beta.1 and the like.
     */
    public function isRelease(): bool
    {
        return preg_match('/^v?\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $this->name) === 1;
    }

    public function file(string $relative): string
    {
        return rtrim($this->path, '/').'/'.ltrim($relative, '/');
    }

    /**
     * Changes whenever a file of this version changes: part of every cache
     * key, so a `git pull` or a new `atrium:docs:pull` needs no cache clear.
     */
    public function fingerprint(): string
    {
        $parts = [];

        foreach ([...glob($this->file('docs/*.md')) ?: [], ...glob($this->file('docs/readme/*.md')) ?: [], ...glob($this->file('packages/*/README*.md')) ?: [], $this->file('README.md')] as $file) {
            if (is_file($file)) {
                $parts[] = $file.':'.filemtime($file).':'.filesize($file);
            }
        }

        return md5($this->path.'|'.implode('|', $parts));
    }
}
