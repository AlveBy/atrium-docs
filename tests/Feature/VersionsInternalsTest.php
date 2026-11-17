<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Docs;
use Atrium\Docs\Page;
use Atrium\Docs\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Which folders are versions and in what order, what a name may be, how a
 * language is spelled, and which files of a repository are pages.
 */
final class VersionsInternalsTest extends TestCase
{
    /**
     * @return array<string,array{string,bool}>
     */
    public static function names(): array
    {
        return [
            'a branch' => ['main', true],
            'a tag' => ['v1.0.0', true],
            'a pre-release' => ['1.2.3-beta.1', true],
            'one letter' => ['a', true],
            'sixty-four characters' => ['x'.str_repeat('a', 63), true],
            'sixty-five characters' => ['x'.str_repeat('a', 64), false],
            'empty' => ['', false],
            'a space inside' => ['bad name', false],
            'a space at the end' => ['ok name', false],
            'a space in front' => [' abc', false],
            'two dots' => ['a..b', false],
            'a dash first' => ['-x', false],
            'a dot first' => ['.x', false],
            'a slash' => ['a/b', false],
        ];
    }

    #[DataProvider('names')]
    public function test_a_name_is_short_and_plain(string $name, bool $valid): void
    {
        $this->assertSame($valid, Docs::validName($name));
    }

    public function test_a_language_is_spelled_in_lower_case_with_a_dash_and_in_the_repository_with_a_capital_region(): void
    {
        $this->assertSame('pt-br', Docs::code('PT_br'));
        $this->assertSame('pt-br', Docs::code('pt-BR'));
        $this->assertSame('de', Docs::code('DE'));
        $this->assertSame('pt-BR', Docs::fileCode('pt_br'));
        $this->assertSame('de', Docs::fileCode('DE'));
        $this->assertSame('zh-HANT-TW', Docs::fileCode('zh-hant-tw'));
    }

    public function test_the_versions_of_the_config_and_of_the_storage_come_newest_release_first_then_the_branches_as_found(): void
    {
        foreach (['v0.9.0', 'v0.10.0', '2.0.0', 'v1.0.0', '9.9', 'bad name', 'main', 'v5.0.0.tmp'] as $name) {
            mkdir($this->storage.'/'.$name, 0777, true);
        }

        file_put_contents($this->storage.'/a-file', 'not a folder');
        config(['atrium-docs.versions' => [
            'main' => self::fixture().'/',
            'gone' => '/no/such/folder',
            'bad name' => self::fixture(),
            'next' => self::fixture(),
            'number' => 5,
        ]]);

        $versions = $this->app->make(Docs::class)->versions();

        $this->assertSame(['2.0.0', 'v1.0.0', 'v0.10.0', 'v0.9.0', 'main', 'next', '9.9'], array_keys($versions));
        $this->assertSame(self::fixture(), $versions['main']->path);
        $this->assertSame($this->storage.'/v0.9.0', $versions['v0.9.0']->path);
    }

    public function test_the_versions_are_looked_up_once(): void
    {
        $docs = $this->app->make(Docs::class);
        $this->assertSame(['main'], array_keys($docs->versions()));

        mkdir($this->storage.'/v9.9.9', 0777, true);

        $this->assertSame(['main'], array_keys($docs->versions()));

        $this->app->forgetInstance(Docs::class);
        $this->assertSame(['v9.9.9', 'main'], array_keys($this->app->make(Docs::class)->versions()));
    }

    public function test_no_versions_and_no_storage_in_the_config_leave_the_other_source(): void
    {
        mkdir($this->storage.'/v1.0.0', 0777, true);

        config(['atrium-docs.versions' => null]);
        $this->assertSame(['v1.0.0'], array_keys($this->app->make(Docs::class)->versions()));

        $this->app->forgetInstance(Docs::class);
        config(['atrium-docs.versions' => ['main' => self::fixture()], 'atrium-docs.storage' => null]);
        $this->assertSame(['main'], array_keys($this->app->make(Docs::class)->versions()));
    }

    public function test_the_pages_of_a_version_are_looked_up_once_and_found_with_or_without_slashes(): void
    {
        $docs = $this->docsOf(['README.md' => '# Start', 'docs/guide.md' => '# Guide']);
        $version = $docs->version('main');
        $this->assertNotNull($version);

        $this->assertSame(['', 'guide'], array_keys($docs->pages($version)));
        file_put_contents($version->file('docs/new.md'), '# New');
        $this->assertSame(['', 'guide'], array_keys($docs->pages($version)));

        $this->assertSame('guide', $docs->page($version, '/guide/')?->slug);
        $this->assertSame($docs->url($version, 'guide', 'en'), $docs->url($version, '/guide/', 'en'));
        $this->assertSame($this->root().'/docs/main/guide', $docs->url($version, '/guide/', 'en'));
        $this->assertSame($this->root().'/docs/main/search', $docs->searchUrl($version, 'en'));
    }

