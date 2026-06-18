<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal;

use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Color\Color;
use PhpPdf\Content\Matrix;
use PhpPdf\Content\PdfContentStreamBuilder;
use PhpPdf\Image\PdfImage;
use PhpPdf\Markdown\Internal\Block\BlockQuoteBlock;
use PhpPdf\Markdown\Internal\Block\CodeBlock;
use PhpPdf\Markdown\Internal\Block\HeadingBlock;
use PhpPdf\Markdown\Internal\Block\ImageBlock;
use PhpPdf\Markdown\Internal\Block\ListBlock;
use PhpPdf\Markdown\Internal\Block\ListItem;
use PhpPdf\Markdown\Internal\Block\MarkdownBlock;
use PhpPdf\Markdown\Internal\Block\ParagraphBlock;
use PhpPdf\Markdown\Internal\Block\TableAlignment;
use PhpPdf\Markdown\Internal\Block\TableBlock;
use PhpPdf\Markdown\Internal\Block\ThematicBreakBlock;
use PhpPdf\Markdown\Internal\Inline\InlineRun;
use PhpPdf\Markdown\MarkdownChunk;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PhpPdf\Table\TableBuilder;
use PhpPdf\Table\TableCell;
use PhpPdf\Table\TableRow;
use PhpPdf\Text\RichTextBox;
use PhpPdf\Text\TextAlign;
use PhpPdf\Text\TextBox;
use PhpPdf\Text\TextSpan;

use function assert;
use function spl_object_id;

/**
 * Measurement/rendering core for Markdown content.
 *
 * This engine never creates pages and never registers fonts on anything —
 * it only measures blocks and draws them into whatever
 * PdfContentStreamBuilder and region it is given, using the font resource
 * names and metrics configured on MarkdownConverterConfig. Registering
 * those resource names with real fonts (and creating/owning pages) is the
 * caller's responsibility; see MarkdownFlow and MarkdownConverterConfig.
 *
 * The page-break heuristic mirrors phppdf's own HTML converter: a chunk takes
 * as many leading blocks as fit within the requested height; a block taller
 * than the requested height is still placed (and may overflow) as long as
 * nothing else has been placed in the chunk yet. Blocks are never split
 * mid-content across a chunk boundary.
 *
 * Not quite stateless: when $footnoteDefinitions is non-empty (i.e.
 * FootnotePlacement::PerPage), the engine tracks which footnote numbers it
 * has already rendered across successive nextChunk() calls on the same
 * instance, so a footnote referenced again on a later page doesn't get its
 * definition repeated. One instance is scoped to one document (see
 * MarkdownFlow::fromMarkdown(), which constructs a fresh one every time),
 * so this never leaks across unrelated documents.
 *
 * @internal
 */
final class MarkdownLayoutEngine
{
    /** @var array<int, true> Footnote numbers already rendered on an earlier page. */
    private array $renderedFootnotes = [];

    /** @param list<list<InlineRun>> $footnoteDefinitions Definition runs, index 0 = footnote number 1. */
    public function __construct(
        private readonly MarkdownConverterConfig $config,
        private readonly array $footnoteDefinitions = [],
    ) {
    }

    /**
     * Measures and removes as many leading blocks from $blocks as fit within
     * $maxHeight, returning them as a self-contained, drawable MarkdownChunk.
     * $blocks is mutated in place — call repeatedly with a fresh region until
     * it is empty to lay out an entire document.
     *
     * A paragraph, heading, fenced code block, or table that is the first
     * block of the chunk and is still too tall for the *entire* $maxHeight
     * gets split: as much as fits is rendered here, and $blocks[0] becomes a
     * fresh block holding whatever didn't fit, picked up by the next call.
     * Other block types (lists, blockquotes, images, thematic breaks) are
     * never split — if one of those alone doesn't fit, it is still placed in
     * full and may overflow, exactly as before.
     *
     * When $footnoteDefinitions was given to the constructor, each
     * candidate block is also scanned for footnote references before it is
     * placed; the first time a given footnote number is seen, space for its
     * definition is reserved at the bottom of *this* chunk, shrinking the
     * budget available to content already being considered for it (see
     * footnoteAreaHeight()). A block is only ever rejected for not fitting,
     * never retroactively un-placed, so this never backtracks.
     *
     * @param list<MarkdownBlock> $blocks
     */
    public function nextChunk(array &$blocks, float $maxWidth, float $maxHeight): MarkdownChunk
    {
        $usedHeight = 0.0;
        $renderOps = [];
        $pageFootnotes = [];
        $footnoteAreaHeight = 0.0;

        while ($blocks !== []) {
            $block = $blocks[0];
            $measured = $this->measure($block, $maxWidth);

            if ($measured === null) {
                array_shift($blocks);

                continue;
            }

            [$height, $marginTop, $marginBottom] = $measured;

            $newFootnotes = $this->footnoteDefinitions === [] ? [] : $this->newFootnoteNumbers($block, $pageFootnotes);
            $tentativeFootnoteAreaHeight = $newFootnotes === []
                ? $footnoteAreaHeight
                : $this->footnoteAreaHeight([...array_keys($pageFootnotes), ...$newFootnotes], $maxWidth);
            $effectiveMaxHeight = $maxHeight - $tentativeFootnoteAreaHeight;

            if ($usedHeight > 0 && $usedHeight + $marginTop + $height > $effectiveMaxHeight) {
                break;
            }

            if ($usedHeight === 0.0 && $marginTop + $height > $effectiveMaxHeight) {
                $available = $effectiveMaxHeight - $marginTop;
                $split = $available > 0 ? $this->trySplit($block, $maxWidth, $available) : null;

                if ($split !== null) {
                    [$splitHeight, $renderOp, $remainder] = $split;
                    $renderOps[] = $this->offsetRenderOp($renderOp, $marginTop);
                    $blocks[0] = $remainder;
                    $usedHeight = $marginTop + $splitHeight;

                    foreach ($newFootnotes as $n) {
                        $pageFootnotes[$n] = true;
                    }

                    $footnoteAreaHeight = $tentativeFootnoteAreaHeight;

                    break;
                }
            }

            $usedHeight += $marginTop;
            $offsetFromTop = $usedHeight;

            $renderOps[] = $this->renderOpFor($block, $offsetFromTop, $maxWidth);

            $usedHeight += $height + $marginBottom;
            array_shift($blocks);

            foreach ($newFootnotes as $n) {
                $pageFootnotes[$n] = true;
            }

            $footnoteAreaHeight = $tentativeFootnoteAreaHeight;
        }

        if ($pageFootnotes !== []) {
            $numbers = array_keys($pageFootnotes);
            sort($numbers);

            $renderOps[] = $this->offsetRenderOp(
                $this->footnoteAreaRenderOp($numbers, $maxWidth),
                $maxHeight - $footnoteAreaHeight,
            );

            foreach ($numbers as $n) {
                $this->renderedFootnotes[$n] = true;
            }

            $usedHeight = $maxHeight;
        }

        return new MarkdownChunk($renderOps, $usedHeight);
    }

