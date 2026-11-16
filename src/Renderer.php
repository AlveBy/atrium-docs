<?php

declare(strict_types=1);

namespace Atrium\Docs;

use Closure;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\StringContainerHelper;
use Phiki\Adapters\CommonMark\PhikiExtension;
use Phiki\Phiki;

/**
 * Markdown of the repository → HTML of a page.
 *
 * GitHub-flavoured Markdown, ids on headings (the same anchors GitHub
 * makes, so "#fields" links keep working), code coloured on the server by
 * Phiki. The first "# Title" becomes the page title, the line of language
 * links under it goes (the site has its own switcher), and every link and
 * image goes through $resolve, which knows where a repository path lives
 * on the site.
 */
final class Renderer
{
    private ?Phiki $phiki = null;

    /**
     * @param  string|array<string,string>  $theme  a Phiki theme, or ['light' => …, 'dark' => …]
     */
    public function __construct(private readonly string|array $theme = 'atrium-light') {}

    /**
     * @param  Closure(string $url, bool $image): string  $resolve
     */
    public function render(string $markdown, Closure $resolve): Rendered
    {
        $title = '';

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'id_prefix' => '',
                'fragment_prefix' => '',
                'html_class' => 'docs-anchor',
                'insert' => 'after',
                'min_heading_level' => 2,
                'max_heading_level' => 4,
                'symbol' => '#',
                'aria_hidden' => true,
                'title' => '',
            ],
            'slug_normalizer' => ['unique' => 'document'],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new PhikiExtension($this->theme, $this->phiki()));

        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use (&$title, $resolve): void {
            $document = $event->getDocument();
            $title = $this->takeTitle($document);
            $this->dropLanguageLinks($document);

            foreach ($document->iterator() as $node) {
                if ($node instanceof Link || $node instanceof Image) {
                    $node->setUrl($resolve($node->getUrl(), $node instanceof Image));
                }
            }
        });

        $html = (string) (new MarkdownConverter($environment))->convert($markdown);
        // Wide tables scroll on their own instead of widening the page.
        $html = (string) preg_replace(['#<table>#', '#</table>#'], ['<div class="docs-table" tabindex="0"><table>', '</table></div>'], $html);

        [$headings, $sections] = $this->outline($html);

        return new Rendered($title, $html, $this->description($html), $headings, $sections);
    }

    /**
     * Phiki with the package's own themes: GitHub's colours, each token at
     * a contrast of 4.6:1 or more on the background (WCAG AA).
     */
    private function phiki(): Phiki
    {
        if ($this->phiki === null) {
            $this->phiki = new Phiki;

            foreach (['atrium-light', 'atrium-dark'] as $name) {
                $this->phiki->theme($name, __DIR__.'/../resources/themes/'.$name.'.json');
            }
        }

        return $this->phiki;
    }

    private function takeTitle(Document $document): string
    {
        foreach ($document->children() as $node) {
            if ($node instanceof Heading && $node->getLevel() === 1) {
                $title = trim(StringContainerHelper::getChildText($node));
                $node->detach();

                return $title;
            }
        }

        return '';
    }

    /**
     * "**English** · [Русский](modules.ru.md)" near the top: links between
     * translations of the file, which the site's own switcher replaces.
     */
    private function dropLanguageLinks(Document $document): void
    {
        $checked = 0;

        foreach ($document->children() as $node) {
            if (++$checked > 3) {
                return;
            }

            if ($node instanceof Paragraph && $this->isLanguageLinks($node)) {
                $node->detach();

                return;
            }
        }
    }

    private function isLanguageLinks(Paragraph $paragraph): bool
    {
        $links = 0;

        foreach ($paragraph->children() as $child) {
            if ($child instanceof Link) {
                if (! preg_match('/\.md(#.*)?$/', $child->getUrl())) {
                    return false;
                }

                $links++;
            } elseif ($child instanceof Text) {
                if (trim($child->getLiteral(), " ·\u{00A0}\t\n") !== '') {
                    return false;
                }
            } elseif (! $child instanceof Strong) {
                return false;
            }
        }

        return $links >= 1 && $paragraph->firstChild() !== null && $this->countChildren($paragraph) >= 3;
    }

    private function countChildren(Node $node): int
    {
        $count = 0;

        foreach ($node->children() as $ignored) {
            $count++;
        }

        return $count;
    }

    /**
     * The h2–h3 of the page for its contents, and the text under each
     * heading for the search.
     *
     * @return array{0:array<int,array{level:int,id:string,text:string}>,1:array<int,array{id:string,heading:string,text:string}>}
     */
    private function outline(string $html): array
    {
        $parts = preg_split('#(<h([2-4]) id="([^"]+)"[^>]*>.*?</h\2>)#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $headings = [];
        $sections = [['id' => '', 'heading' => '', 'text' => self::plain($parts[0])]];

        for ($i = 1; $i + 3 <= count($parts); $i += 4) {
            $text = self::plain($parts[$i]);
            $level = (int) $parts[$i + 1];
            $id = html_entity_decode($parts[$i + 2], ENT_QUOTES | ENT_HTML5);

            if ($level <= 3) {
                $headings[] = ['level' => $level, 'id' => $id, 'text' => $text];
            }

            $sections[] = ['id' => $id, 'heading' => $text, 'text' => self::plain($parts[$i + 3] ?? '')];
        }

        return [$headings, array_values(array_filter($sections, fn (array $section): bool => $section['heading'] !== '' || $section['text'] !== ''))];
    }

    private function description(string $html): string
    {
        if (! preg_match('#<p>(.*?)</p>#s', $html, $match)) {
            return '';
        }

        $text = self::plain($match[1]);

        return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157)).'…' : $text;
    }

    public static function plain(string $html): string
    {
        $html = (string) preg_replace('#<a [^>]*class="docs-anchor"[^>]*>.*?</a>#s', '', $html);
        $html = (string) preg_replace('#<(br|/p|/li|/h\d|/tr|/pre|/div)[^>]*>#', ' ', $html);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }
}
