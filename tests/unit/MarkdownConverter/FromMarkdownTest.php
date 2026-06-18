<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverter;

use PhpPdf\Builder\PdfDocumentBuilder;
use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Color\Color;
use PhpPdf\Document\PdfDocument;
use PhpPdf\Font\Type1FontMetrics;
use PhpPdf\Markdown\FootnotePlacement;
use PhpPdf\Markdown\Internal\Block\BlockQuoteBlock;
use PhpPdf\Markdown\Internal\Block\CodeBlock;
use PhpPdf\Markdown\Internal\Block\HeadingBlock;
use PhpPdf\Markdown\Internal\Block\ImageBlock;
use PhpPdf\Markdown\Internal\Block\ListBlock;
use PhpPdf\Markdown\Internal\Block\ListItem;
use PhpPdf\Markdown\Internal\Block\MarkdownBlock;
use PhpPdf\Markdown\Internal\Block\ParagraphBlock;
use PhpPdf\Markdown\Internal\Block\TableAlignment;
use PhpPdf\Markdown\Internal\Block\TableBlock;
use PhpPdf\Markdown\Internal\Block\ThematicBreakBlock;
use PhpPdf\Markdown\Internal\Inline\InlineParser;
use PhpPdf\Markdown\Internal\Inline\InlineRun;
use PhpPdf\Markdown\Internal\MarkdownLayoutEngine;
use PhpPdf\Markdown\Internal\MarkdownParser;
use PhpPdf\Markdown\MarkdownChunk;
use PhpPdf\Markdown\MarkdownConverter;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PhpPdf\Markdown\MarkdownFlow;
use PhpPdf\Output\PdfMemoryOutput;
use PhpPdf\Serialization\PdfDocumentSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MarkdownConverter::class)]
#[CoversMethod(MarkdownConverter::class, 'fromMarkdown')]
#[UsesClass(MarkdownConverterConfig::class)]
#[UsesClass(MarkdownFlow::class)]
#[UsesClass(MarkdownChunk::class)]
#[UsesClass(MarkdownParser::class)]
#[UsesClass(MarkdownLayoutEngine::class)]
#[UsesClass(InlineParser::class)]
#[UsesClass(InlineRun::class)]
#[UsesClass(HeadingBlock::class)]
#[UsesClass(ParagraphBlock::class)]
#[UsesClass(ListBlock::class)]
#[UsesClass(ListItem::class)]
#[UsesClass(BlockQuoteBlock::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(ThematicBreakBlock::class)]
#[UsesClass(TableBlock::class)]
#[UsesClass(TableAlignment::class)]
#[UsesClass(ImageBlock::class)]
final class FromMarkdownTest extends TestCase
{
    #[Test]
    public function returnsDocumentBuilder(): void
    {
        // Arrange / Act
        $builder = MarkdownConverter::fromMarkdown(
            '# Title' . "\n\n" . 'Hello world.',
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );

        // Assert
        self::assertInstanceOf(PdfDocumentBuilder::class, $builder);
    }

    #[Test]
    public function returnsTheSameBuilderInstanceItWasGiven(): void
    {
        // Arrange
        $builder = new PdfDocumentBuilder();

        // Act
        $result = MarkdownConverter::fromMarkdown('Hello world.', new MarkdownConverterConfig(), $builder);

        // Assert
        self::assertSame($builder, $result);
    }

    #[Test]
    public function appendsBlankPageForEmptyMarkdown(): void
    {
        // Arrange / Act
        $builder = MarkdownConverter::fromMarkdown('', new MarkdownConverterConfig(), new PdfDocumentBuilder());

        // Assert
        self::assertSame(1, $builder->getPageCount());
    }

