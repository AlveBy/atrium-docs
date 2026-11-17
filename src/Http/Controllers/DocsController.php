<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Http\Controllers;

use Atrium\Admin\Support\ContentLanguages;
use Atrium\Docs\Docs;
use Atrium\Docs\DocsServiceProvider;
use Atrium\Docs\Page;
use Atrium\Docs\Version;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocsController extends Controller
{
    public function __construct(
        private readonly Docs $docs,
        private readonly ContentLanguages $languages,
    ) {}

    /**
     * /docs → the start page of the default version.
     */
    public function index(): RedirectResponse
    {
        $version = $this->docs->defaultVersion();

        abort_if($version === null, 404);

        return redirect()->to($this->docs->url($version, ''));
    }

    public function show(string $version, string $page = ''): View|RedirectResponse
    {
        $found = $this->docs->version($version);

        // /docs/installation, /docs/modules/faq: a page of the default
        // version without the version in the address.
        if ($found === null) {
            $default = $this->docs->defaultVersion();
            $slug = $version.'/'.$page;

            abort_unless($default !== null && $this->docs->page($default, $slug) !== null, 404);

            return redirect()->to($this->docs->url($default, $slug), 301);
        }

        $docPage = $this->docs->page($found, $page);

        abort_if($docPage === null, 404);

        $language = app()->getLocale();
        $rendered = $this->docs->render($found, $docPage, $language);
        $translated = Docs::code($language) === Docs::SOURCE || $docPage->has($language);

        return view('atrium-docs::page', [
            ...$this->frame($found, $language, fn (string $code): string => $this->docs->url($found, $docPage->slug, $code)),
            'page' => $docPage,
            'rendered' => $rendered,
            'metaTitle' => $rendered->title,
            'metaDescription' => $rendered->description,
            'canonical' => $this->canonical($found, $docPage, $translated),
            'alternates' => $this->alternates($found, $docPage),
            'translated' => $translated,
            'editUrl' => $this->docs->editUrl($docPage->file($language)),
            'translateUrl' => $translated ? null : $this->docs->translateUrl($docPage, $language),
            'neighbours' => $this->neighbours($found, $docPage, $language),
        ]);
    }

    public function search(Request $request, string $version): View|JsonResponse
    {
        $found = $this->docs->version($version);

        abort_if($found === null, 404);

        $query = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $language = app()->getLocale();
        $results = $query === '' ? [] : $this->docs->search($found, $language, $query);

        if ($request->wantsJson()) {
            return response()->json(['query' => $query, 'results' => $results]);
        }

        return view('atrium-docs::search', [
            ...$this->frame($found, $language, fn (string $code): string => $this->docs->searchUrl($found, $code).($query !== '' ? '?q='.rawurlencode($query) : '')),
            'query' => $query,
            'results' => $results,
            'metaTitle' => __('atrium-docs::docs.search_title'),
            'canonical' => null,
            'alternates' => [],
            'robots' => 'noindex',
        ]);
    }

    public function asset(string $version, string $path): BinaryFileResponse
    {
        $found = $this->docs->version($version);
        $file = $found === null ? null : $this->docs->asset($found, $path);

        abort_if($file === null, 404);

        return response()->file($file, [
            'Cache-Control' => $found->isRelease() ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }

    /**
     * What every docs page needs: the version, the other versions, the
     * sidebar, the search and the language switch.
     *
     * @param  callable(string): string  $url  the same page in a language
     * @return array<string,mixed>
     */
    private function frame(Version $version, string $language, callable $url): array
    {
        $versions = [];

        foreach ($this->docs->versions() as $other) {
            $versions[] = ['name' => $other->name, 'url' => $this->docs->url($other, ''), 'current' => $other->name === $version->name];
        }

        $switch = [];

        foreach ($this->languages->codes() as $code) {
            $switch[$code] = $url($code);
        }

        return [
            'version' => $version,
            'versions' => $versions,
            'isDefaultVersion' => $this->docs->defaultVersion() === $version,
            'sidebar' => $this->docs->sidebar($version, $language),
            'searchUrl' => $this->docs->searchUrl($version),
            'switch' => $switch,
            'docsStyles' => DocsServiceProvider::styles(),
        ];
    }

    /**
     * A page shown in English for want of a translation, or a page of an
     * older version, points search engines at the page they should index.
     */
    private function canonical(Version $version, Page $page, bool $translated): string
    {
        $default = $this->docs->defaultVersion() ?? $version;
        $target = $this->docs->page($default, $page->slug) !== null ? $default : $version;
        $language = $translated || ! $this->languages->has(Docs::SOURCE) ? app()->getLocale() : Docs::SOURCE;

        return $this->docs->url($target, $page->slug, $language);
    }

    /**
     * hreflang only for the languages the page is really written in.
     *
     * @return array<string,string>
     */
    private function alternates(Version $version, Page $page): array
    {
        $urls = [];

        foreach ($this->languages->codes() as $code) {
            if (Docs::code($code) === Docs::SOURCE || $page->has($code)) {
                $urls[$code] = $this->docs->url($version, $page->slug, $code);
            }
        }

        return $urls;
    }

    /**
     * @return array{previous:?array{title:string,url:string},next:?array{title:string,url:string}}
     */
    private function neighbours(Version $version, Page $page, string $language): array
    {
        $slugs = array_keys($this->docs->pages($version));
        $index = array_search($page->slug, $slugs, true);
        $link = function (?string $slug) use ($version, $language): ?array {
            $other = $slug === null ? null : $this->docs->page($version, $slug);

            return $other === null ? null : ['title' => $this->docs->title($version, $other, $language), 'url' => $this->docs->url($version, $other->slug, $language)];
        };

        return [
            'previous' => $link(is_int($index) && $index > 0 ? $slugs[$index - 1] : null),
            'next' => $link(is_int($index) ? ($slugs[$index + 1] ?? null) : null),
        ];
    }
}
