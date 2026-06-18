<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Inline\InlineParser;

use PhpPdf\Markdown\Internal\Inline\InlineParser;
use PhpPdf\Markdown\Internal\Inline\InlineRun;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InlineParser::class)]
#[CoversMethod(InlineParser::class, 'parse')]
#[UsesClass(InlineRun::class)]
final class ParseTest extends TestCase
{
    #[Test]
    public function returnsSinglePlainRunForUnstyledText(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('Hello world');

        // Assert
        self::assertEquals([new InlineRun('Hello world')], $runs);
    }

    #[Test]
    public function returnsEmptyListForEmptyText(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('');

        // Assert
        self::assertSame([], $runs);
    }

    #[Test]
    public function parsesBoldWithAsterisks(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('a **bold** b');

        // Assert
        self::assertEquals(
            [new InlineRun('a '), new InlineRun('bold', bold: true), new InlineRun(' b')],
            $runs,
        );
    }

    #[Test]
    public function parsesBoldWithUnderscores(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('__bold__');

        // Assert
        self::assertEquals([new InlineRun('bold', bold: true)], $runs);
    }

    #[Test]
    public function parsesItalicWithAsterisks(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('*italic*');

        // Assert
        self::assertEquals([new InlineRun('italic', italic: true)], $runs);
    }

    #[Test]
    public function parsesItalicWithUnderscores(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('_italic_');

        // Assert
        self::assertEquals([new InlineRun('italic', italic: true)], $runs);
    }

    #[Test]
    public function parsesBoldItalic(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('***both***');

        // Assert
        self::assertEquals([new InlineRun('both', bold: true, italic: true)], $runs);
    }

    #[Test]
    public function parsesBoldItalicWithUnderscores(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('___both___');

        // Assert
        self::assertEquals([new InlineRun('both', bold: true, italic: true)], $runs);
    }

    #[Test]
    public function parsesItalicNestedInsideBold(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('**bold *and* more**');

        // Assert
        self::assertEquals(
            [
                new InlineRun('bold ', bold: true),
                new InlineRun('and', bold: true, italic: true),
                new InlineRun(' more', bold: true),
            ],
            $runs,
        );
    }

    #[Test]
    public function parsesBoldNestedInsideItalic(): void
    {
        // Arrange / Act: mixed delimiter families (underscore italic, asterisk
        // bold) avoid the lazy-match ambiguity that nesting the *same*
        // delimiter character inside itself runs into (see class docblock).
        $runs = InlineParser::parse('_italic **and** more_');

        // Assert
        self::assertEquals(
            [
                new InlineRun('italic ', italic: true),
                new InlineRun('and', bold: true, italic: true),
                new InlineRun(' more', italic: true),
            ],
            $runs,
        );
    }

    #[Test]
    public function codeSpanNestedInsideBoldIsNotItselfStyledBold(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('**bold `code` text**');

        // Assert
        self::assertEquals(
            [
                new InlineRun('bold ', bold: true),
                new InlineRun('code', code: true),
                new InlineRun(' text', bold: true),
            ],
            $runs,
        );
    }

    #[Test]
    public function parsesInlineCode(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('a `code` b');

        // Assert
        self::assertEquals(
            [new InlineRun('a '), new InlineRun('code', code: true), new InlineRun(' b')],
            $runs,
        );
    }

    #[Test]
    public function codeSpanContentIsNotParsedForEmphasis(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('`**not bold**`');

        // Assert
        self::assertEquals([new InlineRun('**not bold**', code: true)], $runs);
    }

    #[Test]
    public function parsesLinkAsClickableRun(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('[label](https://example.com)');

        // Assert
        self::assertEquals([new InlineRun('label', linkUrl: 'https://example.com')], $runs);
    }

    #[Test]
    public function parsesFootnoteReferenceAsSequentialNumberMarker(): void
    {
        // Arrange / Act
        $footnoteOrder = [];
        $runs = InlineParser::parse('First[^a] and second[^b] and again[^a].', $footnoteOrder);

        // Assert: "a" is assigned 1 on first sight and reused; "b" gets 2.
        self::assertEquals(
            [
                new InlineRun('First'),
                new InlineRun('[1]', footnoteNumber: 1),
                new InlineRun(' and second'),
                new InlineRun('[2]', footnoteNumber: 2),
                new InlineRun(' and again'),
                new InlineRun('[1]', footnoteNumber: 1),
                new InlineRun('.'),
            ],
            $runs,
        );
        self::assertSame(['a' => 1, 'b' => 2], $footnoteOrder);
    }

    #[Test]
    public function parsesBoldLinkPreservingLinkUrlAcrossNestedEmphasis(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('[**bold link**](https://example.com)');

        // Assert
        self::assertEquals(
            [new InlineRun('bold link', bold: true, linkUrl: 'https://example.com')],
            $runs,
        );
    }

    #[Test]
    public function parsesImageAsAltTextOnly(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('![alt text](https://example.com/img.png)');

        // Assert
        self::assertEquals([new InlineRun('alt text')], $runs);
    }

    #[Test]
    public function omitsRunForImageWithEmptyAltText(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('![](https://example.com/img.png)');

        // Assert
        self::assertSame([], $runs);
    }

    #[Test]
    public function unescapesBackslashEscapedPunctuation(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('\\*not italic\\*');

        // Assert
        self::assertEquals([new InlineRun('*not italic*')], $runs);
    }

    #[Test]
    public function handlesMultiByteUtf8Characters(): void
    {
        // Arrange / Act
        $runs = InlineParser::parse('café **résumé**');

        // Assert
        self::assertEquals(
            [new InlineRun('café '), new InlineRun('résumé', bold: true)],
            $runs,
        );
    }
}
