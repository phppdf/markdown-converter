<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Block;

/** Column alignment for a GFM table, derived from its delimiter row. @internal */
enum TableAlignment
{
    case Left;
    case Center;
    case Right;
}
