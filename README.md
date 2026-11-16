# Atrium Docs

Documentation pages for an [Atrium](https://github.com/AlveBy/atrium) site, made from the Markdown of a repository: the README, the guides in `docs/`, the changelog and the README of every package. It is what the Atrium site uses for its own documentation, and it works for any project that keeps its docs the same way.

- A sidebar of the guides and the modules, a contents list of the page, anchors on every heading (the same ones GitHub makes, so `guide.md#fields` links keep working).
- Code coloured on the server by [Phiki](https://github.com/phikiphp/phiki), with a light and a dark theme; every colour has a contrast of 4.5:1 or more (WCAG AA). No script.
- Search inside the docs, in the language of the page, with suggestions as you type.
- An "Edit this page on GitHub" link on every page.
- A version per release: `/docs/v0.2.0/installation`, with a notice on old versions.
- Pages in every content language of the site. A page without a translation is shown in English with a notice and a link to translate it.

## Install

```bash
composer require alveby/atrium-docs
php artisan vendor:publish --tag=atrium-docs-config
```

Then tell it where the Markdown is. For the copy of the repository the site runs from:

```dotenv
ATRIUM_DOCS_PATH=/var/www/atrium
ATRIUM_DOCS_REPOSITORY=https://github.com/you/project
```

The docs open at `/docs`.

## What becomes a page

| File | Page |
|---|---|
| `README.md` | `/docs/main`, the start page |
| `docs/installation.md` | `/docs/main/installation` |
| `CHANGELOG.md` | `/docs/main/changelog` |
| `packages/forms/README.md` | `/docs/main/modules/forms` |

The first `# Heading` of a file is the title of the page. The line of links between translations under it (`**English** · [Русский](installation.ru.md)`) is left out: the site has its own language switch.

Links between the files lead to their pages, pictures in the repository are served from it, and links to anything else (`../LICENSE`, a PHP file) go to the file on GitHub.

The guides follow `atrium-docs.order` in the sidebar, then the rest in alphabetical order; the packages come after them under "Modules".

## Languages

A translation sits beside the original with the language code in its name: `docs/installation.ru.md`, `packages/forms/README.de.md`. The README's translations live in `docs/readme/README.{lang}.md`. Codes are the site's content languages, in any case and with `-` or `_`: `pt-BR`, `pt_BR` and `pt-br` are one language.

With German on the site, `/de/docs/main/installation` shows `installation.de.md`, or the English text with a notice when there is none. Links inside the English text keep the reader on German pages. Such a page tells search engines that the English one is the original (`canonical`), and its `hreflang` lists only the languages the page is really written in.

## Versions

`main` is the folder named in `ATRIUM_DOCS_PATH`. A release is copied out of the git repository:

```bash
php artisan atrium:docs:pull v0.2.0
```

It clones the tag into `storage/app/atrium-docs` and keeps only the Markdown and the pictures. Run it again for `main` after each push if the site does not run from the repository itself. The newest release (the highest `vX.Y.Z`) is the version `/docs` opens; `ATRIUM_DOCS_DEFAULT=main` changes that.

Old versions say they are old and point their `canonical` to the same page of the current one. Links of a release lead to that tag on GitHub; "Edit" always leads to the branch, because a released text is fixed in the next release.

## Search

`/docs/main/search?q=field` lists the sections that contain every word of the query, the ones with the words in their heading first. The same address answers JSON to `Accept: application/json`, which is what the box in the sidebar uses for its suggestions. The index is built from the rendered pages and cached until a file changes.

## The look

The pages extend the theme's layout when `alveby/atrium-theme` is installed and a plain layout of their own otherwise; `atrium-docs.layout` names any other view. A layout gets `$metaTitle`, `$metaDescription`, `$canonical`, `$alternates` and `$switch`, and has to `@yield('content')` and `@stack('head')`.

The stylesheet is inline and takes its colours from the site's custom properties (`--text`, `--muted`, `--accent`, `--line`, `--surface`, `--focus`), so the docs follow the theme in light and in dark. Links are underlined and found words are bold and underlined: nothing depends on colour alone.

```bash
php artisan vendor:publish --tag=atrium-docs-views
php artisan vendor:publish --tag=atrium-docs-lang
```

The interface is translated into the 15 languages of the admin.

## Settings

`config/atrium-docs.php`:

| Key | Default | |
|---|---|---|
| `prefix` | `docs` | the address of the docs |
| `routes` | `true` | `false` leaves the routes to the app |
| `layout` | `null` | the view pages extend |
| `versions` | `main` → `ATRIUM_DOCS_PATH` | name → folder |
| `storage` | `storage/app/atrium-docs` | where `atrium:docs:pull` puts releases |
| `default` | newest release | the version `/docs` opens |
| `source` | the Atrium repository | what `atrium:docs:pull` clones |
| `repository`, `branch` | `AlveBy/atrium`, `main` | where "Edit" and file links lead |
| `order` | `installation`, `configuration`, `modules`, `bitrix` | the guides first in the sidebar |
| `highlight` | `atrium-light`, `atrium-dark` | Phiki themes, or one name for one theme |

## Tests

```bash
vendor/bin/phpunit packages/docs/tests
```

## License

MIT
