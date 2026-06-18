<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\Internal\Inline;

/**
 * Parses a single block's inline Markdown (emphasis, code spans, links) into
 * a flat list of InlineRun values.
 *
 * Supported syntax:
 *   - `code`                  → code run
 *   - **bold** / __bold__     → bold run
 *   - *italic* / _italic_     → italic run
 *   - ***both*** / ___both___ → bold + italic run
 *   - [text](url)             → run(s) carrying $linkUrl, clickable once rendered
 *   - ![alt](url)             → plain run containing only the alt text
 *   - [^label]                → footnote reference, replaced with "[N]" — see $footnoteOrder
 *   - \X                      → literal X for any ASCII punctuation X
 *
 * Emphasis nests: the content captured between a pair of delimiters is
 * itself parsed recursively, inheriting the enclosing bold/italic/link
 * state, so `**bold *and* more**` produces a bold run, a bold+italic run,
 * and another bold run rather than treating the inner `*…*` as literal
 * asterisks, and `[**bold link**](url)` keeps the link clickable across its
 * bold run too.
 *
 * Nesting the *same* delimiter character inside itself (e.g.
 * `_italic __and__ more_`) is ambiguous for this lexer — like the
 * non-nested matcher before it, the lazy single-character match can close
 * prematurely on the first marker character it sees, even when that
 * character is actually part of an inner double-marker pair. Mixing
 * delimiter families for the inner/outer pair (`**bold *and* more**`,
 * `_italic **and** more_`) is unambiguous and the supported way to nest.
 *
 * @internal
 */
final class InlineParser
{
    private const ESCAPABLE = '\\`*_{}[]()#+-.!';

    /**
     * @param array<string, int> $footnoteOrder Maps footnote label to its
     *     sequential number, assigned in order of first reference across
     *     the whole document. Pass the SAME array by reference across every
     *     parse() call for one document so numbering stays consistent; see
     *     MarkdownParser, which threads this through every call site.
     * @return list<InlineRun>
     */
    public static function parse(string $text, array &$footnoteOrder = []): array
    {
        return self::parseWithStyle($text, bold: false, italic: false, linkUrl: null, footnoteOrder: $footnoteOrder);
    }

    /**
     * @param array<string, int> $footnoteOrder
     * @return list<InlineRun>
     */
    private static function parseWithStyle(
        string $text,
        bool $bold,
        bool $italic,
        ?string $linkUrl,
        array &$footnoteOrder,
    ): array {
        $runs = [];
        $buffer = '';
        $length = strlen($text);
        $pos = 0;

        while ($pos < $length) {
            // Backslash escape.
            if ($text[$pos] === '\\' && $pos + 1 < $length && str_contains(self::ESCAPABLE, $text[$pos + 1])) {
                $buffer .= $text[$pos + 1];
                $pos += 2;

                continue;
            }

            // Inline code span — never styled by an enclosing emphasis, but stays part of a link.
            if (preg_match('/\G`([^`]+)`/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                $runs[] = new InlineRun($matches[1], code: true, linkUrl: $linkUrl);
                $pos += strlen($matches[0]);

                continue;
            }

            // Bold + italic: ***text*** or ___text___.
            if (preg_match('/\G(\*\*\*|___)(.+?)\1/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                array_push($runs, ...self::parseWithStyle(
                    $matches[2],
                    bold: true,
                    italic: true,
                    linkUrl: $linkUrl,
                    footnoteOrder: $footnoteOrder,
                ));
                $pos += strlen($matches[0]);

                continue;
            }

            // Bold: **text** or __text__.
            if (preg_match('/\G(\*\*|__)(.+?)\1/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                array_push($runs, ...self::parseWithStyle(
                    $matches[2],
                    bold: true,
                    italic: $italic,
                    linkUrl: $linkUrl,
                    footnoteOrder: $footnoteOrder,
                ));
                $pos += strlen($matches[0]);

                continue;
            }

            // Italic: *text* or _text_.
            if (preg_match('/\G(\*|_)(.+?)\1/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                array_push($runs, ...self::parseWithStyle(
                    $matches[2],
                    bold: $bold,
                    italic: true,
                    linkUrl: $linkUrl,
                    footnoteOrder: $footnoteOrder,
                ));
                $pos += strlen($matches[0]);

                continue;
            }

            // Footnote reference: [^label] — replaced with "[N]", numbered by
            // order of first reference. Checked before the image/link
            // patterns since [^label] never has a following "(url)" part.
            if (preg_match('/\G\[\^([^\]]+)\]/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                $label = $matches[1];

                if (!isset($footnoteOrder[$label])) {
                    $footnoteOrder[$label] = count($footnoteOrder) + 1;
                }

                $number = $footnoteOrder[$label];
                $runs[] = new InlineRun(
                    '[' . $number . ']',
                    bold: $bold,
                    italic: $italic,
                    linkUrl: $linkUrl,
                    footnoteNumber: $number,
                );
                $pos += strlen($matches[0]);

                continue;
            }

            // Image: ![alt](url) — rendered as its alt text only; the image itself is not drawn.
            if (preg_match('/\G!\[([^\]]*)\]\(([^)]*)\)/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);

                if ($matches[1] !== '') {
                    $runs[] = new InlineRun($matches[1], bold: $bold, italic: $italic, linkUrl: $linkUrl);
                }

                $pos += strlen($matches[0]);

                continue;
            }

            // Link: [text](url) — its visible text becomes a clickable run.
            if (preg_match('/\G\[([^\]]*)\]\(([^)]*)\)/', $text, $matches, 0, $pos) === 1) {
                self::flush($runs, $buffer, $bold, $italic, $linkUrl);
                array_push($runs, ...self::parseWithStyle(
                    $matches[1],
                    bold: $bold,
                    italic: $italic,
                    linkUrl: $matches[2],
                    footnoteOrder: $footnoteOrder,
                ));
                $pos += strlen($matches[0]);

                continue;
            }

            // Plain character — advance by one UTF-8 code point.
            if (preg_match('/\G./us', $text, $matches, 0, $pos) === 1) {
                $buffer .= $matches[0];
                $pos += strlen($matches[0]);

                continue;
            }

            // Should be unreachable for valid UTF-8 input; avoid an infinite loop otherwise.
            $pos++;
        }

        self::flush($runs, $buffer, $bold, $italic, $linkUrl);

        return $runs;
    }

    /** @param list<InlineRun> $runs */
    private static function flush(array &$runs, string &$buffer, bool $bold, bool $italic, ?string $linkUrl): void
    {
        if ($buffer !== '') {
            $runs[] = new InlineRun($buffer, bold: $bold, italic: $italic, linkUrl: $linkUrl);
        }

        $buffer = '';
    }
}
