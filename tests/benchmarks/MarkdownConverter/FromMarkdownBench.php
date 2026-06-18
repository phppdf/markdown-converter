<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverter;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use PhpPdf\Builder\PdfDocumentBuilder;
use PhpPdf\Markdown\MarkdownConverter;
use PhpPdf\Markdown\MarkdownConverterConfig;

#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(20)]
final class FromMarkdownBench
{
    private string $shortMarkdown;
    private string $fullFeaturedMarkdown;
    private string $longMarkdown;
    private MarkdownConverterConfig $config;

    public function setUp(): void
    {
        $this->shortMarkdown = '# Title' . "\n\n" . 'Hello world.';

        $this->fullFeaturedMarkdown = <<<MD
        # Heading One

        ## Heading Two

        A paragraph with **bold**, *italic*, ***both***, and `code`.

        - First item
        - Second item

        1. Step one
        2. Step two

        > A quoted remark.

        ```
        echo "hello";
        ```

        ---

        | A | B |
        |---|---|
        | 1 | 2 |
        MD;

        $paragraph = str_repeat('word ', 80);
        $this->longMarkdown = implode("\n\n", array_fill(0, 40, $paragraph));

        $this->config = new MarkdownConverterConfig();
    }

    public function benchShortMarkdown(): void
    {
        MarkdownConverter::fromMarkdown($this->shortMarkdown, $this->config, new PdfDocumentBuilder());
    }

    public function benchFullFeaturedMarkdown(): void
    {
        MarkdownConverter::fromMarkdown($this->fullFeaturedMarkdown, $this->config, new PdfDocumentBuilder());
    }

    public function benchLongMultiPageMarkdown(): void
    {
        MarkdownConverter::fromMarkdown($this->longMarkdown, $this->config, new PdfDocumentBuilder());
    }
}