    /** @return callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void */
    private function renderOpFor(MarkdownBlock $block, float $offsetFromTop, float $maxWidth): callable
    {
        return $this->offsetRenderOp(
            function (
                PdfContentStreamBuilder $stream,
                PdfPageBuilder $page,
                float $x,
                float $y,
            ) use (
                $block,
                $maxWidth,
            ): void {
                $this->render($stream, $page, $block, $x, $y, $maxWidth);
            },
            $offsetFromTop,
        );
    }

    /**
     * @param callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void $renderOp
     * @return callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void
     */
    private function offsetRenderOp(callable $renderOp, float $offsetFromTop): callable
    {
        return function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $y,
        ) use (
            $renderOp,
            $offsetFromTop,
        ): void {
            $renderOp($stream, $page, $x, $y - $offsetFromTop);
        };
    }

    /**
     * Attempts to split $block so that a leading portion fits within
     * $availableHeight, returning [heightUsed, renderOp for that portion,
     * remainder block for the next chunk] — or null when $block's type
     * isn't splittable, or not even one line/row of it fits.
     *
     * @return array{
     *     0: float,
     *     1: callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void,
     *     2: MarkdownBlock,
     * }|null
     */
    private function trySplit(MarkdownBlock $block, float $contentWidth, float $availableHeight): ?array
    {
        $base = $this->config->getBaseFontSize();
        $lhMultiplier = $this->config->getLineHeightMultiplier();

        return match (true) {
            $block instanceof ParagraphBlock => $this->splitParagraph(
                $block,
                $contentWidth,
                $base,
                $lhMultiplier,
                $availableHeight,
            ),
            $block instanceof HeadingBlock => $this->splitHeading($block, $contentWidth, $availableHeight),
            $block instanceof CodeBlock => $this->splitCode($block, $contentWidth, $base, $availableHeight),
            $block instanceof TableBlock => $this->splitTable($block, $contentWidth, $base, $availableHeight),
            default => null,
        };
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measure(MarkdownBlock $block, float $contentWidth): ?array
    {
        $base = $this->config->getBaseFontSize();
        $lineHeightMultiplier = $this->config->getLineHeightMultiplier();

        return match (true) {
            $block instanceof HeadingBlock => $this->measureHeading($block, $contentWidth),
            $block instanceof ParagraphBlock => $this->measureParagraph(
                $block,
                $contentWidth,
                $base,
                $lineHeightMultiplier,
            ),
            $block instanceof ListBlock => $this->measureList($block, $contentWidth, $base, $lineHeightMultiplier),
            $block instanceof BlockQuoteBlock => $this->measureBlockQuote(
                $block,
                $contentWidth,
                $base,
                $lineHeightMultiplier,
            ),
            $block instanceof CodeBlock => $this->measureCode($block, $contentWidth, $base),
            $block instanceof ThematicBreakBlock => [1.0, $base * 0.6, $base * 0.6],
            $block instanceof TableBlock => $this->measureTable($block, $contentWidth, $base),
            $block instanceof ImageBlock => $this->measureImage($block, $contentWidth, $base),
            default => null,
        };
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measureHeading(HeadingBlock $block, float $contentWidth): ?array
    {
        if ($block->runs === []) {
            return null;
        }

        $fontSize = $this->config->headingFontSize($block->level);
        $box = $this->headingBox($block, $contentWidth, $fontSize);

        return [$box->getHeight(), max(8.0, $fontSize * 0.5), max(4.0, $fontSize * 0.3)];
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measureParagraph(
        ParagraphBlock $block,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): ?array {
        if ($block->runs === []) {
            return null;
        }

        $box = $this->richTextBox(
            $block->runs,
            $contentWidth,
            $base,
            $lhMultiplier,
            forceBold: false,
            forceItalic: false,
        );

        return [$box->getHeight(), 0.0, $base * 0.8];
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measureList(ListBlock $block, float $contentWidth, float $base, float $lhMultiplier): ?array
    {
        if ($block->items === []) {
            return null;
        }

        return [$this->listBlockHeight($block, $contentWidth, $base, $lhMultiplier), 0.0, $base * 0.8];
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measureBlockQuote(
        BlockQuoteBlock $block,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): ?array {
        if ($block->content === []) {
            return null;
        }

        $height = $this->blockQuoteContentHeight($block->content, $contentWidth, $base, $lhMultiplier);

        return [$height, $base * 0.4, $base * 0.8];
    }

    /** @param list<list<InlineRun>|BlockQuoteBlock> $content */
    private function blockQuoteContentHeight(
        array $content,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): float {
        $textWidth = max(1.0, $contentWidth - $this->config->getQuoteIndent());
        $paragraphSpacing = $base * 0.6;
        $height = 0.0;

        foreach ($content as $i => $item) {
            $height += $item instanceof BlockQuoteBlock
                ? $this->blockQuoteContentHeight($item->content, $textWidth, $base, $lhMultiplier)
                : $this->richTextBox($item, $textWidth, $base, $lhMultiplier, forceBold: false, forceItalic: true)
                    ->getHeight();

            if ($i < count($content) - 1) {
                $height += $paragraphSpacing;
            }
        }

        return $height;
    }

    /**
     * The line height of the very last rendered line within $content,
     * however deeply nested — used to compute how far the quote rule should
     * extend past the last paragraph's box (see renderBlockQuoteContent()).
     *
     * @param list<list<InlineRun>|BlockQuoteBlock> $content
     */
    private function lastLineHeight(array $content, float $contentWidth, float $base, float $lhMultiplier): float
    {
        $textWidth = max(1.0, $contentWidth - $this->config->getQuoteIndent());
        $last = $content[count($content) - 1];

        return $last instanceof BlockQuoteBlock
            ? $this->lastLineHeight($last->content, $textWidth, $base, $lhMultiplier)
            : $this->richTextBox($last, $textWidth, $base, $lhMultiplier, forceBold: false, forceItalic: true)
                ->getLineHeight();
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function measureCode(CodeBlock $block, float $contentWidth, float $base): array
    {
        $fontSize = $base * 0.9;
        $box = $this->codeBox($block, $contentWidth, $fontSize);
        $padding = $this->config->getTablePadding();

        return [$box->getHeight() + $padding * 2, $base * 0.5, $base * 0.8];
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private function measureTable(TableBlock $block, float $contentWidth, float $base): ?array
    {
        if ($block->header === []) {
            return null;
        }

        $rowHeights = $this->computeRowHeights($block, $contentWidth, $base);

        return [array_sum($rowHeights), $base * 0.4, $base * 0.8];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private function measureImage(ImageBlock $block, float $contentWidth, float $base): array
    {
        $image = PdfImage::fromFile($block->src);
        $height = $contentWidth * $image->getHeight() / $image->getWidth();

        return [$height, $base * 0.4, $base * 0.8];
    }

    private function render(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        MarkdownBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
    ): void {
        $base = $this->config->getBaseFontSize();
        $lhMultiplier = $this->config->getLineHeightMultiplier();

        match (true) {
            $block instanceof HeadingBlock => $this->renderHeading($stream, $page, $block, $x, $pdfYTop, $contentWidth),
            $block instanceof ParagraphBlock => $this->renderParagraph(
                $stream,
                $page,
                $block,
                $x,
                $pdfYTop,
                $contentWidth,
                $base,
                $lhMultiplier,
            ),
            $block instanceof ListBlock => $this->renderList(
                $stream,
                $page,
                $block,
                $x,
                $pdfYTop,
                $contentWidth,
                $base,
                $lhMultiplier,
            ),
            $block instanceof BlockQuoteBlock => $this->renderBlockQuote(
                $stream,
                $page,
                $block,
                $x,
                $pdfYTop,
                $contentWidth,
                $base,
                $lhMultiplier,
            ),
            $block instanceof CodeBlock => $this->renderCode($stream, $block, $x, $pdfYTop, $contentWidth, $base),
            $block instanceof ThematicBreakBlock => $this->renderThematicBreak($stream, $x, $pdfYTop, $contentWidth),
            $block instanceof TableBlock => $this->renderTable($stream, $block, $x, $pdfYTop, $contentWidth, $base),
            $block instanceof ImageBlock => $this->renderImage($stream, $page, $block, $x, $pdfYTop, $contentWidth),
            default => null,
        };
    }

    // ── Headings ─────────────────────────────────────────────────────────────

    private function headingBox(HeadingBlock $block, float $contentWidth, float $fontSize): RichTextBox
    {
        return $this->richTextBox(
            $block->runs,
            $contentWidth,
            $fontSize,
            $this->config->getLineHeightMultiplier(),
            forceBold: true,
            forceItalic: false,
        );
    }

    private function renderHeading(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        HeadingBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
    ): void {
        $fontSize = $this->config->headingFontSize($block->level);
        $box = $this->headingBox($block, $contentWidth, $fontSize);
        $baseline = $pdfYTop - $fontSize * 0.72;

        $stream->drawRichTextBox($box, $x, $baseline);
        $this->registerLinks(
            $page,
            $block->runs,
            $x,
            $pdfYTop,
            $contentWidth,
            $fontSize,
            $this->config->getLineHeightMultiplier(),
            forceBold: true,
            forceItalic: false,
        );
    }

    /**
     * @return array{
     *     0: float,
     *     1: callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void,
     *     2: MarkdownBlock,
     * }|null
     */
    private function splitHeading(HeadingBlock $block, float $contentWidth, float $availableHeight): ?array
    {
        $fontSize = $this->config->headingFontSize($block->level);
        $lhMultiplier = $this->config->getLineHeightMultiplier();
        $box = $this->headingBox($block, $contentWidth, $fontSize);
        $lineHeight = $box->getLineHeight();
        $maxLines = (int) floor($availableHeight / $lineHeight);
        $totalLines = count($box->getLines());

        if ($maxLines <= 0 || $maxLines >= $totalLines) {
            return null;
        }

        $tokens = $this->tokenizeRuns($block->runs, forceBold: true, forceItalic: false);
        $wrapped = $this->wrapTokens($tokens, $contentWidth, $fontSize);
        $remainderRuns = $this->reconstructRuns(array_slice($wrapped, $maxLines));

        if ($remainderRuns === []) {
            return null;
        }

        $visibleHeight = $maxLines * $lineHeight;

        $renderOp = function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $pdfYTop,
        ) use (
            $block,
            $box,
            $fontSize,
            $contentWidth,
            $lhMultiplier,
            $maxLines,
            $visibleHeight,
        ): void {
            $baseline = $pdfYTop - $fontSize * 0.72;

            $stream->drawRichTextBox($box, $x, $baseline, $visibleHeight + $box->getLineHeight() * 0.001);
            $this->registerLinks(
                $page,
                $block->runs,
                $x,
                $pdfYTop,
                $contentWidth,
                $fontSize,
                $lhMultiplier,
                forceBold: true,
                forceItalic: false,
                maxLines: $maxLines,
            );
        };

        return [$visibleHeight, $renderOp, new HeadingBlock($block->level, $remainderRuns)];
    }

    // ── Paragraphs ───────────────────────────────────────────────────────────

    private function renderParagraph(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        ParagraphBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): void {
        $box = $this->richTextBox(
            $block->runs,
            $contentWidth,
            $base,
            $lhMultiplier,
            forceBold: false,
            forceItalic: false,
        );
        $baseline = $pdfYTop - $base * 0.72;

        $stream->drawRichTextBox($box, $x, $baseline);
        $this->registerLinks(
            $page,
            $block->runs,
            $x,
            $pdfYTop,
            $contentWidth,
            $base,
            $lhMultiplier,
            forceBold: false,
            forceItalic: false,
        );
    }

    /**
     * @return array{
     *     0: float,
     *     1: callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void,
     *     2: MarkdownBlock,
     * }|null
     */
    private function splitParagraph(
        ParagraphBlock $block,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
        float $availableHeight,
    ): ?array {
        $box = $this->richTextBox(
            $block->runs,
            $contentWidth,
            $base,
            $lhMultiplier,
            forceBold: false,
            forceItalic: false,
        );
        $lineHeight = $box->getLineHeight();
        $maxLines = (int) floor($availableHeight / $lineHeight);
        $totalLines = count($box->getLines());

        if ($maxLines <= 0 || $maxLines >= $totalLines) {
            return null;
        }

        $tokens = $this->tokenizeRuns($block->runs, forceBold: false, forceItalic: false);
        $wrapped = $this->wrapTokens($tokens, $contentWidth, $base);
        $remainderRuns = $this->reconstructRuns(array_slice($wrapped, $maxLines));

        if ($remainderRuns === []) {
            return null;
        }

        $visibleHeight = $maxLines * $lineHeight;

        $renderOp = function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $pdfYTop,
        ) use (
            $block,
            $box,
            $contentWidth,
            $base,
            $lhMultiplier,
            $maxLines,
            $visibleHeight,
        ): void {
            $baseline = $pdfYTop - $base * 0.72;

            $stream->drawRichTextBox($box, $x, $baseline, $visibleHeight + $box->getLineHeight() * 0.001);
            $this->registerLinks(
                $page,
                $block->runs,
                $x,
                $pdfYTop,
                $contentWidth,
                $base,
                $lhMultiplier,
                forceBold: false,
                forceItalic: false,
                maxLines: $maxLines,
            );
        };

        return [$visibleHeight, $renderOp, new ParagraphBlock($remainderRuns)];
    }

    // ── Lists ────────────────────────────────────────────────────────────────

    /**
     * Measures the full height of $block, including every item's own text
     * (preserving inline emphasis — unlike phppdf's own ListBox, which only
     * accepts plain item strings) plus any nested sub-lists indented under
     * an item.
     */
    private function listBlockHeight(ListBlock $block, float $contentWidth, float $base, float $lhMultiplier): float
    {
        if ($block->items === []) {
            return 0.0;
        }

        $indent = $this->listIndent($block, $base);
        $textWidth = max(1.0, $contentWidth - $indent);
        $itemSpacing = $base * 0.2;
        $itemCount = count($block->items);
        $height = 0.0;

        foreach ($block->items as $i => $item) {
            $box = $this->richTextBox(
                $item->runs,
                $textWidth,
                $base,
                $lhMultiplier,
                forceBold: false,
                forceItalic: false,
            );
            $height += $box->getHeight();

            foreach ($item->children as $child) {
                $height += $itemSpacing + $this->listBlockHeight($child, $textWidth, $base, $lhMultiplier);
            }

            if ($i < $itemCount - 1) {
                $height += $itemSpacing;
            }
        }

        return $height;
    }

    /** Distance in points from the list's x position to the body text column. */
    private function listIndent(ListBlock $block, float $base): float
    {
        $metrics = $this->config->getRegularFontMetrics();

        if ($block->ordered) {
            $widestMarker = count($block->items) . '.';
            $markerWidth = $metrics->stringWidth($widestMarker) * $base / 1000;

            return $markerWidth + $base * 0.6;
        }

        if (self::hasTaskItem($block)) {
            $markerWidth = $metrics->stringWidth('[x]') * $base / 1000;

            return $markerWidth + $base * 0.6;
        }

        return $base * 2.0;
    }

    /** @return list<string> */
    private function listMarkers(ListBlock $block): array
    {
        $defaults = $block->ordered
            ? array_map(static fn (int $i): string => ($i + 1) . '.', array_keys($block->items))
            : array_fill(0, count($block->items), '•');

        return array_map(
            static fn (ListItem $item, string $default): string => match ($item->checked) {
                true => '[x]',
                false => '[ ]',
                null => $default,
            },
            $block->items,
            $defaults,
        );
    }

    /** Renders $block at $x starting from top $topY; returns the height it consumed. */
    private function renderListBlock(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        ListBlock $block,
        float $x,
        float $topY,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): float {
        if ($block->items === []) {
            return 0.0;
        }

        $indent = $this->listIndent($block, $base);
        $textWidth = max(1.0, $contentWidth - $indent);
        $textX = $x + $indent;
        $itemSpacing = $base * 0.2;
        $markers = $this->listMarkers($block);
        $fontName = $this->config->getRegularFontName();
        $itemCount = count($block->items);
        $currentTop = $topY;
        $consumed = 0.0;

        foreach ($block->items as $i => $item) {
            $box = $this->richTextBox(
                $item->runs,
                $textWidth,
                $base,
                $lhMultiplier,
                forceBold: false,
                forceItalic: false,
            );
            $baseline = $currentTop - $base * 0.72;

            $stream->beginText()
                ->setFont($fontName, $base)
                ->setTextMatrix(Matrix::translate($x, $baseline))
                ->showText($markers[$i])
                ->endText();

            $stream->drawRichTextBox($box, $textX, $baseline);
            $this->registerLinks(
                $page,
                $item->runs,
                $textX,
                $currentTop,
                $textWidth,
                $base,
                $lhMultiplier,
                forceBold: false,
                forceItalic: false,
            );

            $currentTop -= $box->getHeight();
            $consumed += $box->getHeight();

            foreach ($item->children as $child) {
                $currentTop -= $itemSpacing;
                $consumed += $itemSpacing;

                $childHeight = $this->renderListBlock(
                    $stream,
                    $page,
                    $child,
                    $textX,
                    $currentTop,
                    $textWidth,
                    $base,
                    $lhMultiplier,
                );

                $currentTop -= $childHeight;
                $consumed += $childHeight;
            }

            if ($i < $itemCount - 1) {
                $currentTop -= $itemSpacing;
                $consumed += $itemSpacing;
            }
        }

        return $consumed;
    }

    private function renderList(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        ListBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): void {
        $this->renderListBlock($stream, $page, $block, $x, $pdfYTop, $contentWidth, $base, $lhMultiplier);
    }

    // ── Blockquotes ──────────────────────────────────────────────────────────

    private function renderBlockQuote(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        BlockQuoteBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): void {
        $this->renderBlockQuoteContent(
            $stream,
            $page,
            $block->content,
            $x,
            $pdfYTop,
            $contentWidth,
            $base,
            $lhMultiplier,
        );
    }

    /**
     * Renders $content (a blockquote's paragraphs and nested blockquotes) at
     * $x starting from top $topY; returns the height it consumed. Recurses
     * for each nested BlockQuoteBlock, drawing that level's own rule
     * indented past the parent's.
     *
     * @param list<list<InlineRun>|BlockQuoteBlock> $content
     */
    private function renderBlockQuoteContent(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        array $content,
        float $x,
        float $topY,
        float $contentWidth,
        float $base,
        float $lhMultiplier,
    ): float {
        $quoteIndent = $this->config->getQuoteIndent();
        $textX = $x + $quoteIndent;
        $textWidth = max(1.0, $contentWidth - $quoteIndent);
        $paragraphSpacing = $base * 0.6;

        $totalHeight = $this->blockQuoteContentHeight($content, $contentWidth, $base, $lhMultiplier);
        $lastLineHeight = $this->lastLineHeight($content, $contentWidth, $base, $lhMultiplier);

        // The rule should span the glyphs themselves (cap-height of the first
        // line through the descender of the last), not the full line-height
        // box — which would overshoot below the last line whenever
        // lineHeightMultiplier > 1, making the rule look taller than the text.
        $ruleHeight = $totalHeight - $lastLineHeight + $base * 0.92;

        $stream->strokeColor($this->config->getQuoteRuleColor())
            ->setLineWidth(2.0)
            ->moveTo($x, $topY)
            ->lineTo($x, $topY - $ruleHeight)
            ->stroke();

        $currentTop = $topY;
        $itemCount = count($content);

        foreach ($content as $i => $item) {
            if ($item instanceof BlockQuoteBlock) {
                $currentTop -= $this->renderBlockQuoteContent(
                    $stream,
                    $page,
                    $item->content,
                    $textX,
                    $currentTop,
                    $textWidth,
                    $base,
                    $lhMultiplier,
                );
            } else {
                $box = $this->richTextBox($item, $textWidth, $base, $lhMultiplier, forceBold: false, forceItalic: true);
                $baseline = $currentTop - $base * 0.72;

                $stream->drawRichTextBox($box, $textX, $baseline);
                $this->registerLinks(
                    $page,
                    $item,
                    $textX,
                    $currentTop,
                    $textWidth,
                    $base,
                    $lhMultiplier,
                    forceBold: false,
                    forceItalic: true,
                );

                $currentTop -= $box->getHeight();
            }

            if ($i < $itemCount - 1) {
                $currentTop -= $paragraphSpacing;
            }
        }

        return $topY - $currentTop;
    }

    // ── Fenced code blocks ───────────────────────────────────────────────────

    private function codeBox(CodeBlock $block, float $contentWidth, float $fontSize): TextBox
    {
        return TextBox::create(
            text: $block->code,
            metrics: $this->config->getCodeFontMetrics(),
            fontSize: $fontSize,
            maxWidth: max(1.0, $contentWidth - $this->config->getTablePadding() * 2),
            lineHeight: $fontSize * 1.3,
        );
    }

    private function renderCode(
        PdfContentStreamBuilder $stream,
        CodeBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
        float $base,
    ): void {
        $fontSize = $base * 0.9;
        $box = $this->codeBox($block, $contentWidth, $fontSize);
        $padding = $this->config->getTablePadding();
        $totalHeight = $box->getHeight() + $padding * 2;
        $baseline = $pdfYTop - $padding - $fontSize * 0.72;

        $stream->fillColor($this->config->getCodeBackgroundColor())
            ->rectangle($x, $pdfYTop - $totalHeight, $contentWidth, $totalHeight)
            ->fill();

        $stream->fillColor(Color::fromHex('#000000'));
        $stream->drawTextBox($box, $this->config->getCodeFontName(), $x + $padding, $baseline);
    }

    /**
     * @return array{
     *     0: float,
     *     1: callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void,
     *     2: MarkdownBlock,
     * }|null
     */
    private function splitCode(CodeBlock $block, float $contentWidth, float $base, float $availableHeight): ?array
    {
        $fontSize = $base * 0.9;
        $box = $this->codeBox($block, $contentWidth, $fontSize);
        $padding = $this->config->getTablePadding();
        $lineHeight = $box->getLineHeight();
        $totalLines = count($box->getLines());
        $maxLines = (int) floor(($availableHeight - $padding * 2) / $lineHeight);

        if ($maxLines <= 0 || $maxLines >= $totalLines) {
            return null;
        }

        $remainderText = implode("\n", array_slice($box->getLines(), $maxLines));

        if (trim($remainderText) === '') {
            return null;
        }

        $visibleHeight = $maxLines * $lineHeight + $padding * 2;

        $renderOp = function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $pdfYTop,
        ) use (
            $box,
            $contentWidth,
            $padding,
            $fontSize,
            $maxLines,
            $lineHeight,
            $visibleHeight,
        ): void {
            $baseline = $pdfYTop - $padding - $fontSize * 0.72;

            $stream->fillColor($this->config->getCodeBackgroundColor())
                ->rectangle($x, $pdfYTop - $visibleHeight, $contentWidth, $visibleHeight)
                ->fill();

            $stream->fillColor(Color::fromHex('#000000'));
            $stream->drawTextBox(
                $box,
                $this->config->getCodeFontName(),
                $x + $padding,
                $baseline,
                $maxLines * $lineHeight + $lineHeight * 0.001,
            );
        };

        return [$visibleHeight, $renderOp, new CodeBlock($remainderText, $block->language)];
    }

    // ── Thematic breaks ──────────────────────────────────────────────────────

    private function renderThematicBreak(
        PdfContentStreamBuilder $stream,
        float $x,
        float $pdfYTop,
        float $contentWidth,
    ): void {
        $y = $pdfYTop - 0.5;

        $stream->strokeColor(Color::fromHex('#cccccc'))
            ->setLineWidth(1.0)
            ->moveTo($x, $y)
            ->lineTo($x + $contentWidth, $y)
            ->stroke();
    }

    // ── Tables ───────────────────────────────────────────────────────────────

    /** @return list<float> */
    private function computeRowHeights(TableBlock $block, float $contentWidth, float $base): array
    {
        $colCount = count($block->header);

        if ($colCount === 0) {
            return [];
        }

        $padding = $this->config->getTablePadding();
        $colWidth = $contentWidth / $colCount;
        $pt = $padding + $base * 0.72;
        $pb = $padding + $base * 0.20;
        $rows = [$block->header, ...$block->rows];
        $heights = [];

        foreach ($rows as $row) {
            $rowHeight = 0.0;

            foreach (array_slice($row, 0, $colCount) as $cellRuns) {
                $cellBox = $this->richTextBox(
                    $cellRuns,
                    max(1.0, $colWidth - $padding * 2),
                    $base,
                    1.2,
                    forceBold: false,
                    forceItalic: false,
                );
                $minHeight = $pt + max(0.0, $cellBox->getHeight() - $cellBox->getLineHeight()) + $pb;
                $rowHeight = max($rowHeight, $minHeight);
            }

            $heights[] = $rowHeight;
        }

        return $heights;
    }

    private function renderTable(
        PdfContentStreamBuilder $stream,
        TableBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
        float $base,
    ): void {
        $colCount = count($block->header);

        if ($colCount === 0) {
            return;
        }

        $colWidth = $contentWidth / $colCount;
        $columns = array_fill(0, $colCount, $colWidth);

        $builder = TableBuilder::create($x, $pdfYTop)
            ->columns($columns)
            ->paddingAll($this->config->getTablePadding())
            ->border(Color::fromHex('#cccccc'))
            ->font($this->config->getRegularFontName(), $base, $this->config->getRegularFontMetrics());

        $headerCells = [];

        foreach ($block->header as $columnIndex => $cellRuns) {
            $headerCells[] = TableCell::spans($this->cellSpans($cellRuns, $base, forceBold: true))
                ->align($this->textAlignFor($block->alignments, $columnIndex))
                ->background(Color::fromHex('#e8e8e8'));
        }

        $builder->addRow(TableRow::cells($headerCells));

        foreach ($block->rows as $row) {
            $cells = [];

            foreach (array_slice($row, 0, $colCount) as $columnIndex => $cellRuns) {
                $cells[] = TableCell::spans($this->cellSpans($cellRuns, $base, forceBold: false))
                    ->align($this->textAlignFor($block->alignments, $columnIndex));
            }

            $builder->addRow(TableRow::cells($cells));
        }

        $builder->draw($stream);
    }

    /**
     * Splits $block between rows: as many body rows as fit after the header
     * go in this chunk; the rest become a fresh TableBlock — repeating the
     * same header and alignments — for the next one.
     *
     * @return array{
     *     0: float,
     *     1: callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void,
     *     2: MarkdownBlock,
     * }|null
     */
    private function splitTable(TableBlock $block, float $contentWidth, float $base, float $availableHeight): ?array
    {
        $rowHeights = $this->computeRowHeights($block, $contentWidth, $base);

        if ($rowHeights === []) {
            return null;
        }

        $headerHeight = $rowHeights[0];
        $bodyRowHeights = array_slice($rowHeights, 1);
        $rowCount = count($bodyRowHeights);

        if ($rowCount === 0) {
            return null;
        }

        $usedHeight = $headerHeight;
        $rowsThatFit = 0;

        foreach ($bodyRowHeights as $rowHeight) {
            if ($usedHeight + $rowHeight > $availableHeight) {
                break;
            }

            $usedHeight += $rowHeight;
            $rowsThatFit++;
        }

        if ($rowsThatFit <= 0 || $rowsThatFit >= $rowCount) {
            return null;
        }

        $visibleBlock = new TableBlock($block->header, $block->alignments, array_slice($block->rows, 0, $rowsThatFit));
        $remainderBlock = new TableBlock($block->header, $block->alignments, array_slice($block->rows, $rowsThatFit));

        $renderOp = function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $pdfYTop,
        ) use (
            $visibleBlock,
            $contentWidth,
            $base,
        ): void {
            $this->renderTable($stream, $visibleBlock, $x, $pdfYTop, $contentWidth, $base);
        };

        return [$usedHeight, $renderOp, $remainderBlock];
    }

    /**
     * @param list<InlineRun> $runs
     * @return list<TextSpan>
     */
    private function cellSpans(array $runs, float $fontSize, bool $forceBold): array
    {
        $spans = [];

        foreach ($runs as $run) {
            [$fontName, $metrics] = $this->fontFor($run, $forceBold, false);
            $spans[] = TextSpan::create($run->text, $fontName, $fontSize, $metrics);
        }

        return $spans;
    }

    /** @param list<TableAlignment> $alignments */
    private function textAlignFor(array $alignments, int $columnIndex): TextAlign
    {
        return match ($alignments[$columnIndex] ?? TableAlignment::Left) {
            TableAlignment::Center => TextAlign::Center,
            TableAlignment::Right => TextAlign::Right,
            TableAlignment::Left => TextAlign::Left,
        };
    }

    // ── Images ───────────────────────────────────────────────────────────────

    private function renderImage(
        PdfContentStreamBuilder $stream,
        PdfPageBuilder $page,
        ImageBlock $block,
        float $x,
        float $pdfYTop,
        float $contentWidth,
    ): void {
        $image = PdfImage::fromFile($block->src);
        $height = $contentWidth * $image->getHeight() / $image->getWidth();
        $localName = 'MdImg' . spl_object_id($block);

        $page->useImage($localName, $image);
        $stream->drawImage($localName, $x, $pdfYTop - $height, $contentWidth, $height);
    }

    // ── Footnotes (FootnotePlacement::PerPage) ──────────────────────────────

    /**
     * Footnote numbers $block references that haven't already been placed
     * on this page (via $pageFootnotes) or an earlier one (via
     * $this->renderedFootnotes).
     *
     * @param array<int, true> $pageFootnotes
     * @return list<int>
     */
    private function newFootnoteNumbers(MarkdownBlock $block, array $pageFootnotes): array
    {
        $numbers = [];

        foreach (array_unique($this->footnoteNumbersIn($block)) as $n) {
            if (!isset($pageFootnotes[$n]) && !isset($this->renderedFootnotes[$n])) {
                $numbers[] = $n;
            }
        }

        return $numbers;
    }

    /**
     * Recursively collects every footnote number referenced anywhere in
     * $block. Scoped to the whole block even when it might end up split
     * across pages (see the class docblock's note on this simplification).
     *
     * @return list<int>
     */
    private function footnoteNumbersIn(MarkdownBlock $block): array
    {
        return match (true) {
            $block instanceof HeadingBlock => self::footnoteNumbersInRuns($block->runs),
            $block instanceof ParagraphBlock => self::footnoteNumbersInRuns($block->runs),
            $block instanceof ListBlock => $this->footnoteNumbersInList($block),
            $block instanceof BlockQuoteBlock => $this->footnoteNumbersInBlockQuoteContent($block->content),
            $block instanceof TableBlock => $this->footnoteNumbersInTable($block),
            default => [],
        };
    }

    /** @return list<int> */
    private function footnoteNumbersInList(ListBlock $block): array
    {
        $numbers = [];

        foreach ($block->items as $item) {
            array_push($numbers, ...self::footnoteNumbersInRuns($item->runs));

            foreach ($item->children as $child) {
                array_push($numbers, ...$this->footnoteNumbersInList($child));
            }
        }

        return $numbers;
    }

    /**
     * @param list<list<InlineRun>|BlockQuoteBlock> $content
     * @return list<int>
     */
    private function footnoteNumbersInBlockQuoteContent(array $content): array
    {
        $numbers = [];

        foreach ($content as $item) {
            array_push($numbers, ...($item instanceof BlockQuoteBlock
                ? $this->footnoteNumbersInBlockQuoteContent($item->content)
                : self::footnoteNumbersInRuns($item)));
        }

        return $numbers;
    }

    /** @return list<int> */
    private function footnoteNumbersInTable(TableBlock $block): array
    {
        $numbers = [];

        foreach ([$block->header, ...$block->rows] as $row) {
            foreach ($row as $cellRuns) {
                array_push($numbers, ...self::footnoteNumbersInRuns($cellRuns));
            }
        }

        return $numbers;
    }

    /**
     * Width in points of the widest "N." marker among $numbers, at $fontSize.
     *
     * @param non-empty-list<int> $numbers
     */
    private function footnoteMarkerWidth(array $numbers, float $fontSize): float
    {
        $metrics = $this->config->getRegularFontMetrics();
        $widest = max($numbers) . '.';

        return $metrics->stringWidth($widest) * $fontSize / 1000;
    }

    /**
     * Height in points of the footnote area for $numbers (sorted ascending):
     * a separator rule plus each one's definition text, smaller than body
     * text per typesetting convention.
     *
     * @param list<int> $numbers
     */
    private function footnoteAreaHeight(array $numbers, float $contentWidth): float
    {
        if ($numbers === []) {
            return 0.0;
        }

        $base = $this->config->getBaseFontSize();
        $fontSize = $base * 0.85;
        $indent = $this->footnoteMarkerWidth($numbers, $fontSize) + $fontSize * 0.5;
        $textWidth = max(1.0, $contentWidth - $indent);
        $itemSpacing = $fontSize * 0.3;
        $height = $base * 0.6 + $base * 0.4;
        $count = count($numbers);

        foreach ($numbers as $i => $n) {
            $box = $this->richTextBox(
                $this->footnoteDefinitions[$n - 1] ?? [new InlineRun('(undefined)')],
                $textWidth,
                $fontSize,
                1.2,
                forceBold: false,
                forceItalic: false,
            );
            $height += $box->getHeight();

            if ($i < $count - 1) {
                $height += $itemSpacing;
            }
        }

        return $height;
    }

    /**
     * @param non-empty-list<int> $numbers
     * @return callable(PdfContentStreamBuilder, PdfPageBuilder, float, float): void
     */
    private function footnoteAreaRenderOp(array $numbers, float $contentWidth): callable
    {
        return function (
            PdfContentStreamBuilder $stream,
            PdfPageBuilder $page,
            float $x,
            float $areaTop,
        ) use (
            $numbers,
            $contentWidth,
): void {
            $this->renderFootnoteArea($stream, $numbers, $x, $areaTop, $contentWidth);
        };
    }

    /** @param non-empty-list<int> $numbers */
    private function renderFootnoteArea(
        PdfContentStreamBuilder $stream,
        array $numbers,
        float $x,
        float $areaTop,
        float $contentWidth,
    ): void {
        $base = $this->config->getBaseFontSize();
        $fontSize = $base * 0.85;
        $indent = $this->footnoteMarkerWidth($numbers, $fontSize) + $fontSize * 0.5;
        $textWidth = max(1.0, $contentWidth - $indent);
        $itemSpacing = $fontSize * 0.3;
        $fontName = $this->config->getRegularFontName();

        $ruleY = $areaTop - $base * 0.6;

        $stream->strokeColor($this->config->getQuoteRuleColor())
            ->setLineWidth(0.75)
            ->moveTo($x, $ruleY)
            ->lineTo($x + min($contentWidth, 150.0), $ruleY)
            ->stroke();

        $currentTop = $ruleY - $base * 0.4;

        foreach ($numbers as $n) {
            $box = $this->richTextBox(
                $this->footnoteDefinitions[$n - 1] ?? [new InlineRun('(undefined)')],
                $textWidth,
                $fontSize,
                1.2,
                forceBold: false,
                forceItalic: false,
            );
            $baseline = $currentTop - $fontSize * 0.72;

            $stream->beginText()
                ->setFont($fontName, $fontSize)
                ->setTextMatrix(Matrix::translate($x, $baseline))
                ->showText($n . '.')
                ->endText();

            $stream->drawRichTextBox($box, $x + $indent, $baseline);

            $currentTop -= $box->getHeight() + $itemSpacing;
        }
    }

    // ── Shared inline-run rendering helpers ─────────────────────────────────

    /** @param list<InlineRun> $runs */
    private function richTextBox(
        array $runs,
        float $maxWidth,
        float $fontSize,
        float $lhMultiplier,
        bool $forceBold,
        bool $forceItalic,
    ): RichTextBox {
        $spans = [];

        foreach (self::mergeTouchingRuns($runs) as $run) {
            [$fontName, $metrics] = $this->fontFor($run, $forceBold, $forceItalic);
            $spans[] = TextSpan::create($run->text, $fontName, $fontSize, $metrics);
        }

        return RichTextBox::create($spans, max(1.0, $maxWidth), $fontSize * $lhMultiplier);
    }

    /**
     * Registers a clickable PdfPageBuilder::addUriLink() rectangle over the
     * rendered glyphs of every run in $runs that carries a linkUrl.
     *
     * Mirrors RichTextBox's own greedy word-wrap (see wrapForLinks()) so the
     * computed rectangles land on the same line breaks actually drawn by
     * richTextBox() + PdfContentStreamBuilder::drawRichTextBox() for the
     * same ($runs, $maxWidth, $fontSize, $lhMultiplier) — both rely on the
     * exact same per-word width math, so they agree without RichTextBox
     * needing to expose per-span source-run metadata it doesn't track.
     *
     * Assumes left-aligned text, which is the only alignment this converter
     * ever uses. $maxLines, when given, caps registration to that many
     * leading lines — used when only that many lines were actually drawn on
     * this page (see splitParagraph()/splitHeading()).
     *
     * @param list<InlineRun> $runs
     */
    private function registerLinks(
        PdfPageBuilder $page,
        array $runs,
        float $x,
        float $topY,
        float $maxWidth,
        float $fontSize,
        float $lhMultiplier,
        bool $forceBold,
        bool $forceItalic,
        ?int $maxLines = null,
    ): void {
        if (!self::containsLink($runs)) {
            return;
        }

        $lineHeight = $fontSize * $lhMultiplier;
        $tokens = $this->tokenizeRuns($runs, $forceBold, $forceItalic);
        $lines = $this->wrapTokens($tokens, max(1.0, $maxWidth), $fontSize);

        if ($maxLines !== null) {
            $lines = array_slice($lines, 0, $maxLines);
        }

        $currentTop = $topY;

        foreach ($lines as $line) {
            foreach ($this->linkSegmentsForLine($line, $fontSize) as $segment) {
                $page->addUriLink(
                    $x + $segment['x'],
                    $currentTop - $lineHeight,
                    $segment['width'],
                    $lineHeight,
                    $segment['url'],
                );
            }

            $currentTop -= $lineHeight;
        }
    }

    /**
     * Word-tokenises $runs, recording each word's full styling (not just
     * linkUrl) — shared by registerLinks() (which only reads linkUrl) and
     * the mid-block pagination splitters (which need bold/italic/code too,
     * to rebuild a faithful InlineRun list for whatever didn't fit).
     *
     * @param list<InlineRun> $runs
     * @return list<array{
     *     word: string,
     *     metrics: \PhpPdf\Font\FontMetrics,
     *     bold: bool,
     *     italic: bool,
     *     code: bool,
     *     linkUrl: ?string,
     * }>
     */
    private function tokenizeRuns(array $runs, bool $forceBold, bool $forceItalic): array
    {
        $tokens = [];

        foreach (self::mergeTouchingRuns($runs) as $run) {
            [, $metrics] = $this->fontFor($run, $forceBold, $forceItalic);

            foreach (preg_split('/\s+/', $run->text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $tokens[] = [
                    'word' => $word,
                    'metrics' => $metrics,
                    'bold' => $run->bold,
                    'italic' => $run->italic,
                    'code' => $run->code,
                    'linkUrl' => $run->linkUrl,
                ];
            }
        }

        return $tokens;
    }

    /**
     * Re-derives RichTextBox::wrap()'s line breaks directly from word
     * tokens (same greedy packing, same width math), so the result lands on
     * the same line breaks actually drawn by richTextBox() +
     * PdfContentStreamBuilder::drawRichTextBox() for the same
     * ($runs, $maxWidth, $fontSize) — both rely on the exact same per-word
     * width math, so they agree without RichTextBox needing to expose
     * per-span source-run metadata it doesn't track.
     *
     * @param list<array{
     *     word: string,
     *     metrics: \PhpPdf\Font\FontMetrics,
     *     bold: bool,
     *     italic: bool,
     *     code: bool,
     *     linkUrl: ?string,
     * }> $tokens
     * @return list<list<array{
     *     word: string,
     *     metrics: \PhpPdf\Font\FontMetrics,
     *     bold: bool,
     *     italic: bool,
     *     code: bool,
     *     linkUrl: ?string,
     * }>>
     */
    private function wrapTokens(array $tokens, float $maxWidth, float $fontSize): array
    {
        if ($tokens === []) {
            return [];
        }

        $lines = [];
        $lineTokens = [];
        $lineWidthPt = 0.0;

        foreach ($tokens as $token) {
            $wordPt = $token['metrics']->stringWidth($token['word']) * $fontSize / 1000;
            $spacePt = $token['metrics']->charWidth(32) * $fontSize / 1000;

            if ($lineTokens === []) {
                $lineTokens[] = $token;
                $lineWidthPt = $wordPt;
            } elseif ($lineWidthPt + $spacePt + $wordPt <= $maxWidth) {
                $lineTokens[] = $token;
                $lineWidthPt += $spacePt + $wordPt;
            } else {
                $lines[] = $lineTokens;
                $lineTokens = [$token];
                $lineWidthPt = $wordPt;
            }
        }

        $lines[] = $lineTokens;

        return $lines;
    }

    /**
     * Rebuilds an InlineRun list from word tokens (see tokenizeRuns()),
     * merging consecutive tokens that share the same styling back into one
     * run. Used to reconstruct the runs that didn't fit on the current page
     * into a fresh block for the next one — see splitParagraph() / splitHeading().
     *
     * @param list<list<array{
     *     word: string,
     *     metrics: \PhpPdf\Font\FontMetrics,
     *     bold: bool,
     *     italic: bool,
     *     code: bool,
     *     linkUrl: ?string,
     * }>> $lines
     * @return list<InlineRun>
     */
    private function reconstructRuns(array $lines): array
    {
        $runs = [];
        $words = [];
        $bold = false;
        $italic = false;
        $code = false;
        $linkUrl = null;
        $hasCurrent = false;

        foreach (array_merge([], ...$lines) as $token) {
            $sameStyle = $hasCurrent
                && $token['bold'] === $bold
                && $token['italic'] === $italic
                && $token['code'] === $code
                && $token['linkUrl'] === $linkUrl;

            if (!$sameStyle) {
                if ($hasCurrent) {
                    $runs[] = new InlineRun(implode(' ', $words), $bold, $italic, $code, $linkUrl);
                }

                $words = [];
                $bold = $token['bold'];
                $italic = $token['italic'];
                $code = $token['code'];
                $linkUrl = $token['linkUrl'];
                $hasCurrent = true;
            }

            $words[] = $token['word'];
        }

        if ($hasCurrent) {
            $runs[] = new InlineRun(implode(' ', $words), $bold, $italic, $code, $linkUrl);
        }

        return $runs;
    }

    /**
     * @param list<array{
     *     word: string,
     *     metrics: \PhpPdf\Font\FontMetrics,
     *     bold: bool,
     *     italic: bool,
     *     code: bool,
     *     linkUrl: ?string,
     * }> $line
     * @return list<array{x: float, width: float, url: string}>
     */
    private function linkSegmentsForLine(array $line, float $fontSize): array
    {
        $segments = [];
        $cursorX = 0.0;
        $runStart = 0.0;
        $runUrl = null;

        foreach ($line as $i => $token) {
            $wordPt = $token['metrics']->stringWidth($token['word']) * $fontSize / 1000;
            $spacePt = $token['metrics']->charWidth(32) * $fontSize / 1000;

            if ($i > 0) {
                $cursorX += $spacePt;
            }

            if ($token['linkUrl'] !== $runUrl) {
                if ($runUrl !== null) {
                    $segments[] = ['x' => $runStart, 'width' => $cursorX - $runStart, 'url' => $runUrl];
                }

                $runUrl = $token['linkUrl'];
                $runStart = $cursorX;
            }

            $cursorX += $wordPt;
        }

        if ($runUrl !== null) {
            $segments[] = ['x' => $runStart, 'width' => $cursorX - $runStart, 'url' => $runUrl];
        }

        return $segments;
    }

    /** @return array{0: string, 1: \PhpPdf\Font\FontMetrics} */
    private function fontFor(InlineRun $run, bool $forceBold, bool $forceItalic): array
    {
        if ($run->code) {
            return [$this->config->getCodeFontName(), $this->config->getCodeFontMetrics()];
        }

        $bold = $run->bold || $forceBold;
        $italic = $run->italic || $forceItalic;

        return match (true) {
            $bold && $italic => [$this->config->getBoldItalicFontName(), $this->config->getBoldItalicFontMetrics()],
            $bold => [$this->config->getBoldFontName(), $this->config->getBoldFontMetrics()],
            $italic => [$this->config->getItalicFontName(), $this->config->getItalicFontMetrics()],
            default => [$this->config->getRegularFontName(), $this->config->getRegularFontMetrics()],
        };
    }

    /**
     * @param list<InlineRun> $runs
     * @return list<int>
     */
    private static function footnoteNumbersInRuns(array $runs): array
    {
        $numbers = [];

        foreach ($runs as $run) {
            if ($run->footnoteNumber !== null) {
                $numbers[] = $run->footnoteNumber;
            }
        }

        return $numbers;
    }

    private static function hasTaskItem(ListBlock $block): bool
    {
        foreach ($block->items as $item) {
            if ($item->checked !== null) {
                return true;
            }
        }

        return false;
    }

    /** @param list<InlineRun> $runs */
    private static function containsLink(array $runs): bool
    {
        foreach ($runs as $run) {
            if ($run->linkUrl !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * RichTextBox treats every span boundary as a word boundary and always
     * inserts a space there, with no way to say two spans touch with no
     * space between them. Markdown routinely produces exactly that: a bold
     * run closing right up against trailing punctuation (e.g. bold text
     * followed immediately by a comma, no space) parses into two adjacent
     * spans that would otherwise render with a spurious space wedged
     * between them. This merges the touching boundary characters into
     * whichever side's styling should win, so RichTextBox never sees an
     * artificial word break where the source had none.
     *
     * @param list<InlineRun> $runs
     * @return list<InlineRun>
     */
    private static function mergeTouchingRuns(array $runs): array
    {
        $merged = [];

        foreach ($runs as $run) {
            if ($merged === []) {
                $merged[] = $run;

                continue;
            }

            $prev = $merged[array_key_last($merged)];

            if (!self::runsTouch($prev, $run)) {
                $merged[] = $run;

                continue;
            }

            if (self::stylePriority($run) > self::stylePriority($prev)) {
                array_splice($merged, -1, 1, self::glueOnto($run, $prev, fromEnd: true));
            } else {
                array_splice($merged, -1, 1, self::glueOnto($prev, $run, fromEnd: false));
            }
        }

        return $merged;
    }

    private static function runsTouch(InlineRun $prev, InlineRun $run): bool
    {
        return $prev->text !== ''
            && $run->text !== ''
            && !self::isWhitespace($prev->text[-1])
            && !self::isWhitespace($run->text[0]);
    }

    /**
     * Glues the touching boundary characters of $donor onto $winner's text.
     *
     * When $fromEnd is true, $donor is the earlier run: its trailing
     * non-whitespace chunk is moved onto the front of $winner. Otherwise
     * $donor is the later run: its leading non-whitespace chunk is moved onto
     * the end of $winner.
     *
     * @return list<InlineRun> Either [$winner] or [$donorRemainder, $winner] /
     *                          [$winner, $donorRemainder], in source order.
     */
    private static function glueOnto(InlineRun $winner, InlineRun $donor, bool $fromEnd): array
    {
        if ($fromEnd) {
            preg_match('/^(.*?)(\S*)$/su', $donor->text, $matches);
            assert(isset($matches[1], $matches[2]));
            $remainder = $matches[1];
            $glued = $matches[2];
            $combined = new InlineRun(
                $glued . $winner->text,
                $winner->bold,
                $winner->italic,
                $winner->code,
                $winner->linkUrl,
            );

            return $remainder === ''
                ? [$combined]
                : [new InlineRun($remainder, $donor->bold, $donor->italic, $donor->code, $donor->linkUrl), $combined];
        }

        preg_match('/^(\S*)(.*)$/su', $donor->text, $matches);
        assert(isset($matches[1], $matches[2]));
        $glued = $matches[1];
        $remainder = $matches[2];
        $combined = new InlineRun(
            $winner->text . $glued,
            $winner->bold,
            $winner->italic,
            $winner->code,
            $winner->linkUrl,
        );

        return $remainder === ''
            ? [$combined]
            : [$combined, new InlineRun($remainder, $donor->bold, $donor->italic, $donor->code, $donor->linkUrl)];
    }

    private static function isWhitespace(string $char): bool
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }

    private static function stylePriority(InlineRun $run): int
    {
        return match (true) {
            $run->code => 4,
            $run->bold && $run->italic => 3,
            $run->bold => 2,
            $run->italic => 1,
            default => 0,
        };
    }
}
