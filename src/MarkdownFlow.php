<?php

declare(strict_types=1);

namespace PhpPdf\Markdown;

use PhpPdf\Markdown\Internal\Block\HeadingBlock;
use PhpPdf\Markdown\Internal\Block\ListBlock;
use PhpPdf\Markdown\Internal\Block\ListItem;
use PhpPdf\Markdown\Internal\Inline\InlineParser;
use PhpPdf\Markdown\Internal\MarkdownLayoutEngine;
use PhpPdf\Markdown\Internal\MarkdownParser;

/**
 * Low-level, composable entry point for rendering Markdown — the
 * TableBuilder-style counterpart to MarkdownConverter::fromMarkdown().
 *
 * Where fromMarkdown() owns the whole PdfDocumentBuilder and paginates for
 * you, MarkdownFlow does neither: it only knows how to measure and draw the
 * Markdown it was given. You decide where pages come from, how big each
 * region is, what else shares the page or the document, and which fonts
 * back the resource names configured on MarkdownConverterConfig (see its
 * class docblock) — mix in hand-drawn content, the HTML converter,
 * headers/footers, or your own pagination logic.
 *
 * Usage — manual pagination:
 *
 *   $config = new MarkdownConverterConfig();
 *   $flow = MarkdownFlow::fromMarkdown($markdown, $config);
 *
 *   $builder = new PdfDocumentBuilder();
 *   $builder->globalFont('F1', 'Helvetica')
 *       ->globalFont('F2', 'Helvetica-Bold')
 *       ->globalFont('F3', 'Helvetica-Oblique')
 *       ->globalFont('F4', 'Helvetica-BoldOblique')
 *       ->globalFont('F5', 'Courier');
 *
 *   do {
 *       $chunk = $flow->nextChunk($config->contentWidth(), $config->contentHeight());
 *
 *       $builder->page(function (PdfPageBuilder $page) use ($config, $chunk): void {
 *           $page->size($config->getPageWidth(), $config->getPageHeight());
 *           $page->content(function (PdfContentStreamBuilder $stream) use ($page, $config, $chunk): void {
 *               $x = $config->getMarginLeft();
 *               $y = $config->getPageHeight() - $config->getMarginTop();
 *               $chunk->draw($stream, $page, $x, $y);
 *           });
 *       });
 *   } while (!$flow->isEmpty());
 *
 * Usage — sharing a page with other content (e.g. a fixed-size sidebar
 * already drawn by hand):
 *
 *   $chunk = $flow->nextChunk(maxWidth: 300, maxHeight: 400);
 *
 *   $page->content(function (PdfContentStreamBuilder $stream) use ($page, $chunk): void {
 *       // ... draw your own content first ...
 *       $chunk->draw($stream, $page, x: 72, y: 700);
 *   });
 *
 *   // $flow->isEmpty() tells you whether the rest needs to continue on
 *   // another page (or region) of your own choosing.
 *
 * MarkdownChunk::draw() always needs $page, even if your Markdown turns out
 * to contain no links or images — pass it the same PdfPageBuilder $page is
 * configured on, so any ![alt](src) images or [text](url) links it does
 * contain get their page-level resources (useImage() / addUriLink())
 * registered before the corresponding content-stream operators run.
 */
final class MarkdownFlow
{
    /** @var list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> */
    private array $blocks;

    /** @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks */
    private function __construct(array $blocks, private readonly MarkdownLayoutEngine $engine)
    {
        $this->blocks = $blocks;
    }

    /**
     * Parses $markdown and returns a flow ready to be drawn in chunks.
     *
     * Where any referenced footnotes end up is decided here, per
     * $config->getFootnotePlacement(): FootnotePlacement::PerPage (the
     * default) leaves them for the layout engine to place at the bottom of
     * whichever page first references each one; FootnotePlacement::DocumentEnd
     * appends them as one ordered list, headed by getFootnotesHeading(), at
     * the very end of $markdown instead.
     */
    public static function fromMarkdown(string $markdown, ?MarkdownConverterConfig $config = null): self
    {
        $config ??= new MarkdownConverterConfig();
        $parsed = MarkdownParser::parse($markdown);
        $blocks = $parsed->blocks;
        $footnoteDefinitions = [];

        if ($parsed->footnotes !== []) {
            if ($config->getFootnotePlacement() === FootnotePlacement::DocumentEnd) {
                $footnoteBlocks = self::documentEndFootnoteBlocks($parsed->footnotes, $config->getFootnotesHeading());
                $blocks = [...$blocks, ...$footnoteBlocks];
            } else {
                $footnoteDefinitions = $parsed->footnotes;
            }
        }

        return new self($blocks, new MarkdownLayoutEngine($config, $footnoteDefinitions));
    }

    /** True once every block has been drawn (or skipped, e.g. an empty paragraph). */
    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    /**
     * Measures and removes as many leading blocks as fit within
     * ($maxWidth, $maxHeight), returning them as a drawable MarkdownChunk.
     *
     * Call repeatedly with a fresh region (e.g. the next page's content
     * area) until isEmpty() to lay out the whole document. When the sole
     * leading block (a paragraph, heading, fenced code block, or table) is
     * still too tall for the *entire* $maxHeight, it is split — as much as
     * fits goes in this chunk, and the rest is picked up by the next call.
     * Other block types (lists, blockquotes, images, thematic breaks) are
     * never split — one alone taller than $maxHeight is still placed in
     * full and may overflow.
     */
    public function nextChunk(float $maxWidth, float $maxHeight): MarkdownChunk
    {
        return $this->engine->nextChunk($this->blocks, $maxWidth, $maxHeight);
    }

    /**
     * @param list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>> $footnotes
     * @return list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock>
     */
    private static function documentEndFootnoteBlocks(array $footnotes, string $heading): array
    {
        $items = array_map(static fn (array $runs): ListItem => new ListItem($runs), $footnotes);

        return [
            new HeadingBlock(2, InlineParser::parse($heading)),
            new ListBlock(true, $items),
        ];
    }
}
