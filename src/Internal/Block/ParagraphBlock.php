<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/** @internal */
final class ParagraphBlock implements MarkdownBlock
{
    /** @param list<\PhpPdf\Markdown\Internal\Inline\InlineRun> $runs */
    public function __construct(
        public readonly array $runs,
    ) {
    }
}
