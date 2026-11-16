<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    /*
    | Where the documentation opens: /docs/{version}/{page}, and
    | /de/docs/{version}/{page} in the site's other content languages.
    | `routes` => false leaves the routes to the app.
    */
    'prefix' => env('ATRIUM_DOCS_PREFIX', 'docs'),

    'routes' => true,

    /*
    | The view every docs page extends. Null picks the theme's layout when
    | alveby/atrium-theme is installed and a plain one of this package
    | otherwise. A layout of your own gets the same variables as the theme's
    | (metaTitle, metaDescription, canonical, alternates, switch) and must
    | @yield('content') and @stack('head').
    */
    'layout' => null,

    /*
    | Versions of the documentation: a name and the root of a copy of the
    | repository (a folder with docs/, README.md and packages/*\/README.md).
    | Versions pulled with `php artisan atrium:docs:pull v0.2.0` are added
    | from `storage` by themselves, so a site usually lists nothing here.
    */
    'versions' => array_filter([
        'main' => env('ATRIUM_DOCS_PATH'),
    ]),

    'storage' => storage_path('app/atrium-docs'),

    /*
    | The version /docs opens. Null means the newest release (the highest
    | vX.Y.Z), or the first version listed when there is no release yet.
    */
    'default' => env('ATRIUM_DOCS_DEFAULT'),

    /*
    | The git repository `atrium:docs:pull` clones, and the GitHub page
    | the "Edit on GitHub" links lead to. Edits always go to `branch`:
    | a released version is fixed, its text is corrected in the next one.
    */
    'source' => env('ATRIUM_DOCS_SOURCE', 'https://github.com/AlveBy/atrium.git'),

    'repository' => env('ATRIUM_DOCS_REPOSITORY', 'https://github.com/AlveBy/atrium'),

    'branch' => env('ATRIUM_DOCS_BRANCH', 'main'),

    /*
    | The order of the guides in the sidebar, by file name in docs/. Guides
    | not listed follow in alphabetical order; the READMEs of packages/*
    | come after them under "Modules".
    */
    'order' => ['installation', 'configuration', 'modules', 'bitrix'],

    /*
    | Highlighting themes of Phiki (code blocks are coloured on the server,
    | no script). The dark one is used under prefers-color-scheme: dark or
    | [data-theme=dark]; null keeps one theme. atrium-light and atrium-dark
    | are GitHub's colours with every token at 4.6:1 or more (WCAG AA); any
    | theme Phiki ships may be named instead.
    */
    'highlight' => [
        'light' => 'atrium-light',
        'dark' => 'atrium-dark',
    ],
];
