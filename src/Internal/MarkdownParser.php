<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal;

use PhpPdf\Markdown\Internal\Block\BlockQuoteBlock;
use PhpPdf\Markdown\Internal\Block\CodeBlock;
use PhpPdf\Markdown\Internal\Block\HeadingBlock;
use PhpPdf\Markdown\Internal\Block\ImageBlock;
use PhpPdf\Markdown\Internal\Block\ListBlock;
use PhpPdf\Markdown\Internal\Block\ListItem;
use PhpPdf\Markdown\Internal\Block\ParagraphBlock;
use PhpPdf\Markdown\Internal\Block\TableAlignment;
use PhpPdf\Markdown\Internal\Block\TableBlock;
use PhpPdf\Markdown\Internal\Block\ThematicBreakBlock;
use PhpPdf\Markdown\Internal\Inline\InlineParser;

use function assert;

/**
 * Line-based block scanner. Splits Markdown source into a flat list of
 * MarkdownBlock values; inline syntax within each block is delegated to
 * InlineParser.
 *
 * Supported blocks: ATX headings (#…######) and Setext headings (underlined
 * with `===`/`---`), paragraphs, unordered/ordered lists (lazy continuation
 * lines, nested sub-lists indented under an item, GFM task-list checkboxes
 * via `- [ ]`/`- [x]`), blockquotes (possibly multiple paragraphs, nested
 * blockquotes via a further `>`), fenced code blocks (``` or ~~~), thematic
 * breaks (---, ***, ___), GFM pipe tables, standalone images (`![alt](src)`
 * alone on its own paragraph — see ImageBlock), raw HTML blocks (a line
 * starting with a tag or `<!--`, discarded rather than rendered — this
 * converter has no HTML renderer, so silently dropping it beats leaking the
 * literal markup into the PDF as text), and footnotes (`[^label]` inline,
 * defined anywhere via a `[^label]: text` line). A footnote reference
 * becomes a "[N]" marker numbered by order of first reference; every
 * referenced definition is parsed and returned alongside the blocks (see
 * ParsedMarkdown) rather than placed anywhere here — MarkdownFlow decides
 * where they go, per MarkdownConverterConfig's FootnotePlacement.
 * A definition with no matching reference is dropped (nothing would ever
 * point a reader to it); a reference with no matching definition still
 * gets a number but its definition text is "(undefined)".
 *
 * Known limitations (v1): no loose lists (a blank line always ends the
 * whole list rather than separating paragraphs within one item), and
 * footnote definitions are single-line only (no lazy continuation lines).
 *
 * @internal
 */
final class MarkdownParser
{
    private const LIST_ITEM_PATTERN = '/^([-*+]|\d{1,9}[.)])[ \t]+(.*)$/';

    public static function parse(string $markdown): ParsedMarkdown
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $count = count($lines);
        $blocks = [];
        $footnoteOrder = [];
        $footnoteDefs = [];
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                $i++;

