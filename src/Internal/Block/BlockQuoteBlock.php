<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/**
 * A blockquote, possibly containing multiple paragraphs and/or nested
 * blockquotes — source paragraphs separated by a blank quote-continuation
 * line (e.g. `>` on its own) stay separate rather than collapsing into one,
 * and a further `>` nesting level (e.g. `> > quoted`) becomes a nested
 * BlockQuoteBlock rather than being flattened into the parent's text.
 *
 * @internal
 */
final class BlockQuoteBlock implements MarkdownBlock
{
    /** @param list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>|BlockQuoteBlock> $content */
    public function __construct(
        public readonly array $content,
    ) {
    }
}
