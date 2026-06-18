<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Inline\InlineParser;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use PhpPdf\Markdown\Internal\Inline\InlineParser;

#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(200)]
final class ParseBench
{
    private string $plainText;
    private string $heavilyStyledText;

    public function setUp(): void
    {
        $this->plainText = str_repeat('word ', 80);

        $this->heavilyStyledText = str_repeat(
            'Some **bold**, *italic*, ***bold italic***, `code`, '
                . 'and a [link](https://example.com/page) with a footnote[^1]. ',
            10,
        );
    }

    public function benchPlainText(): void
    {
        InlineParser::parse($this->plainText);
    }

    public function benchHeavilyStyledText(): void
    {
        $footnoteOrder = [];

        InlineParser::parse($this->heavilyStyledText, $footnoteOrder);
    }
}
