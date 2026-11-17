<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs;

use Atrium\Admin\Support\ContentLanguages;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The documentation: its versions, the pages of each, their HTML in each
 * language and the search through them.
 *
 * A version is a copy of the repository. Its pages are README.md (the
 * start page; translations in docs/readme/README.{lang}.md), every
 * docs/{name}.md (translations beside it as {name}.{lang}.md), the
 * CHANGELOG.md and every packages/{name}/README.md (under modules/{name}).
 * A page that is not translated into a language is shown in English.
 */
final class Docs
{
    /** The language the originals are written in. */
    public const SOURCE = 'en';

    private const FILE = '/^(?<name>[A-Za-z0-9_-]+?)(?:\.(?<lang>[a-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?))?\.md$/';

    private const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif'];

    /** @var array<string,Version>|null */
    private ?array $versions = null;

    /** @var array<string,array<string,Page>> */
    private array $pages = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly ContentLanguages $languages,
    ) {}

    /**
     * "pt_BR", "pt-BR" and "pt-br" are one language.
     */
    public static function code(string $language): string
    {
        return strtolower(str_replace('_', '-', $language));
    }

    /**
     * Newest release first, then the branches in the order configured.
     *
     * @return array<string,Version>
     */
    public function versions(): array
    {
        if ($this->versions !== null) {
            return $this->versions;
        }

        $found = [];

        foreach ((array) config('atrium-docs.versions', []) as $name => $path) {
            if (is_string($path) && is_dir($path) && self::validName((string) $name)) {
                $found[$name] = new Version((string) $name, rtrim($path, '/'));
            }
        }

        $storage = (string) config('atrium-docs.storage', '');

        foreach ($storage !== '' ? (glob($storage.'/*', GLOB_ONLYDIR) ?: []) : [] as $path) {
            $name = basename($path);

            if (self::validName($name) && ! isset($found[$name]) && ! str_ends_with($name, '.tmp')) {
                $found[$name] = new Version($name, $path);
            }
        }

        $releases = array_filter($found, fn (Version $version): bool => $version->isRelease());
        uasort($releases, fn (Version $a, Version $b): int => version_compare(ltrim($b->name, 'v'), ltrim($a->name, 'v')));

        return $this->versions = [...$releases, ...$found];
    }

    public function version(string $name): ?Version
    {
        return $this->versions()[$name] ?? null;
    }

    public function defaultVersion(): ?Version
    {
        $configured = config('atrium-docs.default');

        if (is_string($configured) && ($version = $this->version($configured)) !== null) {
            return $version;
        }

        return array_values($this->versions())[0] ?? null;
    }

    public static function validName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $name) === 1 && ! str_contains($name, '..');
    }

    /**
     * The pages of a version in sidebar order.
     *
     * @return array<string,Page>
     */
    public function pages(Version $version): array
    {
        return $this->pages[$version->name] ??= $this->scan($version);
    }

    public function page(Version $version, string $slug): ?Page
    {
        return $this->pages($version)[trim($slug, '/')] ?? null;
    }

    /**
     * The page in a language: its translation or, if there is none, the
     * English original. Cached until a file of the version changes.
     */
    public function render(Version $version, Page $page, string $language): Rendered
    {
        $file = $page->file($language);
        $key = 'atrium-docs:'.md5(implode('|', [$version->fingerprint(), $file, self::code($language), $this->urlRoot()]));

        // Kept as an array: the cache gives back no objects (see Rendered::toArray).
        return Rendered::fromArray($this->cache->rememberForever($key, fn (): array => $this->renderer()->render(
            (string) file_get_contents($version->file($file)),
            fn (string $url, bool $image): string => $this->resolve($version, $file, $url, $image, $language),
        )->toArray()));
    }

    /**
     * The title of a page: its first "# Heading", read from the file. The
     * sidebar needs the title of every page on every page, and rendering
     * them all (highlighting included) made the first visit after a change
     * of the repository take twenty seconds.
     */
    public function title(Version $version, Page $page, string $language): string
    {
        $title = self::heading((string) file_get_contents($version->file($page->file($language))))
            ?? $this->render($version, $page, $language)->title;

        return $title !== '' ? $title : $page->slug;
    }

    /**
     * The first "# Heading" of Markdown as plain text, outside code blocks;
     * null when there is none.
     */
    public static function heading(string $markdown): ?string
    {
        $fenced = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (preg_match('/^ {0,3}(```|~~~)/', $line)) {
                $fenced = ! $fenced;

                continue;
            }

            if (! $fenced && preg_match('/^ {0,3}#[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*$/', $line, $match)) {
                $text = preg_replace(['/!?\[([^\]]*)\]\([^)]*\)/', '/\*\*|__|\*|`/'], ['$1', ''], $match[1]) ?? '';

                return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));
            }
        }

        return null;
    }

    /**
     * The address of a page in a language (the current one by default).
     */
    public function url(Version $version, string $slug, ?string $language = null): string
    {
        return $this->languages->route('atrium.docs.show', ['version' => $version->name, 'page' => trim($slug, '/')], $this->contentCode($language));
    }

    public function searchUrl(Version $version, ?string $language = null): string
    {
        return $this->languages->route('atrium.docs.search', ['version' => $version->name], $this->contentCode($language));
    }

    /**
     * Where to edit the file shown, on GitHub. Released versions are fixed:
     * the fix goes to the branch and into the next release.
     */
    public function editUrl(string $file): string
    {
        return rtrim((string) config('atrium-docs.repository'), '/').'/edit/'.config('atrium-docs.branch', 'main').'/'.$file;
    }

    /**
     * A new file on GitHub for the translation of a page: README.md →
     * README.de.md beside it, the start page under docs/readme.
     */
    public function translateUrl(Page $page, string $language): string
    {
        $original = $page->original();
        $directory = $page->group === Page::INDEX ? 'docs/readme' : dirname($original);
        $name = pathinfo($original, PATHINFO_FILENAME).'.'.self::fileCode($language).'.md';

        return rtrim((string) config('atrium-docs.repository'), '/').'/new/'.config('atrium-docs.branch', 'main').'/'.$directory.'?filename='.rawurlencode($name);
    }

    /**
     * "pt-br" → "pt-BR", as the translations are named in the repository.
     */
    public static function fileCode(string $language): string
    {
        $parts = explode('-', self::code($language), 2);

        return isset($parts[1]) ? $parts[0].'-'.strtoupper($parts[1]) : $parts[0];
    }

    /**
     * The sidebar: the guides, then the modules, with their titles in the
     * language.
     *
     * @return array<string,array<int,array{slug:string,title:string,url:string}>>
     */
    public function sidebar(Version $version, string $language): array
    {
        $groups = [Page::GUIDE => [], Page::MODULE => []];

        foreach ($this->pages($version) as $page) {
            $group = $page->group === Page::INDEX ? Page::GUIDE : $page->group;
            $groups[$group][] = [
                'slug' => $page->slug,
                'title' => $this->title($version, $page, $language),
                'url' => $this->url($version, $page->slug, $language),
            ];
        }

        return array_filter($groups);
    }

    /**
     * Sections of the pages that hold every word of the query, best first:
     * a word in a heading weighs more than one in the text.
     *
     * @return array<int,array{page:string,title:string,heading:string,url:string,snippet:string}>
     */
    public function search(Version $version, string $language, string $query, int $limit = 20): array
    {
        $terms = array_unique(array_filter(preg_split('/\s+/u', mb_strtolower($query)) ?: [], fn (string $term): bool => mb_strlen($term) >= 2));

        if ($terms === []) {
            return [];
        }

        $results = [];

        foreach ($this->index($version, $language) as $entry) {
            $heading = mb_strtolower($entry['title'].' '.$entry['heading']);
            $text = mb_strtolower($entry['text']);
            $scores = [];

            foreach ($terms as $term) {
                $inHeading = substr_count($heading, $term);
                $inText = substr_count($text, $term);

                if ($inHeading + $inText === 0) {
                    continue 2;
                }

                $scores[] = 10 * $inHeading + min($inText, 5);
            }

            $results[] = [
                'score' => array_sum($scores),
                'page' => $entry['slug'],
                'title' => $entry['title'],
                'heading' => $entry['heading'],
                'url' => $this->url($version, $entry['slug'], $language).($entry['id'] !== '' ? '#'.$entry['id'] : ''),
                'snippet' => self::snippet($entry['text'], $terms),
            ];
        }

        usort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(function (array $result): array {
            unset($result['score']);

            return $result;
        }, array_slice($results, 0, $limit));
    }

    /**
     * Every section of every page in a language, as plain text.
     *
     * @return array<int,array{slug:string,title:string,id:string,heading:string,text:string}>
     */
    private function index(Version $version, string $language): array
    {
        $key = 'atrium-docs:index:'.md5(implode('|', [$version->fingerprint(), self::code($language)]));

        return $this->cache->rememberForever($key, function () use ($version, $language): array {
            $entries = [];

            foreach ($this->pages($version) as $page) {
                $rendered = $this->render($version, $page, $language);

                foreach ($rendered->sections as $section) {
                    $entries[] = ['slug' => $page->slug, 'title' => $rendered->title, ...$section];
                }
            }

            return $entries;
        });
    }

    /**
     * About 200 characters around the first word found, the words marked.
     *
     * @param  array<int,string>  $terms
     */
    private static function snippet(string $text, array $terms): string
    {
        $lower = mb_strtolower($text);
        $positions = [];

        foreach ($terms as $term) {
            $found = mb_strpos($lower, $term);

            if ($found !== false) {
                $positions[] = $found;
            }
        }

        $start = $positions === [] ? 0 : max(0, min($positions) - 60);
        $snippet = mb_substr($text, $start, 200);
        $snippet = ($start > 0 ? '…' : '').$snippet.(mb_strlen($text) > $start + 200 ? '…' : '');
        $escaped = e($snippet);

        foreach ($terms as $term) {
            $escaped = preg_replace('/('.preg_quote(e($term), '/').')/iu', '<mark>$1</mark>', $escaped) ?? $escaped;
        }

        return $escaped;
    }

    /**
     * A link or an image of a file, as the site should see it: a page of
     * the docs, a picture served from the version, or the file on GitHub.
     */
    private function resolve(Version $version, string $from, string $url, bool $image, string $language): string
    {
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) || str_starts_with($url, '//')) {
            return $url;
        }

        $anchor = '';

        if (($hash = strpos($url, '#')) !== false) {
            $anchor = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $path = self::normalize(dirname($from).'/'.rawurldecode($url));

        if ($path === null) {
            return $url.$anchor;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::IMAGES, true) && is_file($version->file($path))) {
            return route('atrium.docs.asset', ['version' => $version->name, 'path' => $path]);
        }

        if (! $image && $extension === 'md') {
            foreach ($this->pages($version) as $page) {
                foreach ($page->files as $fileLanguage => $file) {
                    if ($file === $path) {
                        // The original keeps the reader in the language of
                        // the page; a translation names its own.
                        return $this->url($version, $page->slug, $fileLanguage === self::SOURCE ? $language : $fileLanguage).$anchor;
                    }
                }
            }
        }

        $ref = $version->isRelease() ? $version->name : config('atrium-docs.branch', 'main');

        return rtrim((string) config('atrium-docs.repository'), '/').'/blob/'.$ref.'/'.$path.$anchor;
    }

    /**
     * "docs/../packages/faq/README.md" → "packages/faq/README.md"; null
     * for a path that leaves the repository.
     */
    public static function normalize(string $path): ?string
    {
        $parts = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }

                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    /**
     * Whether a file of a version may be served as a picture.
     */
    public function asset(Version $version, string $path): ?string
    {
        $path = self::normalize($path);

        if ($path === null || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGES, true)) {
            return null;
        }

        $file = $version->file($path);
        $real = realpath($file);
        $root = realpath($version->path);

        return $real !== false && $root !== false && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && is_file($real) ? $real : null;
    }

    /**
     * @return array<string,Page>
     */
    private function scan(Version $version): array
    {
        $pages = [];

        if (is_file($version->file('README.md'))) {
            $files = [self::SOURCE => 'README.md'];

            foreach (glob($version->file('docs/readme/README.*.md')) ?: [] as $file) {
                if (preg_match(self::FILE, basename($file), $match)) {
                    $files[self::code($match['lang'])] ??= 'docs/readme/'.basename($file);
                }
            }

            $pages[''] = new Page('', Page::INDEX, $files);
        }

        $guides = $this->group($version, 'docs/*.md', fn (string $name): string => strtolower($name), Page::GUIDE);
        $order = array_flip(array_map('strval', (array) config('atrium-docs.order', [])));
        uksort($guides, fn (string $a, string $b): int => [$order[$a] ?? PHP_INT_MAX, $a] <=> [$order[$b] ?? PHP_INT_MAX, $b]);

        $pages += $guides;

        if (is_file($version->file('CHANGELOG.md'))) {
            $pages['changelog'] = new Page('changelog', Page::GUIDE, [self::SOURCE => 'CHANGELOG.md']);
        }

        $modules = [];

        foreach (glob($version->file('packages/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $package = basename($directory);
            $found = $this->group($version, 'packages/'.$package.'/README*.md', fn (): string => 'modules/'.strtolower($package), Page::MODULE);

            if ($found !== [] && preg_match('/^[a-z0-9-]+$/i', $package)) {
                $modules += $found;
            }
        }

        ksort($modules);

        return $pages + $modules;
    }

    /**
     * @param  callable(string): string  $slug
     * @return array<string,Page>
     */
    private function group(Version $version, string $pattern, callable $slug, string $group): array
    {
        $files = [];

        foreach (glob($version->file($pattern)) ?: [] as $file) {
            if (! is_file($file) || ! preg_match(self::FILE, basename($file), $match)) {
                continue;
            }

            $language = ($match['lang'] ?? '') === '' ? self::SOURCE : self::code($match['lang']);
            $relative = dirname($pattern).'/'.basename($file);
            $files[$slug($match['name'])][$language] = $relative;
        }

        $pages = [];

        foreach ($files as $name => $languages) {
            if (isset($languages[self::SOURCE])) {
                $pages[$name] = new Page($name, $group, $languages);
            }
        }

        return $pages;
    }

    private function renderer(): Renderer
    {
        $theme = (array) config('atrium-docs.highlight', []);
        $light = (string) ($theme['light'] ?? 'atrium-light');
        $dark = $theme['dark'] ?? null;

        return new Renderer(is_string($dark) && $dark !== '' ? ['light' => $light, 'dark' => $dark] : $light);
    }

    /**
     * A file language ("pt-br") as the site's content language code; the
     * language itself when the site has no such language.
     */
    private function contentCode(?string $language): string
    {
        $language = self::code($language ?? app()->getLocale());

        foreach ($this->languages->codes() as $code) {
            if (self::code($code) === $language) {
                return $code;
            }
        }

        return $language;
    }

    private function urlRoot(): string
    {
        return (string) url('/');
    }
}