    public function test_the_pages_are_the_start_page_the_guides_in_the_configured_order_the_changelog_and_the_modules(): void
    {
        config(['atrium-docs.order' => [1.5, 'zeta', 'alpha']]);
        $docs = $this->docsOf([
            'README.md' => '# Start',
            'docs/readme/README.pt-br.md' => '# Inicio',
            'docs/readme/README.pt_BR.md' => '# Another',
            'docs/readme/README.final.md' => '# Not a language',
            'docs/alpha.md' => '# A',
            'docs/beta.md' => '# B',
            'docs/zeta.md' => '# Z',
            'docs/Setup.md' => '# Setup',
            'docs/lonely.de.md' => '# Without an original',
            'CHANGELOG.md' => '# Changes',
            'packages/Shop/README.md' => '# Shop',
            'packages/api/README.md' => '# Api',
            'packages/api/README.de.md' => '# Api de',
            'packages/my_pkg/README.md' => '# Not a name',
        ]);
        $version = $docs->version('main');
        $this->assertNotNull($version);

        $pages = $docs->pages($version);

        $this->assertSame(['', 'zeta', 'alpha', 'beta', 'setup', 'changelog', 'modules/api', 'modules/shop'], array_keys($pages));
        $this->assertSame(['en' => 'README.md', 'pt-br' => 'docs/readme/README.pt-br.md'], $pages['']->files);
        $this->assertSame(['en' => 'docs/Setup.md'], $pages['setup']->files);
        $this->assertEquals(['en' => 'packages/api/README.md', 'de' => 'packages/api/README.de.md'], $pages['modules/api']->files);
        $this->assertSame(Page::MODULE, $pages['modules/shop']->group);
        $this->assertSame(Page::GUIDE, $pages['changelog']->group);
        $this->assertSame(Page::INDEX, $pages['']->group);
    }

    public function test_the_sidebar_has_no_group_without_pages(): void
    {
        $docs = $this->docsOf(['README.md' => '# Start', 'docs/guide.md' => '# Guide']);
        $version = $docs->version('main');
        $this->assertNotNull($version);

        $this->assertSame([Page::GUIDE], array_keys($docs->sidebar($version, 'en')));
        $this->assertSame(['Start', 'Guide'], array_column($docs->sidebar($version, 'en')[Page::GUIDE], 'title'));

        $with = $this->app->make(Docs::class);
        config(['atrium-docs.versions' => ['main' => self::fixture()]]);
        $this->app->forgetInstance(Docs::class);
        $full = $this->app->make(Docs::class);
        $main = $full->version('main');
        $this->assertNotNull($main);

        $this->assertSame([Page::GUIDE, Page::MODULE], array_keys($full->sidebar($main, 'en')));
        $this->assertNotSame($with, $full);
    }

    public function test_a_path_is_made_plain_or_refused(): void
    {
        $this->assertSame('docs/guide.md', Docs::normalize('docs/./x/../guide.md'));
        $this->assertSame('docs/guide.md', Docs::normalize('docs\\guide.md'));
        $this->assertSame('a/b', Docs::normalize('//a///b/'));
        $this->assertNull(Docs::normalize('../x'));
        $this->assertNull(Docs::normalize('docs/../../x'));
        $this->assertNull(Docs::normalize('.'));
        $this->assertNull(Docs::normalize(''));
    }

    public function test_only_pictures_inside_the_version_are_served(): void
    {
        $docs = $this->docsOf(['README.md' => '# Start', 'docs/images/Shot.PNG' => 'png', 'docs/images/note.txt' => 'text']);
        $version = $docs->version('main');
        $this->assertNotNull($version);
        $outside = $version->path.'-other';
        mkdir($outside);
        file_put_contents($outside.'/secret.png', 'png');
        symlink($outside.'/secret.png', $version->path.'/docs/images/link.png');

        try {
            $this->assertSame(realpath($version->path.'/docs/images/Shot.PNG'), $docs->asset($version, 'docs/images/Shot.PNG'));
            $this->assertSame(realpath($version->path.'/docs/images/Shot.PNG'), $docs->asset($version, 'docs\\images\\Shot.PNG'));
            $this->assertNull($docs->asset($version, 'docs/images/note.txt'));
            $this->assertNull($docs->asset($version, 'docs/images/missing.png'));
            $this->assertNull($docs->asset($version, '../x.png'));
            $this->assertNull($docs->asset($version, 'docs/images/link.png'));
        } finally {
            unlink($outside.'/secret.png');
            rmdir($outside);
        }
    }
}
