<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/** @internal */
final class ListItem
{
    /**
     * @param list<\PhpPdf\Markdown\Internal\Inline\InlineRun> $runs
     * @param list<ListBlock> $children Nested sub-lists indented under this item.
     * @param bool|null $checked Task-list checkbox state (`- [ ]`/`- [x]`), or
     *                           null when this item isn't a task-list item.
     */
    public function __construct(
        public readonly array $runs,
        public readonly array $children = [],
        public readonly ?bool $checked = null,
    ) {
    }
}