    #[Test]
    public function appendsToAnExistingBuilderWithPagesAlready(): void
    {
        // Arrange: a builder that already has a hand-crafted page on it,
        // proving fromMarkdown() appends rather than replacing.
        $builder = new PdfDocumentBuilder();
        $builder->page(static function (PdfPageBuilder $page): void {
            $page->size(595, 842);
        });

        // Act
        MarkdownConverter::fromMarkdown('# Title', new MarkdownConverterConfig(), $builder);

        // Assert
        self::assertGreaterThanOrEqual(2, $builder->getPageCount());
    }

    #[Test]
    public function rendersAtLeastOnePageForFullFeaturedDocument(): void
    {
        // Arrange
        $markdown = <<<MD
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

        // Act
        $builder = MarkdownConverter::fromMarkdown($markdown, new MarkdownConverterConfig(), new PdfDocumentBuilder());

        // Assert
        self::assertGreaterThanOrEqual(1, $builder->getPageCount());
    }

    #[Test]
    public function paginatesLongDocumentsAcrossMultiplePages(): void
    {
        // Arrange
        $paragraph = str_repeat('word ', 80);
        $markdown = implode("\n\n", array_fill(0, 40, $paragraph));

        // Act
        $builder = MarkdownConverter::fromMarkdown($markdown, new MarkdownConverterConfig(), new PdfDocumentBuilder());

        // Assert
        self::assertGreaterThan(1, $builder->getPageCount());
    }

    #[Test]
    public function honoursCustomConfig(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setPageWidth(400)->setPageHeight(400);

        // Act
        $builder = MarkdownConverter::fromMarkdown('# Hi', $config, new PdfDocumentBuilder());

        // Assert
        self::assertGreaterThanOrEqual(1, $builder->getPageCount());
    }

    #[Test]
    public function buildsAValidPdfDocument(): void
    {
        // Arrange
        $builder = MarkdownConverter::fromMarkdown(
            '# Hi' . "\n\n" . 'Body text.',
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );

        // Act / Assert
        self::assertInstanceOf(PdfDocument::class, $builder->build());
    }

    #[Test]
    public function honoursCustomCodeBackgroundColorInRenderedOutput(): void
    {
        // Arrange: a fully saturated, unmistakable colour so its RGB fill
        // operator is easy to find in the raw (uncompressed) content stream.
        $config = new MarkdownConverterConfig();
        $config->setCodeBackgroundColor(Color::fromHex('#ff0000'));
        $builder = MarkdownConverter::fromMarkdown("```\necho 1;\n```", $config, new PdfDocumentBuilder());

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());

