<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\MarkdownParser;

use PhpPdf\Markdown\Internal\Block\BlockQuoteBlock;
use PhpPdf\Markdown\Internal\Block\CodeBlock;
use PhpPdf\Markdown\Internal\Block\HeadingBlock;
use PhpPdf\Markdown\Internal\Block\ImageBlock;
use PhpPdf\Markdown\Internal\Block\ListBlock;
use PhpPdf\Markdown\Internal\Block\ParagraphBlock;
use PhpPdf\Markdown\Internal\Block\TableAlignment;
use PhpPdf\Markdown\Internal\Block\TableBlock;
use PhpPdf\Markdown\Internal\Block\ThematicBreakBlock;
use PhpPdf\Markdown\Internal\Inline\InlineParser;
use PhpPdf\Markdown\Internal\Inline\InlineRun;
use PhpPdf\Markdown\Internal\MarkdownParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownParser::class)]
#[CoversMethod(MarkdownParser::class, 'parse')]
#[UsesClass(InlineParser::class)]
#[UsesClass(InlineRun::class)]
#[UsesClass(HeadingBlock::class)]
#[UsesClass(ParagraphBlock::class)]
#[UsesClass(ListBlock::class)]
#[UsesClass(BlockQuoteBlock::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(ThematicBreakBlock::class)]
#[UsesClass(TableBlock::class)]
#[UsesClass(TableAlignment::class)]
#[UsesClass(ImageBlock::class)]
final class ParseTest extends TestCase
{
    #[Test]
    public function parsesAtxHeadingLevels(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse('### Title')->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(HeadingBlock::class, $blocks[0]);
        self::assertSame(3, $blocks[0]->level);
        self::assertEquals([new InlineRun('Title')], $blocks[0]->runs);
    }

    #[Test]
    public function stripsTrailingHashesFromClosedHeading(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse('# Title ##')->blocks;

        // Assert
        self::assertInstanceOf(HeadingBlock::class, $blocks[0]);
        self::assertEquals([new InlineRun('Title')], $blocks[0]->runs);
    }

    #[Test]
    public function parsesSetextHeadingLevelOneUnderlinedWithEquals(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("Title\n=====")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(HeadingBlock::class, $blocks[0]);
        self::assertSame(1, $blocks[0]->level);
        self::assertEquals([new InlineRun('Title')], $blocks[0]->runs);
    }

    #[Test]
    public function parsesSetextHeadingLevelTwoUnderlinedWithDashes(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("Subtitle\n--------")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(HeadingBlock::class, $blocks[0]);
        self::assertSame(2, $blocks[0]->level);
        self::assertEquals([new InlineRun('Subtitle')], $blocks[0]->runs);
    }

    #[Test]
    public function joinsWrappedLinesIntoOneParagraph(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("Line one\nLine two")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
        self::assertEquals([new InlineRun('Line one Line two')], $blocks[0]->runs);
    }

    #[Test]
    public function splitsParagraphsOnBlankLines(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("First\n\nSecond")->blocks;

        // Assert
        self::assertCount(2, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[1]);
    }

    #[Test]
    public function parsesUnorderedListItems(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- one\n- two\n- three")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertFalse($blocks[0]->ordered);
        self::assertCount(3, $blocks[0]->items);
        self::assertEquals([new InlineRun('two')], $blocks[0]->items[1]->runs);
    }

    #[Test]
    public function parsesOrderedListItems(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("1. one\n2. two")->blocks;

        // Assert
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertTrue($blocks[0]->ordered);
        self::assertCount(2, $blocks[0]->items);
    }

    #[Test]
    public function appendsLazyContinuationLineToPreviousListItem(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- one\n  more text")->blocks;

        // Assert
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertCount(1, $blocks[0]->items);
        self::assertEquals([new InlineRun('one more text')], $blocks[0]->items[0]->runs);
    }

    #[Test]
    public function parsesNestedListUnderAnItem(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- parent\n  - child one\n  - child two\n- sibling")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertCount(2, $blocks[0]->items);

        $parent = $blocks[0]->items[0];
        self::assertEquals([new InlineRun('parent')], $parent->runs);
        self::assertCount(1, $parent->children);
        self::assertFalse($parent->children[0]->ordered);
        self::assertCount(2, $parent->children[0]->items);
        self::assertEquals([new InlineRun('child one')], $parent->children[0]->items[0]->runs);
        self::assertEquals([new InlineRun('child two')], $parent->children[0]->items[1]->runs);

        $sibling = $blocks[0]->items[1];
        self::assertEquals([new InlineRun('sibling')], $sibling->runs);
        self::assertSame([], $sibling->children);
    }

    #[Test]
    public function parsesDeeplyNestedLists(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- a\n  - b\n    - c")->blocks;

        // Assert
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        $a = $blocks[0]->items[0];
        self::assertEquals([new InlineRun('a')], $a->runs);

        $b = $a->children[0]->items[0];
        self::assertEquals([new InlineRun('b')], $b->runs);

        $c = $b->children[0]->items[0];
        self::assertEquals([new InlineRun('c')], $c->runs);
    }

