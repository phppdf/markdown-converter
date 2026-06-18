<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/**
 * A standalone image (`![alt](src)` alone on its own paragraph). $src is a
 * local filesystem path passed to PdfImage::fromFile() — remote URLs are
 * not fetched. An image written inline mixed with other text does not
 * become an ImageBlock; it stays a plain InlineRun carrying only the alt
 * text (see InlineParser).
 *
 * @internal
 */
final class ImageBlock implements MarkdownBlock
{
    public function __construct(
        public readonly string $src,
        public readonly string $alt,
    ) {
    }
}
