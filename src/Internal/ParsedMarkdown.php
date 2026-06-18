<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal;

/**
 * The result of MarkdownParser::parse(): the document's content blocks, plus
 * every referenced footnote's definition text — parsed but not yet placed
 * anywhere. Deciding where footnotes go (an end-of-document section, or
 * reserved space at the bottom of whichever page first references each
 * one) is MarkdownFlow's job, driven by MarkdownConverterConfig's
 * FootnotePlacement.
 *
 * @internal
 */
final class ParsedMarkdown
{
    /**
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     * @param list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>> $footnotes Definition runs, in order of
     *     first reference — index 0 is footnote number 1, index 1 is
     *     footnote number 2, and so on.
     */
    public function __construct(
        public readonly array $blocks,
        public readonly array $footnotes,
    ) {
    }
}
