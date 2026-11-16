{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
<aside class="docs-sidebar" aria-label="{{ __('atrium-docs::docs.contents') }}">
    <form class="docs-search" role="search" action="{{ $searchUrl }}" method="get" data-docs-search>
        <label for="docs-q">{{ __('atrium-docs::docs.search') }}</label>
        <input id="docs-q" type="search" name="q" value="{{ $query ?? '' }}" autocomplete="off" placeholder="{{ __('atrium-docs::docs.search_placeholder') }}" aria-controls="docs-suggest">
        <div id="docs-suggest" class="docs-suggest" role="region" aria-live="polite" hidden></div>
    </form>

    @if (count($versions) > 1)
        <details class="docs-versions">
            <summary>{{ __('atrium-docs::docs.version') }}: <strong>{{ $version->name }}</strong></summary>
            <ul>
                @foreach ($versions as $item)
                    <li><a href="{{ $item['url'] }}"{!! $item['current'] ? ' aria-current="true"' : '' !!}>{{ $item['name'] }}</a></li>
                @endforeach
            </ul>
        </details>
    @else
        <p class="docs-versions">{{ __('atrium-docs::docs.version') }}: <strong>{{ $version->name }}</strong></p>
    @endif

    @foreach ($sidebar as $group => $items)
        <nav class="docs-nav" aria-labelledby="docs-nav-{{ $group }}">
            <p id="docs-nav-{{ $group }}">{{ __('atrium-docs::docs.groups.'.$group) }}</p>
            <ul>
                @foreach ($items as $item)
                    <li><a href="{{ $item['url'] }}"{!! isset($page) && $page->slug === $item['slug'] ? ' aria-current="page"' : '' !!}>{{ $item['title'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    @endforeach
</aside>
<script>
(function () {
    var form = document.querySelector('[data-docs-search]');
    if (!form || !window.fetch) return;
    var input = form.querySelector('input'), box = form.querySelector('.docs-suggest'), timer, last = '';
    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var q = input.value.trim();
            if (q === last) return;
            last = q;
            if (q.length < 2) { box.hidden = true; box.innerHTML = ''; return; }
            fetch(form.action + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.query !== input.value.trim()) return;
                    box.innerHTML = '';
                    var list = document.createElement('ul');
                    data.results.slice(0, 8).forEach(function (r) {
                        var li = document.createElement('li'), a = document.createElement('a');
                        a.href = r.url;
                        a.textContent = r.title + (r.heading ? ' › ' + r.heading : '');
                        li.appendChild(a);
                        list.appendChild(li);
                    });
                    if (!data.results.length) {
                        var p = document.createElement('p');
                        p.textContent = {{ Js::from(__('atrium-docs::docs.nothing_short')) }};
                        box.appendChild(p);
                    } else {
                        box.appendChild(list);
                    }
                    box.hidden = false;
                })
                .catch(function () {});
        }, 180);
    });
    input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { box.hidden = true; } });
})();
</script>
