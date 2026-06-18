<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/**
 * A fenced code block. The raw $code is rendered verbatim in a monospace
 * font; no syntax highlighting is applied. $language (the text following
 * the opening fence, e.g. "php") is captured but not currently used.
 *
 * @internal
 */
final class CodeBlock implements MarkdownBlock
{
    public function __construct(
        public readonly string $code,
        public readonly string $language = '',
    ) {
    }
}
