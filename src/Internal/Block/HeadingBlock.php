<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/** @internal */
final class HeadingBlock implements MarkdownBlock
{
    /** @param list<\PhpPdf\Markdown\Internal\Inline\InlineRun> $runs */
    public function __construct(
        public readonly int $level,
        public readonly array $runs,
    ) {
    }
}
