{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    The frame of docs pages when there is no theme and no layout of the
    app's own (atrium-docs.layout).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($metaTitle) && $metaTitle !== '' ? $metaTitle.' · ' : '' }}{{ config('app.name') }}</title>
    @if (! empty($metaDescription))
    <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if (! empty($canonical))
    <link rel="canonical" href="{{ $canonical }}">
    @endif
    <x-atrium::hreflang :urls="$alternates ?? []" />
    @stack('head')
</head>
<body class="docs-standalone">
    <main id="content">
        @if (count($switch ?? []) > 1)
            <nav class="docs-languages" aria-label="{{ __('atrium-docs::docs.language') }}">
                @foreach ($switch as $code => $url)
                    @php($language = app(\Atrium\Admin\Support\ContentLanguages::class)->find((string) $code))
                    @if ($language)
                        <a href="{{ $url }}" hreflang="{{ $language->hreflang() }}" lang="{{ $language->hreflang() }}" @if ($code === app()->getLocale()) aria-current="true" @endif>{{ $language->name }}</a>
                    @endif
                @endforeach
            </nav>
        @endif
        @yield('content')
    </main>
</body>
</html>
