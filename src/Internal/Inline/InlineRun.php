<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Inline;

/**
 * A run of inline text sharing the same emphasis/code styling.
 *
 * Produced by InlineParser; consumed by the layout engine to build
 * RichTextBox spans (each run maps to one TextSpan with a font chosen from
 * its bold/italic/code flags). $linkUrl, when set, marks this run as
 * (part of) a Markdown link's visible text — the layout engine registers a
 * clickable PdfPageBuilder::addUriLink() rectangle over its rendered glyphs.
 * $footnoteNumber, when set, marks this run as a `[^label]` reference's
 * "[N]" marker — the layout engine scans for it to decide which footnote
 * definitions need to be reserved space for on a given page (see
 * MarkdownLayoutEngine::footnoteNumbersIn()).
 *
 * @internal
 */
final class InlineRun
{
    public function __construct(
        public readonly string $text,
        public readonly bool $bold = false,
        public readonly bool $italic = false,
        public readonly bool $code = false,
        public readonly ?string $linkUrl = null,
        public readonly ?int $footnoteNumber = null,
    ) {
    }
}
