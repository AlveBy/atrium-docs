<?php

declare(strict_types=1);

namespace Atrium\Docs\Tests\Feature;

use Atrium\Docs\Tests\TestCase;

final class RenderingTest extends TestCase
{
    public function test_docs_open_on_the_start_page_of_the_default_version(): void
    {
        $this->get('/docs')->assertRedirect($this->root().'/docs/main');

        $this->get('/docs/main')
            ->assertOk()
            ->assertSee('<h1>Acme</h1>', false)
            ->assertSee('One admin for many sites.')
            ->assertSee('<title>Acme · Acme</title>', false);
    }

    public function test_a_guide_is_rendered_with_its_title_anchors_and_contents(): void
    {
        $response = $this->get('/docs/main/guide')->assertOk();

        $response
            ->assertSee('<h1>The guide</h1>', false)
            ->assertSee('<h2 id="start">Start', false)
            ->assertSee('<h2 id="fields">Fields', false)
            // Two headings with one text get different anchors, as on GitHub.
            ->assertSee('<h2 id="fields-1">Fields', false)
            ->assertSee('<h3 id="field-options">Field options', false)
            ->assertSee('<a href="#start">Start</a>', false)
            ->assertSee('<li class="docs-toc-2"><a href="#start">Start</a></li>', false)
            ->assertSee('<li class="docs-toc-3"><a href="#field-options">Field options</a></li>', false)
            ->assertSee('<meta name="description" content="A guide to everything. See the start and the license.">', false);

        // The links between translations of the file make way for the
        // site's own language switch.
        $response->assertDontSee('[English]', false)->assertDontSee('href="'.$this->root().'/docs/main/guide">English</a>', false);
    }

    public function test_code_is_highlighted_on_the_server(): void
    {
        $this->get('/docs/main/guide')
            ->assertOk()
            ->assertSee('<pre class="phiki language-shellscript atrium-light phiki-themes atrium-dark"', false)
            ->assertSee('data-language="php"', false)
            ->assertSee('--phiki-dark-color', false)
            ->assertDontSee('<script src', false);
    }

    public function test_links_lead_to_pages_of_the_site_pictures_and_github(): void
    {
        $root = $this->root();

        $this->get('/docs/main/guide')
            ->assertOk()
            ->assertSee('href="'.$root.'/docs/main/modules/shop#usage"', false)
            ->assertSee('href="'.$root.'/docs/main"', false)
            ->assertSee('href="https://github.com/acme/acme/blob/main/LICENSE"', false)
            ->assertSee('src="'.$root.'/docs/main/_assets/docs/images/admin.png"', false)
            ->assertSee('<div class="docs-table" tabindex="0"><table>', false);

        $this->get('/docs/main')
            ->assertSee('href="'.$root.'/docs/main/guide"', false)
            ->assertSee('src="'.$root.'/docs/main/_assets/docs/images/admin.png"', false);
    }

    public function test_pictures_are_served_and_nothing_else(): void
    {
        $this->get('/docs/main/_assets/docs/images/admin.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->get('/docs/main/_assets/docs/secret.txt')->assertNotFound();
        $this->get('/docs/main/_assets/docs/../../../composer.json')->assertNotFound();
        $this->get('/docs/main/_assets/docs/images/missing.png')->assertNotFound();
        $this->get('/docs/nope/_assets/docs/images/admin.png')->assertNotFound();
    }

    public function test_the_sidebar_lists_the_guides_then_the_modules(): void
    {
        $html = $this->get('/docs/main/about')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#Acme</a>.*The guide</a>.*About</a>.*Changelog</a>.*Acme Shop</a>#s', $html);
        $this->assertStringContainsString('<a href="'.$this->root().'/docs/main/about" aria-current="page">About</a>', $html);
    }

    public function test_every_page_links_to_its_source_on_github(): void
    {
        $this->get('/docs/main/guide')
            ->assertSee('href="https://github.com/acme/acme/edit/main/docs/guide.md"', false)
            ->assertSee('rel="next" href="'.$this->root().'/docs/main/about"', false)
            ->assertSee('rel="prev" href="'.$this->root().'/docs/main"', false);

        $this->get('/docs/main/modules/shop')
            ->assertOk()
            ->assertSee('<h1>Acme Shop</h1>', false)
            ->assertSee('href="https://github.com/acme/acme/edit/main/packages/shop/README.md"', false);
    }

    public function test_an_unknown_page_is_not_found(): void
    {
        $this->get('/docs/main/nothing')->assertNotFound();
        $this->get('/docs/main/modules/nothing')->assertNotFound();
        $this->get('/docs/nothing-here')->assertNotFound();
    }
}
