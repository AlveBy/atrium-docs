@extends(\Atrium\Docs\DocsServiceProvider::layout())

@push('head')
<meta name="robots" content="noindex">
<style>{!! $docsStyles !!}</style>
@endpush

@section('content')
<div class="docs">
    @include('atrium-docs::partials.sidebar')

    <article class="docs-body">
        <h1>{{ __('atrium-docs::docs.search_title') }}</h1>

        @if ($query === '')
            <p>{{ __('atrium-docs::docs.search_hint') }}</p>
        @elseif ($results === [])
            <p role="status">{{ __('atrium-docs::docs.nothing_found', ['query' => $query]) }}</p>
        @else
            <p role="status">{{ trans_choice('atrium-docs::docs.found', count($results), ['count' => count($results), 'query' => $query]) }}</p>
            <ol class="docs-results">
                @foreach ($results as $result)
                    <li>
                        <a href="{{ $result['url'] }}">{{ $result['title'] }}@if ($result['heading'] !== '') <span>› {{ $result['heading'] }}</span>@endif</a>
                        <p>{!! $result['snippet'] !!}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </article>
</div>
@endsection