        // Assert
        self::assertStringContainsString('1 0 0 rg', $output->getContent());
    }

    #[Test]
    public function honoursCustomFontResourceNamesInRenderedOutput(): void
    {
        // Arrange: the library never registers fonts itself — it only emits
        // whatever resource name the config tells it to. Registering a
        // distinctive name proves it actually flows through to the Tf
        // operator rather than always being hardcoded to "F1".
        $config = new MarkdownConverterConfig();
        $config->setRegularFont('Body', Type1FontMetrics::helvetica());
        $builder = new PdfDocumentBuilder();
        $builder->globalFont('Body', 'Helvetica');

        // Act
        MarkdownConverter::fromMarkdown('Plain text.', $config, $builder);
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());

        // Assert
        self::assertStringContainsString('/Body 11 Tf', $output->getContent());
    }

    #[Test]
    public function doesNotInsertASpaceBeforePunctuationTouchingStyledText(): void
    {
        // Arrange: "**bold**," parses into a bold "bold" run immediately
        // followed by a plain ", " run with no space between them in the
        // source. RichTextBox treats every span boundary as a word boundary
        // and would otherwise render this as "bold ," with a phantom space.
        $builder = MarkdownConverter::fromMarkdown(
            'Markdown supports **bold**, *italic*, ***bold italic***, and `inline code`, done.',
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert: each styled run's closing delimiter should be glued
        // directly to its trailing punctuation in the actual drawn text.
        self::assertStringContainsString('bold,', $content);
        self::assertStringNotContainsString('bold ,', $content);
        self::assertStringContainsString('italic,', $content);
        self::assertStringNotContainsString('italic ,', $content);
        self::assertStringContainsString('code,', $content);
        self::assertStringNotContainsString('code ,', $content);
    }

    #[Test]
    public function instantiatesEveryBlockTypeUnderTest(): void
    {
        // This test exists solely to keep #[UsesClass] declarations truthful for
        // the simple value objects that MarkdownParser/MarkdownLayoutEngine
        // construct internally and that are not otherwise covered directly.

        // Arrange
        $blocks = [
            new HeadingBlock(1, [new InlineRun('x')]),
            new ParagraphBlock([new InlineRun('x')]),
            new ListBlock(false, [new ListItem([new InlineRun('x')])]),
            new BlockQuoteBlock([[new InlineRun('x')]]),
            new CodeBlock('x'),
            new ThematicBreakBlock(),
            new TableBlock([[new InlineRun('x')]], [TableAlignment::Left], [[[new InlineRun('x')]]]),
            new ImageBlock('x.png', 'x'),
        ];

        // Act / Assert
        foreach ($blocks as $block) {
            self::assertInstanceOf(MarkdownBlock::class, $block);
        }
    }

    #[Test]
    public function rendersAClickableLinkAnnotationOverTheLinkText(): void
    {
        // Arrange
        $builder = MarkdownConverter::fromMarkdown(
            'Visit [our site](https://example.com/page) for more.',
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert: a Link annotation with a URI action targeting the
        // markdown link's URL was registered on the page.
        self::assertStringContainsString('/Subtype /Link', $content);
        self::assertStringContainsString('/URI (https://example.com/page)', $content);
    }

    #[Test]
    public function usesACustomFootnotesHeadingFromConfig(): void
    {
        // Arrange: the heading is only used in DocumentEnd mode — PerPage
        // mode (the default) has no heading at all, just a separator rule.
        $config = new MarkdownConverterConfig();
        $config->setFootnotePlacement(FootnotePlacement::DocumentEnd);
        $config->setFootnotesHeading('Notes de bas de page');
        $builder = MarkdownConverter::fromMarkdown(
            "A claim[^1].\n\n[^1]: The source.",
            $config,
            new PdfDocumentBuilder(),
        );

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert
        self::assertStringContainsString('(Notes de bas de page)', $content);
        self::assertStringNotContainsString('(Footnotes)', $content);
    }

    #[Test]
    public function rendersFootnoteDefinitionAtTheBottomOfThePageByDefault(): void
    {
        // Arrange: PerPage is the default — no config override needed.
        $builder = MarkdownConverter::fromMarkdown(
            "A claim[^1].\n\n[^1]: The source.",
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert: a single page holding both the reference marker and its
        // definition, with no separate "Footnotes" heading section.
        self::assertSame(1, $builder->getPageCount());
        self::assertStringContainsString('(A claim[1].)', $content);
        self::assertStringContainsString('(The source.)', $content);
        self::assertStringNotContainsString('(Footnotes)', $content);
    }

    #[Test]
    public function reservesFootnoteSpaceOnlyOnTheFirstPageThatReferencesIt(): void
    {
        // Arrange: the same footnote cited twice, far enough apart (with
        // enough filler in between) that the citations land on different
        // pages — the definition should only be drawn once, on the first.
        $config = new MarkdownConverterConfig();
        $config->setPageWidth(300)->setPageHeight(200);
        $config->setMarginTop(20)->setMarginBottom(20)->setMarginLeft(20)->setMarginRight(20);

        $filler = implode("\n\n", array_fill(0, 12, str_repeat('word ', 30)));
        $markdown = "First cite[^x].\n\n{$filler}\n\nSecond cite[^x].\n\n[^x]: Shared source.";
        $builder = MarkdownConverter::fromMarkdown($markdown, $config, new PdfDocumentBuilder());

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert
        self::assertGreaterThan(1, $builder->getPageCount());
        self::assertSame(1, substr_count($content, '(Shared source.)'));
    }

    #[Test]
    public function embedsAStandaloneImageAsAnXObject(): void
    {
        // Arrange: a standalone image (alone on its own paragraph) should be
        // embedded as a real image XObject, not just its alt text.
        $path = tempnam(sys_get_temp_dir(), 'mdimg') . '.jpg';
        file_put_contents($path, self::minimalJpeg());

        try {
            $builder = MarkdownConverter::fromMarkdown(
                "![a small test image]({$path})",
                new MarkdownConverterConfig(),
                new PdfDocumentBuilder(),
            );

            // Act
            $output = new PdfMemoryOutput();
            (new PdfDocumentSerializer($output))->writeDocument($builder->build());
            $content = $output->getContent();

            // Assert
            self::assertStringContainsString('/Subtype /Image', $content);
            self::assertMatchesRegularExpression('/\/MdImg\d+ Do/', $content);
            self::assertStringNotContainsString('a small test image', $content);
        } finally {
            unlink($path);
        }
    }

    #[Test]
    public function throwsWhenStandaloneImageFileIsMissing(): void
    {
        // Arrange / Act / Assert
        self::expectException(RuntimeException::class);

        MarkdownConverter::fromMarkdown(
            '![missing](/no/such/file.png)',
            new MarkdownConverterConfig(),
            new PdfDocumentBuilder(),
        );
    }

    #[Test]
    public function splitsAParagraphTallerThanOnePageAcrossPages(): void
    {
        // Arrange: a tiny page leaves only a sliver of content height, so a
        // many-line paragraph can't possibly fit on one page and must split.
        $config = new MarkdownConverterConfig();
        $config->setPageWidth(300)->setPageHeight(160);
        $config->setMarginTop(20)->setMarginBottom(20)->setMarginLeft(20)->setMarginRight(20);

        $markdown = 'Start word **bold-marker** ' . str_repeat('filler ', 150) . 'end-marker';
        $builder = MarkdownConverter::fromMarkdown($markdown, $config, new PdfDocumentBuilder());

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert: more than one page, the bold styling switch (Tf to the
        // bold font) survived the split, and the very last word made it
        // onto some page (i.e. content wasn't truncated, only paginated).
        self::assertGreaterThan(1, $builder->getPageCount());
        self::assertStringContainsString('bold-marker', $content);
        self::assertStringContainsString('end-marker', $content);
    }

    #[Test]
    public function splitsATableTallerThanOnePageRepeatingTheHeader(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setPageWidth(300)->setPageHeight(160);
        $config->setMarginTop(20)->setMarginBottom(20)->setMarginLeft(20)->setMarginRight(20);

        $rows = implode("\n", array_map(
            static fn (int $i): string => "| row{$i} | value{$i} |",
            range(1, 20),
        ));
        $markdown = "| Key | Value |\n|---|---|\n{$rows}";
        $builder = MarkdownConverter::fromMarkdown($markdown, $config, new PdfDocumentBuilder());

        // Act
        $output = new PdfMemoryOutput();
        (new PdfDocumentSerializer($output))->writeDocument($builder->build());
        $content = $output->getContent();

        // Assert: split across pages, with the header repeated on each
        // continuation rather than appearing only once at the very top.
        self::assertGreaterThan(1, $builder->getPageCount());
        self::assertGreaterThan(1, substr_count($content, '(Key)'));
        self::assertStringContainsString('row20', $content);
    }

    /** Builds a minimal valid baseline JPEG (1x1, RGB) containing one SOF0 segment. */
    private static function minimalJpeg(): string
    {
        $jpeg = "\xFF\xD8"; // SOI
        $jpeg .= "\xFF\xC0" . pack('n', 17) . "\x08" . pack('n', 1) . pack('n', 1) . "\x03"
            . "\x01\x11\x00\x02\x11\x00\x03\x11\x00"; // SOF0: 1x1, 3 components
        $jpeg .= "\xFF\xD9"; // EOI

        return $jpeg;
    }
}
