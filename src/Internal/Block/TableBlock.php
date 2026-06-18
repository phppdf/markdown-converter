<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/**
 * A GFM pipe table. No row- or column-spans are supported (the Markdown
 * table syntax has no notation for them); column widths are distributed
 * equally across the available content width.
 *
 * @internal
 */
final class TableBlock implements MarkdownBlock
{
    /**
     * @param list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>> $header
     * @param list<TableAlignment> $alignments
     * @param list<list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>>> $rows
     */
    public function __construct(
        public readonly array $header,
        public readonly array $alignments,
        public readonly array $rows,
    ) {
    }
}
