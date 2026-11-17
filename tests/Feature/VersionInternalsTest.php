<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;
use Atrium\Docs\Version;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What makes a version a release, where its files are and when its
 * fingerprint changes.
 */
final class VersionInternalsTest extends TestCase
{
    /**
     * @return array<string,array{string,bool}>
     */
    public static function names(): array
    {
        return [
            'a tag' => ['v1.2.3', true],
            'a tag without v' => ['1.2.3', true],
            'a pre-release' => ['v1.2.3-beta.1', true],
            'a pre-release with dashes' => ['1.2.3-rc-1', true],
            'several digits' => ['v10.20.30', true],
            'a branch' => ['main', false],
            'two numbers' => ['v1.2', false],
            'four numbers' => ['v1.2.3.4', false],
            'text in front' => ['xv1.2.3', false],
            'text behind' => ['v1.2.3x', false],
            'a dash and nothing' => ['v1.2.3-', false],
            'a blank in the tail' => ['v1.2.3-a b', false],
            'a line in front' => ["x\nv1.2.3", false],
            'a letter in the number' => ['v1.a.3', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('names')]
    public function test_a_release_is_a_tag_with_three_numbers(string $name, bool $release): void
    {
        $this->assertSame($release, (new Version($name, '/x'))->isRelease());
    }

    public function test_a_file_is_joined_to_the_path_with_one_slash(): void
    {
        $this->assertSame('/repo/docs/a.md', (new Version('main', '/repo'))->file('docs/a.md'));
        $this->assertSame('/repo/docs/a.md', (new Version('main', '/repo/'))->file('docs/a.md'));
        $this->assertSame('/repo/docs/a.md', (new Version('main', '/repo'))->file('/docs/a.md'));
        $this->assertSame('/repo/docs/a.md', (new Version('main', '/repo///'))->file('///docs/a.md'));
        $this->assertSame('/repo/a/b', (new Version('main', '/repo'))->file('a/b'));
        $this->assertSame('/rep/a', (new Version('main', '/rep/'))->file('a'));
    }

    /**
     * The fingerprint as it is meant: the path, then every page file by its
     * name, change time and size, in the order of the four places.
     */
    private function expected(string $root, array $files): string
    {
        $parts = [];

        foreach ($files as $file) {
            $parts[] = $root.'/'.$file.':'.filemtime($root.'/'.$file).':'.filesize($root.'/'.$file);
        }

        return md5($root.'|'.implode('|', $parts));
    }

    public function test_the_fingerprint_counts_the_pages_the_packages_and_the_readme_and_nothing_else(): void
    {
        $root = $this->repository([
            'README.md' => 'root',
            'docs/a.md' => 'one',
            'docs/b.md' => 'two',
            'docs/notes.txt' => 'not a page',
            'docs/readme/README.ru.md' => 'ru',
            'docs/deep/c.md' => 'too deep',
            'packages/shop/README.md' => 'shop',
            'packages/shop/README.ru.md' => 'shop ru',
            'packages/shop/CHANGELOG.md' => 'not a readme',
            'packages/shop/src/README.md' => 'too deep',
            'CHANGELOG.md' => 'not counted',
        ]);
        $folder = $root.'/docs/folder.md';
        mkdir($folder);

        $version = new Version('main', $root);

        $this->assertSame($this->expected($root, [
            'docs/a.md', 'docs/b.md', 'docs/readme/README.ru.md', 'packages/shop/README.md', 'packages/shop/README.ru.md', 'README.md',
        ]), $version->fingerprint());
    }

    public function test_the_fingerprint_changes_with_the_text_the_size_the_time_and_the_path(): void
    {
        $root = $this->repository(['README.md' => 'one']);
        $version = new Version('main', $root);
        $before = $version->fingerprint();

        $this->assertSame($before, $version->fingerprint());

        file_put_contents($root.'/README.md', 'one and more');
        $this->assertNotSame($before, $version->fingerprint());

        touch($root.'/README.md', filemtime($root.'/README.md') + 100);
        $this->assertSame($this->expected($root, ['README.md']), $version->fingerprint());

        $this->assertNotSame($version->fingerprint(), (new Version('main', $root.'/'))->fingerprint());
    }

    public function test_a_repository_without_any_page_has_the_fingerprint_of_its_path(): void
    {
        $this->assertSame(md5('/no/such|'), (new Version('x', '/no/such'))->fingerprint());
    }
}
