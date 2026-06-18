<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/** @internal */
final class ListBlock implements MarkdownBlock
{
    /** @param list<ListItem> $items */
    public function __construct(
        public readonly bool $ordered,
        public readonly array $items,
    ) {
    }
}
