<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs;

/**
 * A page of the documentation and the files it is written in: the English
 * original (README.md, docs/modules.md) and its translations
 * (docs/readme/README.de.md, docs/modules.ru.md).
 */
final readonly class Page
{
    public const INDEX = 'index';

    public const GUIDE = 'guide';

    public const MODULE = 'module';

    /**
     * @param  array<string,string>  $files  language code (lower case, "pt-br") => path from the version's root; "en" is the original
     */
    public function __construct(
        public string $slug,
        public string $group,
        public array $files,
    ) {}

    public function original(): string
    {
        return $this->files[Docs::SOURCE];
    }

    public function has(string $language): bool
    {
        return isset($this->files[Docs::code($language)]);
    }

    /**
     * The file to show in a language: its translation, else the original.
     */
    public function file(string $language): string
    {
        return $this->files[Docs::code($language)] ?? $this->original();
    }

    /**
     * @return array<int,string>
     */
    public function languages(): array
    {
        return array_keys($this->files);
    }
}