                continue;
            }

            if (self::isHtmlBlockStart($line)) {
                $i = self::skipHtmlBlock($lines, $i, $count);

                continue;
            }

            if (preg_match('/^ {0,3}\[\^([^\]]+)\]:[ \t]*(.*)$/', $line, $footnoteMatch) === 1) {
                $footnoteDefs[$footnoteMatch[1]] = $footnoteMatch[2];
                $i++;

                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})[ \t]*(\S*)[ \t]*$/', $line, $matches) === 1) {
                $i = self::parseCodeBlock($lines, $i, $count, $matches[1], $matches[2], $blocks);

                continue;
            }

            if (self::isThematicBreak($line)) {
                $blocks[] = new ThematicBreakBlock();
                $i++;

                continue;
            }

            if (preg_match('/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?[ \t]*$/', $line, $matches) === 1) {
                $text = preg_replace('/(?:^|[ \t])#+[ \t]*$/', '', $matches[2] ?? '') ?? '';
                $blocks[] = new HeadingBlock(strlen($matches[1]), InlineParser::parse(trim($text), $footnoteOrder));
                $i++;

                continue;
            }

            if (preg_match('/^ {0,3}>/', $line) === 1) {
                $i = self::parseBlockQuote($lines, $i, $count, $blocks, $footnoteOrder);

                continue;
            }

            if (
                str_contains($line, '|')
                && $i + 1 < $count
                && self::isTableDelimiterRow($lines[$i + 1])
            ) {
                $i = self::parseTable($lines, $i, $count, $blocks, $footnoteOrder);

                continue;
            }

            if (self::matchListItem($line, 0) !== null) {
                $i = self::parseList($lines, $i, $count, $blocks, 0, $footnoteOrder);

                continue;
            }

            if ($i + 1 < $count && preg_match('/^ {0,3}(=+|-+)[ \t]*$/', $lines[$i + 1], $underlineMatch) === 1) {
                $level = $underlineMatch[1][0] === '=' ? 1 : 2;
                $blocks[] = new HeadingBlock($level, InlineParser::parse(trim($line), $footnoteOrder));
                $i += 2;

                continue;
            }

            $i = self::parseParagraph($lines, $i, $count, $blocks, $footnoteOrder);
        }

        $footnotes = self::buildFootnoteDefinitions($footnoteOrder, $footnoteDefs);

        return new ParsedMarkdown($blocks, $footnotes);
    }

    /**
     * Parses one definition per label in $footnoteOrder (index 0 = footnote
     * number 1, ...), numbered to match the "[N]" markers already in the
     * parsed blocks. A referenced label with no matching definition still
     * gets a slot, with "(undefined)" as its text, rather than vanishing.
     *
     * @param array<string, int> $footnoteOrder
     * @param array<string, string> $footnoteDefs
     * @return list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>>
     */
    private static function buildFootnoteDefinitions(array &$footnoteOrder, array $footnoteDefs): array
    {
        if ($footnoteOrder === []) {
            return [];
        }

        $labelsByNumber = array_flip($footnoteOrder);
        $definitions = [];

        // Snapshot the count: a definition's own text could reference a not-yet-seen
        // label, growing $footnoteOrder mid-loop. That new label still gets numbered
        // for any later "[N]" markers, just not its own slot in this list — building
        // $labelsByNumber from a live array we're still extending would risk it.
        $referencedCount = count($footnoteOrder);

        for ($n = 1; $n <= $referencedCount; $n++) {
            $label = $labelsByNumber[$n];
            $text = $footnoteDefs[$label] ?? '(undefined)';
            $definitions[] = InlineParser::parse($text, $footnoteOrder);
        }

        return $definitions;
    }

    /**
     * @param list<string> $lines
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     */
    private static function parseCodeBlock(
        array $lines,
        int $i,
        int $count,
        string $fence,
        string $language,
        array &$blocks,
    ): int {
        $fenceChar = $fence[0];
        $fenceLength = strlen($fence);
        $closePattern = '/^ {0,3}' . preg_quote($fenceChar, '/') . '{' . $fenceLength . ',}[ \t]*$/';
        $codeLines = [];
        $i++;

        while ($i < $count) {
            if (preg_match($closePattern, $lines[$i]) === 1) {
                $i++;

                break;
            }

            $codeLines[] = $lines[$i];
            $i++;
        }

        $blocks[] = new CodeBlock(implode("\n", $codeLines), $language);

        return $i;
    }

    /**
     * @param list<string> $lines
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     * @param array<string, int> $footnoteOrder
     */
    private static function parseBlockQuote(
        array $lines,
        int $i,
        int $count,
        array &$blocks,
        array &$footnoteOrder,
    ): int {
        $quoteLines = [];

        while ($i < $count && preg_match('/^ {0,3}>[ \t]?(.*)$/', $lines[$i], $matches) === 1) {
            $quoteLines[] = $matches[1];
            $i++;
        }

        $blocks[] = new BlockQuoteBlock(self::parseBlockQuoteContent($quoteLines, $footnoteOrder));

        return $i;
    }

    /**
     * Splits a blockquote's unwrapped content lines (already stripped of one
     * level of `> ` prefix) into paragraphs and nested BlockQuoteBlock
     * values. A blank line (e.g. a bare `>`) separates paragraphs instead of
     * collapsing them into one; a further `>` nesting level starts a nested
     * blockquote instead of being flattened into the parent's text.
     *
     * @param list<string> $lines
     * @param array<string, int> $footnoteOrder
     * @return list<list<\PhpPdf\Markdown\Internal\Inline\InlineRun>|BlockQuoteBlock>
     */
    private static function parseBlockQuoteContent(array $lines, array &$footnoteOrder): array
    {
        $content = [];
        $paragraphLines = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (trim($line) === '') {
                if ($paragraphLines !== []) {
                    $content[] = InlineParser::parse(trim(implode(' ', $paragraphLines)), $footnoteOrder);
                    $paragraphLines = [];
                }

                $i++;

                continue;
            }

            if (preg_match('/^ {0,3}>[ \t]?(.*)$/', $line, $matches) === 1) {
                if ($paragraphLines !== []) {
                    $content[] = InlineParser::parse(trim(implode(' ', $paragraphLines)), $footnoteOrder);
                    $paragraphLines = [];
                }

                $nestedLines = [];

                while ($i < $count && preg_match('/^ {0,3}>[ \t]?(.*)$/', $lines[$i], $nestedMatch) === 1) {
                    $nestedLines[] = $nestedMatch[1];
                    $i++;
                }

                $nestedContent = self::parseBlockQuoteContent($nestedLines, $footnoteOrder);

                if ($nestedContent !== []) {
                    $content[] = new BlockQuoteBlock($nestedContent);
                }

                continue;
            }

            $paragraphLines[] = $line;
            $i++;
        }

        if ($paragraphLines !== []) {
            $content[] = InlineParser::parse(trim(implode(' ', $paragraphLines)), $footnoteOrder);
        }

        return $content;
    }

    /**
     * Matches a list item marker on $line, requiring at least $baseIndent
     * leading spaces (tolerating up to 3 more, mirroring CommonMark's
     * top-level laxness at every nesting depth). Returns
     * [indent, marker, text] on success.
     *
     * @return array{0: int, 1: string, 2: string}|null
     */
    private static function matchListItem(string $line, int $baseIndent): ?array
    {
        $indent = strspn($line, ' ');

        if ($indent < $baseIndent || $indent > $baseIndent + 3) {
            return null;
        }

        if (preg_match(self::LIST_ITEM_PATTERN, substr($line, $indent), $matches) !== 1) {
            return null;
        }

        return [$indent, $matches[1], $matches[2]];
    }

    private static function isOrderedMarker(string $marker): bool
    {
        return !in_array($marker, ['-', '*', '+'], true);
    }

    /**
     * @param list<string> $lines
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     * @param array<string, int> $footnoteOrder
     */
    private static function parseList(
        array $lines,
        int $i,
        int $count,
        array &$blocks,
        int $baseIndent,
        array &$footnoteOrder,
    ): int {
        $match = self::matchListItem($lines[$i], $baseIndent);
        assert($match !== null);
        [, $marker, $text] = $match;
        $ordered = self::isOrderedMarker($marker);
        $items = [];
        $itemLines = [$text];
        $childLines = [];
        $i++;

        while ($i < $count) {
            $next = $lines[$i];

            if (trim($next) === '') {
                break;
            }

            // Indentation deep enough to start (or continue) a nested
            // sub-list takes priority over sibling detection — otherwise a
            // child line like "  - nested" (indent 2) would fall inside the
            // sibling match's own 0-3 space tolerance and be mistaken for a
            // sibling of the *current* list instead of content of this item.
            $indent = strspn($next, ' ');
            $isNestedContent = $childLines !== []
                ? $indent >= $baseIndent + 2
                : ($indent >= $baseIndent + 2 && self::matchListItem($next, $indent) !== null);

            if ($isNestedContent) {
                $childLines[] = $next;
                $i++;

                continue;
            }

            $siblingMatch = self::matchListItem($next, $baseIndent);

            if ($siblingMatch !== null) {
                [, $siblingMarker, $siblingText] = $siblingMatch;

                if (self::isOrderedMarker($siblingMarker) !== $ordered) {
                    break;
                }

                $items[] = self::buildListItem($itemLines, $childLines, $footnoteOrder);
                $itemLines = [$siblingText];
                $childLines = [];
                $i++;

                continue;
            }

            if (self::startsBlock($next)) {
                break;
            }

            $itemLines[] = trim($next);
            $i++;
        }

        $items[] = self::buildListItem($itemLines, $childLines, $footnoteOrder);
        $blocks[] = new ListBlock($ordered, $items);

        return $i;
    }

    /**
     * @param list<string> $itemLines
     * @param list<string> $childLines Raw (still indented) lines belonging to nested sub-lists.
     * @param array<string, int> $footnoteOrder
     */
    private static function buildListItem(array $itemLines, array $childLines, array &$footnoteOrder): ListItem
    {
        $checked = null;

        if ($itemLines !== [] && preg_match('/^\[([ xX])\][ \t]+(.*)$/', $itemLines[0], $taskMatch) === 1) {
            $checked = strtolower($taskMatch[1]) === 'x';
            $itemLines[0] = $taskMatch[2];
        }

        $runs = InlineParser::parse(trim(implode(' ', $itemLines)), $footnoteOrder);

        return new ListItem($runs, self::parseNestedListBlocks($childLines, $footnoteOrder), $checked);
    }

    /**
     * @param list<string> $childLines
     * @param array<string, int> $footnoteOrder
     * @return list<ListBlock>
     */
    private static function parseNestedListBlocks(array $childLines, array &$footnoteOrder): array
    {
        if ($childLines === []) {
            return [];
        }

        $baseIndent = strspn($childLines[0], ' ');
        $count = count($childLines);
        $blocks = [];
        $i = 0;

        while ($i < $count) {
            $i = self::parseList($childLines, $i, $count, $blocks, $baseIndent, $footnoteOrder);
        }

        /** @var list<ListBlock> $blocks parseList() only ever appends ListBlock here. */
        return $blocks;
    }

    /**
     * @param list<string> $lines
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     * @param array<string, int> $footnoteOrder
     */
    private static function parseTable(array $lines, int $i, int $count, array &$blocks, array &$footnoteOrder): int
    {
        $parseCell = function (string $cell) use (&$footnoteOrder): array {
            return InlineParser::parse($cell, $footnoteOrder);
        };

        $header = array_map($parseCell, self::splitTableRow($lines[$i]));

        $alignments = array_map(
            static fn (string $cell): TableAlignment => self::alignmentFor($cell),
            self::splitTableRow($lines[$i + 1]),
        );

        $i += 2;
        $rows = [];

        while ($i < $count && trim($lines[$i]) !== '' && str_contains($lines[$i], '|')) {
            $rows[] = array_map($parseCell, self::splitTableRow($lines[$i]));
            $i++;
        }

        $blocks[] = new TableBlock($header, $alignments, $rows);

        return $i;
    }

    /**
     * @param list<string> $lines
     * @param list<\PhpPdf\Markdown\Internal\Block\MarkdownBlock> $blocks
     * @param array<string, int> $footnoteOrder
     */
    private static function parseParagraph(array $lines, int $i, int $count, array &$blocks, array &$footnoteOrder): int
    {
        $paragraphLines = [$lines[$i]];
        $i++;

        while ($i < $count && trim($lines[$i]) !== '' && !self::startsBlock($lines[$i])) {
            $paragraphLines[] = $lines[$i];
            $i++;
        }

        $text = trim(implode(' ', $paragraphLines));

        if (preg_match('/^!\[([^\]]*)\]\(([^)]*)\)$/', $text, $imageMatch) === 1) {
            $blocks[] = new ImageBlock($imageMatch[2], $imageMatch[1]);

            return $i;
        }

        $blocks[] = new ParagraphBlock(InlineParser::parse($text, $footnoteOrder));

        return $i;
    }

    private static function isThematicBreak(string $line): bool
    {
        return preg_match('/^ {0,3}([-*_])[ \t]*(?:\1[ \t]*){2,}$/', $line) === 1;
    }

    private static function isHtmlBlockStart(string $line): bool
    {
        return preg_match('/^ {0,3}<(?:!--|\/?[a-zA-Z][a-zA-Z0-9-]*)/', $line) === 1;
    }

    /**
     * Discards a raw HTML block (lines from $i up to the next blank line, or
     * end of input). This converter has no HTML renderer, so the markup is
     * dropped rather than leaked into the PDF as literal text.
     *
     * @param list<string> $lines
     */
    private static function skipHtmlBlock(array $lines, int $i, int $count): int
    {
        while ($i < $count && trim($lines[$i]) !== '') {
            $i++;
        }

        return $i;
    }

    /**
     * Delegates cell splitting to splitTableRow() — the same function used
     * to extract alignments from this row and cells from every other table
     * row — so an escaped pipe (`\|`) inside a delimiter row is recognised
     * identically here and there, rather than each having its own
     * pipe-splitting logic that could silently drift apart.
     */
    private static function isTableDelimiterRow(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '' || !str_contains($trimmed, '-')) {
            return false;
        }

        foreach (self::splitTableRow($trimmed) as $cell) {
            if (preg_match('/^:?-+:?$/', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function alignmentFor(string $cell): TableAlignment
    {
        $left = str_starts_with($cell, ':');
        $right = str_ends_with($cell, ':');

        return match (true) {
            $left && $right => TableAlignment::Center,
            $right => TableAlignment::Right,
            default => TableAlignment::Left,
        };
    }

    /** @return list<string> */
    private static function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\|/', '', $line) ?? $line;
        $line = preg_replace('/(?<!\\\\)\|$/', '', $line) ?? $line;
        $cells = preg_split('/(?<!\\\\)\|/', $line) ?: [$line];

        return array_map(
            static fn (string $cell): string => str_replace('\\|', '|', trim($cell)),
            $cells,
        );
    }

    private static function startsBlock(string $line): bool
    {
        if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line) === 1) {
            return true;
        }

        if (self::isThematicBreak($line)) {
            return true;
        }

        if (preg_match('/^ {0,3}#{1,6}(?:[ \t]|$)/', $line) === 1) {
            return true;
        }

        if (preg_match('/^ {0,3}>/', $line) === 1) {
            return true;
        }

        if (self::isHtmlBlockStart($line)) {
            return true;
        }

        if (preg_match('/^ {0,3}\[\^([^\]]+)\]:/', $line) === 1) {
            return true;
        }

        return self::matchListItem($line, 0) !== null;
    }
}
