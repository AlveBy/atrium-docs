<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs;

/**
 * A page turned into HTML, with what the layout and the search need.
 */
final readonly class Rendered
{
    /**
     * @param  array<int,array{level:int,id:string,text:string}>  $headings  the h2 and h3 of the page, for "On this page"
     * @param  array<int,array{id:string,heading:string,text:string}>  $sections  plain text under each heading, for the search
     */
    public function __construct(
        public string $title,
        public string $html,
        public string $description,
        public array $headings,
        public array $sections,
    ) {}

    /**
     * Plain arrays for the cache: Laravel unserializes no objects from the
     * cache unless the app lists their classes (cache.serializable_classes).
     *
     * @return array{title:string,html:string,description:string,headings:array<int,array{level:int,id:string,text:string}>,sections:array<int,array{id:string,heading:string,text:string}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'html' => $this->html,
            'description' => $this->description,
            'headings' => $this->headings,
            'sections' => $this->sections,
        ];
    }

    /**
     * @param  array{title:string,html:string,description:string,headings:array<int,array{level:int,id:string,text:string}>,sections:array<int,array{id:string,heading:string,text:string}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['title'], $data['html'], $data['description'], $data['headings'], $data['sections']);
    }
}
