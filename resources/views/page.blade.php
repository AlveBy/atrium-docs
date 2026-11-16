{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    A page of the documentation. Publish the views (vendor:publish
    --tag=atrium-docs-views) to change it; the frame comes from the layout
    in atrium-docs.layout.
--}}
@extends(\Atrium\Docs\DocsServiceProvider::layout())

@push('head')
<style>{!! $docsStyles !!}</style>
@endpush

@section('content')
<div class="docs">
    @include('atrium-docs::partials.sidebar')

    <article class="docs-body" lang="{{ $translated ? str_replace('_', '-', app()->getLocale()) : 'en' }}">
        @unless ($isDefaultVersion)
            <p class="docs-notice">{{ __('atrium-docs::docs.old_version', ['version' => $version->name]) }}
                @if ($canonical) <a href="{{ $canonical }}">{{ __('atrium-docs::docs.to_current') }}</a>@endif
            </p>
        @endunless

        @unless ($translated)
            <p class="docs-notice" lang="{{ str_replace('_', '-', app()->getLocale()) }}">{{ __('atrium-docs::docs.not_translated') }}
                <a href="{{ $translateUrl }}" rel="nofollow">{{ __('atrium-docs::docs.translate') }}</a>
            </p>
        @endunless

        <h1>{{ $rendered->title }}</h1>

        <div class="docs-content">{!! $rendered->html !!}</div>

        <footer class="docs-footer">
            <a class="docs-edit" href="{{ $editUrl }}" rel="nofollow">{{ __('atrium-docs::docs.edit') }}</a>

            @if ($neighbours['previous'] || $neighbours['next'])
                <nav class="docs-pager" aria-label="{{ __('atrium-docs::docs.pager') }}">
                    @if ($neighbours['previous'])
                        <a rel="prev" href="{{ $neighbours['previous']['url'] }}"><span>{{ __('atrium-docs::docs.previous') }}</span>{{ $neighbours['previous']['title'] }}</a>
                    @endif
                    @if ($neighbours['next'])
                        <a rel="next" href="{{ $neighbours['next']['url'] }}"><span>{{ __('atrium-docs::docs.next') }}</span>{{ $neighbours['next']['title'] }}</a>
                    @endif
                </nav>
            @endif
        </footer>
    </article>

    @if (count($rendered->headings) > 1)
        <nav class="docs-toc" aria-labelledby="docs-toc-title">
            <p id="docs-toc-title">{{ __('atrium-docs::docs.on_this_page') }}</p>
            <ul>
                @foreach ($rendered->headings as $heading)
                    <li class="docs-toc-{{ $heading['level'] }}"><a href="#{{ rawurlencode($heading['id']) }}">{{ $heading['text'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
@endsection