    #[Test]
    public function parsesTaskListCheckboxes(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- [ ] Todo\n- [x] Done\n- [X] Also done\n- Not a task")->blocks;

        // Assert
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertCount(4, $blocks[0]->items);
        self::assertFalse($blocks[0]->items[0]->checked);
        self::assertEquals([new InlineRun('Todo')], $blocks[0]->items[0]->runs);
        self::assertTrue($blocks[0]->items[1]->checked);
        self::assertEquals([new InlineRun('Done')], $blocks[0]->items[1]->runs);
        self::assertTrue($blocks[0]->items[2]->checked);
        self::assertEquals([new InlineRun('Also done')], $blocks[0]->items[2]->runs);
        self::assertNull($blocks[0]->items[3]->checked);
        self::assertEquals([new InlineRun('Not a task')], $blocks[0]->items[3]->runs);
    }

    #[Test]
    public function startsNewListWhenMarkerTypeChanges(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("- bullet\n1. number")->blocks;

        // Assert
        self::assertCount(2, $blocks);
        self::assertInstanceOf(ListBlock::class, $blocks[0]);
        self::assertFalse($blocks[0]->ordered);
        self::assertInstanceOf(ListBlock::class, $blocks[1]);
        self::assertTrue($blocks[1]->ordered);
    }

    #[Test]
    public function parsesBlockQuoteAsSingleFlattenedParagraph(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("> line one\n> line two")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(BlockQuoteBlock::class, $blocks[0]);
        self::assertEquals([[new InlineRun('line one line two')]], $blocks[0]->content);
    }

    #[Test]
    public function parsesBlockQuoteWithMultipleParagraphsSeparately(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("> paragraph one\n>\n> paragraph two")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(BlockQuoteBlock::class, $blocks[0]);
        self::assertEquals(
            [[new InlineRun('paragraph one')], [new InlineRun('paragraph two')]],
            $blocks[0]->content,
        );
    }

    #[Test]
    public function parsesNestedBlockQuote(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("> outer\n>\n> > inner\n>\n> after")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(BlockQuoteBlock::class, $blocks[0]);
        self::assertCount(3, $blocks[0]->content);

        self::assertEquals([new InlineRun('outer')], $blocks[0]->content[0]);

        $nested = $blocks[0]->content[1];
        self::assertInstanceOf(BlockQuoteBlock::class, $nested);
        self::assertEquals([[new InlineRun('inner')]], $nested->content);

        self::assertEquals([new InlineRun('after')], $blocks[0]->content[2]);
    }

