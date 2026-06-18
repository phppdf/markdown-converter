<?php

declare(strict_types=1);

namespace PhpPdf\Markdown;

use PhpPdf\Builder\PdfDocumentBuilder;
use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Content\PdfContentStreamBuilder;

/**
 * Converts a Markdown string into pages appended to a PdfDocumentBuilder.
 *
 * Markdown is parsed directly into layout primitives (text, lists, tables)
 * on top of phppdf/phppdf — there is no intermediate HTML step. This is the
 * convenience entry point: it paginates the whole document for you,
 * appending pages to whatever PdfDocumentBuilder you pass in — which may
 * already have metadata, encryption, an outline, or pages of its own
 * (hand-crafted, or produced by the HTML converter) before you call this.
 *
 * If you need finer control — sharing a single page/stream with other
 * content, or driving your own pagination — use MarkdownFlow directly;
 * fromMarkdown() is implemented entirely in terms of it and adds nothing
 * but the pagination loop below.
 *
 * Font registration is your responsibility — see MarkdownConverterConfig's
 * class docblock for exactly what to register and why.
 *
 * Basic usage
 * ───────────
 *   $markdown = "# Hello World\n\nWelcome to the PDF.";
 *
 *   $builder = new PdfDocumentBuilder();
 *   $builder->globalFont('F1', 'Helvetica')
 *       ->globalFont('F2', 'Helvetica-Bold')
 *       ->globalFont('F3', 'Helvetica-Oblique')
 *       ->globalFont('F4', 'Helvetica-BoldOblique')
 *       ->globalFont('F5', 'Courier');
 *
 *   MarkdownConverter::fromMarkdown($markdown, new MarkdownConverterConfig(), $builder);
 *
 *   $output = new PdfMemoryOutput();
 *   (new PdfDocumentSerializer($output))->writeDocument($builder->build());
 *
 * Custom layout
 * ─────────────
 *   $config = new MarkdownConverterConfig();
 *   $config->setMarginTop(54)->setMarginBottom(54);
 *   $config->setBaseFontSize(10);
 *
 *   $builder = MarkdownConverter::fromMarkdown($markdown, $config, $builder);
 *
 * Supported Markdown
 * ───────────────────
 * ATX headings (# … ######) and Setext headings (underlined with `===` /
 * `---`), paragraphs, **bold**, *italic*, ***bold italic***, `inline code`,
 * fenced code blocks, unordered/ordered lists (with nested sub-lists and
 * GFM task-list checkboxes via `- [ ]` / `- [x]`), blockquotes (with nested
 * blockquotes), thematic breaks (---), GFM pipe tables (with column
 * alignment), clickable `[text](url)` hyperlinks, standalone `![alt](src)`
 * images (alone on their own paragraph; $src is a local file path — remote
 * URLs are not fetched), and raw HTML blocks (silently discarded — this
 * converter has no HTML renderer).
 *
 * Known limitations (v1)
 * ──────────────────────
 * - No loose lists — a blank line always ends the whole list, rather than
 *   separating multiple paragraphs within a single item.
 * - No footnotes.
 * - An image mixed inline with other text in the same paragraph renders as
 *   its alt text only — only a standalone image gets actually embedded.
 * - Links inside table cells render as their visible text only — not
 *   clickable (table cell layout isn't measured by this engine).
 * - Paragraphs, headings, fenced code blocks, and tables are split across
 *   pages when taller than one (tables repeat their header row on the
 *   continuation page). Lists, blockquotes, and images are never split —
 *   one taller than a full page is still placed anyway and may overflow.
 */
final class MarkdownConverter
{
    /**
     * Parses $markdown and appends the resulting pages to $builder.
     *
     * Always appends at least one page, even for empty/whitespace-only
     * Markdown, so PdfDocumentBuilder::build() never fails because of this
     * call. Returns $builder itself (the same instance) for chaining.
     *
     * Does not register any fonts — you must register a font under each of
     * $config's configured resource names yourself (directly, or via
     * $builder->globalFont() / globalEmbeddedFont()) before calling
     * $builder->build(). See MarkdownConverterConfig's class docblock.
     */
    public static function fromMarkdown(
        string $markdown,
        MarkdownConverterConfig $config,
        PdfDocumentBuilder $builder,
    ): PdfDocumentBuilder {
        $flow = MarkdownFlow::fromMarkdown($markdown, $config);

        $x = $config->getMarginLeft();
        $y = $config->getPageHeight() - $config->getMarginTop();
        $maxWidth = $config->contentWidth();
        $maxHeight = $config->contentHeight();

        do {
            $chunk = $flow->nextChunk($maxWidth, $maxHeight);

            $builder->page(static function (PdfPageBuilder $page) use ($config, $chunk, $x, $y): void {
                $page->size($config->getPageWidth(), $config->getPageHeight());
                $page->content(static function (PdfContentStreamBuilder $stream) use ($page, $chunk, $x, $y): void {
                    $chunk->draw($stream, $page, $x, $y);
                });
            });
        } while (!$flow->isEmpty());

        return $builder;
    }
}
