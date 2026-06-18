<?php

declare(strict_types=1);

namespace PhpPdf\Markdown;

use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Content\PdfContentStreamBuilder;

/**
 * A pre-measured, drawable slice of Markdown content produced by
 * MarkdownFlow::nextChunk().
 *
 * The chunk already knows exactly which blocks it contains and how tall it
 * is. draw() needs both $stream (to emit content-stream operators) and
 * $page (to register page-level resources a block may need — an embedded
 * image via PdfPageBuilder::useImage(), or a clickable link via
 * addUriLink() — before the operators referencing them are emitted), so
 * call it with the same PdfPageBuilder the page's content() callback
 * belongs to. It is safe to call from inside that content() callback
 * (which phppdf runs lazily at PdfDocumentBuilder::build() time, while
 * $page itself was configured eagerly), even though the chunk itself was
 * decided eagerly while you were still laying out pages.
 */
final class MarkdownChunk
{
    /**
     * @param list<callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void> $renderOps
     */
    public function __construct(
        private readonly array $renderOps,
        private readonly float $height,
    ) {
    }

    /** Total height in points this chunk occupies once drawn. */
    public function getHeight(): float
    {
        return $this->height;
    }

    /** True when this chunk has no content to draw (e.g. the flow was already empty). */
    public function isEmpty(): bool
    {
        return $this->renderOps === [];
    }

    /**
     * Draws every block in this chunk into $stream, registering any page-level
     * resources (images, links) it needs on $page.
     *
     * ($x, $y) is the top-left of the region this chunk was measured against;
     * $y is the PDF y-coordinate of the TOP edge (origin bottom-left, units
     * in points) — typically the same value passed as $maxHeight's origin to
     * MarkdownFlow::nextChunk().
     */
    public function draw(PdfContentStreamBuilder $stream, PdfPageBuilder $page, float $x, float $y): void
    {
        foreach ($this->renderOps as $renderOp) {
            $renderOp($stream, $page, $x, $y);
        }
    }
}