    #[Test]
    public function parsesStandaloneImageAsImageBlock(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse('![a diagram](images/diagram.png)')->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ImageBlock::class, $blocks[0]);
        self::assertSame('images/diagram.png', $blocks[0]->src);
        self::assertSame('a diagram', $blocks[0]->alt);
    }

    #[Test]
    public function doesNotTreatImageMixedWithOtherTextAsImageBlock(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse('See ![icon](icon.png) for details.')->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
    }

    #[Test]
    public function parsesFencedCodeBlockVerbatim(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("```php\necho 1;\necho 2;\n```")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(CodeBlock::class, $blocks[0]);
        self::assertSame('php', $blocks[0]->language);
        self::assertSame("echo 1;\necho 2;", $blocks[0]->code);
    }

    #[Test]
    public function fencedCodeBlockContentIsNotParsedForEmphasis(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("```\n**not bold**\n```")->blocks;

        // Assert
        self::assertInstanceOf(CodeBlock::class, $blocks[0]);
        self::assertSame('**not bold**', $blocks[0]->code);
    }

    #[Test]
    public function parsesThematicBreak(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse('---')->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ThematicBreakBlock::class, $blocks[0]);
    }

    #[Test]
    public function thematicBreakAcceptsAsterisksAndUnderscores(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("***\n\n___")->blocks;

        // Assert
        self::assertCount(2, $blocks);
        self::assertInstanceOf(ThematicBreakBlock::class, $blocks[0]);
        self::assertInstanceOf(ThematicBreakBlock::class, $blocks[1]);
    }

    #[Test]
    public function parsesGfmTableWithAlignments(): void
    {
        // Arrange
        $markdown = "| Left | Center | Right |\n|:---|:---:|---:|\n| a | b | c |";

        // Act
        $blocks = MarkdownParser::parse($markdown)->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(TableBlock::class, $blocks[0]);
        self::assertEquals(
            [TableAlignment::Left, TableAlignment::Center, TableAlignment::Right],
            $blocks[0]->alignments,
        );
        self::assertCount(1, $blocks[0]->rows);
        self::assertEquals([new InlineRun('a')], $blocks[0]->rows[0][0]);
    }

    #[Test]
    public function parsesGfmTableHeaderCellWithEscapedPipe(): void
    {
        // Arrange
        $markdown = "| A \\| B | C |\n|---|---|\n| x | y |";

        // Act
        $blocks = MarkdownParser::parse($markdown)->blocks;

        // Assert: the escaped pipe stays inside the first header cell rather
        // than being treated as a column separator, so there are still only
        // two columns.
        self::assertCount(1, $blocks);
        self::assertInstanceOf(TableBlock::class, $blocks[0]);
        self::assertCount(2, $blocks[0]->header);
        self::assertEquals([new InlineRun('A | B')], $blocks[0]->header[0]);
    }

    #[Test]
    public function doesNotTreatALineOfDashesContainingTextAsATableDelimiterRow(): void
    {
        // Arrange: looks superficially table-like (a "|" line followed by a
        // dash-containing line) but the second line isn't a valid delimiter
        // row, so this must not be parsed as a table.
        $markdown = "Not | a table\njust - some text";

        // Act
        $blocks = MarkdownParser::parse($markdown)->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
    }

    #[Test]
    public function discardsRawHtmlBlock(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("<div class=\"note\">\n<p>raw html</p>\n</div>\n\nReal paragraph.")->blocks;

        // Assert: the HTML block is silently dropped, not rendered as literal text.
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
        self::assertEquals([new InlineRun('Real paragraph.')], $blocks[0]->runs);
    }

    #[Test]
    public function discardsHtmlComment(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("<!-- a comment -->\n\nReal paragraph.")->blocks;

        // Assert
        self::assertCount(1, $blocks);
        self::assertInstanceOf(ParagraphBlock::class, $blocks[0]);
    }

    #[Test]
    public function parsesFootnoteReferencesAndReturnsTheirDefinitionsSeparately(): void
    {
        // Arrange / Act
        $parsed = MarkdownParser::parse(
            "First claim[^a].\n\nSecond claim[^b].\n\n[^a]: Source A.\n[^b]: Source B.",
        );

        // Assert: references are numbered by order of first appearance (a=1,
        // b=2); definitions come back via ->footnotes, not appended to ->blocks
        // — placing them is MarkdownFlow's job, driven by FootnotePlacement.
        self::assertCount(2, $parsed->blocks);

        self::assertInstanceOf(ParagraphBlock::class, $parsed->blocks[0]);
        self::assertEquals(
            [new InlineRun('First claim'), new InlineRun('[1]', footnoteNumber: 1), new InlineRun('.')],
            $parsed->blocks[0]->runs,
        );

        self::assertInstanceOf(ParagraphBlock::class, $parsed->blocks[1]);
        self::assertEquals(
            [new InlineRun('Second claim'), new InlineRun('[2]', footnoteNumber: 2), new InlineRun('.')],
            $parsed->blocks[1]->runs,
        );

        self::assertCount(2, $parsed->footnotes);
        self::assertEquals([new InlineRun('Source A.')], $parsed->footnotes[0]);
        self::assertEquals([new InlineRun('Source B.')], $parsed->footnotes[1]);
    }

    #[Test]
    public function numbersFootnoteReferencesByOrderOfFirstUseNotDefinitionOrder(): void
    {
        // Arrange / Act: "b" is referenced first in the body even though "a" is defined first.
        $parsed = MarkdownParser::parse("Uses b[^b] then a[^a].\n\n[^a]: Def A.\n[^b]: Def B.");

        // Assert
        self::assertInstanceOf(ParagraphBlock::class, $parsed->blocks[0]);
        self::assertEquals(
            [
                new InlineRun('Uses b'),
                new InlineRun('[1]', footnoteNumber: 1),
                new InlineRun(' then a'),
                new InlineRun('[2]', footnoteNumber: 2),
                new InlineRun('.'),
            ],
            $parsed->blocks[0]->runs,
        );

        // footnotes[0] is number 1 ("b"), footnotes[1] is number 2 ("a").
        self::assertEquals([new InlineRun('Def B.')], $parsed->footnotes[0]);
        self::assertEquals([new InlineRun('Def A.')], $parsed->footnotes[1]);
    }

    #[Test]
    public function rendersUndefinedForAReferenceWithNoMatchingDefinition(): void
    {
        // Arrange / Act
        $parsed = MarkdownParser::parse('Claim[^missing].');

        // Assert
        self::assertEquals([new InlineRun('(undefined)')], $parsed->footnotes[0]);
    }

    #[Test]
    public function dropsADefinitionWithNoMatchingReference(): void
    {
        // Arrange / Act: a definition with no reference is dropped entirely
        // (nothing would ever point a reader to it), and the source line
        // it's defined on doesn't leak into the blocks either.
        $parsed = MarkdownParser::parse("Plain paragraph.\n\n[^unused]: Never referenced.");

        // Assert
        self::assertCount(1, $parsed->blocks);
        self::assertInstanceOf(ParagraphBlock::class, $parsed->blocks[0]);
        self::assertSame([], $parsed->footnotes);
    }

    #[Test]
    public function blankLinesAreSkippedBetweenBlocks(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("\n\n# Title\n\n\n\nParagraph\n\n")->blocks;

        // Assert
        self::assertCount(2, $blocks);
    }

    #[Test]
    public function returnsEmptyListForBlankInput(): void
    {
        // Arrange / Act
        $blocks = MarkdownParser::parse("   \n\n  ")->blocks;

        // Assert
        self::assertSame([], $blocks);
    }
}
