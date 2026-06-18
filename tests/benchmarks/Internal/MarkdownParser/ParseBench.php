<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\MarkdownParser;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use PhpPdf\Markdown\Internal\MarkdownParser;

#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(50)]
final class ParseBench
{
    private string $fullFeaturedMarkdown;
    private string $longMarkdown;

    public function setUp(): void
    {
        $this->fullFeaturedMarkdown = <<<MD
        # Heading One

        ## Heading Two

        A paragraph with **bold**, *italic*, ***both***, and `code`.

        - First item
        - Second item
          - Nested item

        1. Step one
        2. Step two

        > A quoted remark.
        > > A nested remark.

        ```
        echo "hello";
        ```

        ---

        | A | B |
        |---|---|
        | 1 | 2 |

        A claim[^1].

        [^1]: The source.
        MD;

        $paragraph = str_repeat('word ', 80);
        $this->longMarkdown = implode("\n\n", array_fill(0, 40, $paragraph));
    }

    public function benchFullFeaturedMarkdown(): void
    {
        MarkdownParser::parse($this->fullFeaturedMarkdown);
    }

    public function benchLongMarkdown(): void
    {
        MarkdownParser::parse($this->longMarkdown);
    }
}
