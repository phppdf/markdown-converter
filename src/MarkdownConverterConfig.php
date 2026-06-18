<?php

declare(strict_types=1);

namespace PhpPdf\Markdown;

use InvalidArgumentException;
use PhpPdf\Builder\PdfPageSize;
use PhpPdf\Color\Color;
use PhpPdf\Font\FontMetrics;
use PhpPdf\Font\Type1FontMetrics;

/**
 * Configuration for the Markdown-to-PDF converter.
 *
 * Controls the output page dimensions, margins, base typography, the
 * relative size of each heading level, the styling of code blocks,
 * blockquotes, and tables, and which fonts are used for each text role.
 * All dimension values are in PDF points (1 pt = 1/72 inch).
 *
 * Font registration is entirely your responsibility: this config only
 * tells the converter which resource name (e.g. "F1") and FontMetrics to
 * use for each role when measuring and drawing text. You must register a
 * font under each configured name yourself — via
 * PdfPageBuilder::useType1Font() / useEmbeddedFont() on every page, or
 * PdfDocumentBuilder::globalFont() / globalEmbeddedFont() once for the
 * whole document — before calling PdfDocumentBuilder::build(). The
 * defaults below assume the standard Type 1 Helvetica family and Courier,
 * registered under F1–F5:
 *
 *   $builder->globalFont('F1', 'Helvetica')
 *       ->globalFont('F2', 'Helvetica-Bold')
 *       ->globalFont('F3', 'Helvetica-Oblique')
 *       ->globalFont('F4', 'Helvetica-BoldOblique')
 *       ->globalFont('F5', 'Courier');
 *
 * To use different fonts (including embedded TrueType/OpenType), call
 * setRegularFont() / setBoldFont() / setItalicFont() / setBoldItalicFont()
 * / setCodeFont() with a resource name of your choosing and the matching
 * FontMetrics, then register that same font under that same name yourself.
 * Getting the metrics right matters: word-wrapping is computed from them,
 * so mismatched metrics will measure and wrap text incorrectly.
 */
final class MarkdownConverterConfig
{
    // ── Page geometry ────────────────────────────────────────────────────────

    private int $pageWidth;
    private int $pageHeight;
    private float $marginTop = 72.0;
    private float $marginRight = 72.0;
    private float $marginBottom = 72.0;
    private float $marginLeft = 72.0;

    // ── Typography ───────────────────────────────────────────────────────────

    private float $baseFontSize = 11.0;
    private float $lineHeightMultiplier = 1.4;

    /**
     * Font-size multiplier per heading level (1–6), relative to baseFontSize.
     *
     * @var array<int, float>
     */
    private array $headingScale = [
        1 => 2.0,
        2 => 1.6,
        3 => 1.3,
        4 => 1.15,
        5 => 1.0,
        6 => 0.9,
    ];

    // ── Block styling ────────────────────────────────────────────────────────

    private Color $codeBackgroundColor;
    private Color $quoteRuleColor;
    private float $quoteIndent = 14.0;
    private float $tablePadding = 6.0;
    private FootnotePlacement $footnotePlacement = FootnotePlacement::PerPage;
    private string $footnotesHeading = 'Footnotes';

    // ── Font roles ───────────────────────────────────────────────────────────

    private string $regularFontName = 'F1';
    private string $boldFontName = 'F2';
    private string $italicFontName = 'F3';
    private string $boldItalicFontName = 'F4';
    private string $codeFontName = 'F5';

    private FontMetrics $regularFontMetrics;
    private FontMetrics $boldFontMetrics;
    private FontMetrics $italicFontMetrics;
    private FontMetrics $boldItalicFontMetrics;
    private FontMetrics $codeFontMetrics;

    public function __construct()
    {
        [$this->pageWidth, $this->pageHeight] = PdfPageSize::A4;
        $this->codeBackgroundColor = Color::fromHex('#f3f3f3');
        $this->quoteRuleColor = Color::fromHex('#bbbbbb');
        $this->regularFontMetrics = Type1FontMetrics::helvetica();
        $this->boldFontMetrics = Type1FontMetrics::helveticaBold();
        $this->italicFontMetrics = Type1FontMetrics::helveticaOblique();
        $this->boldItalicFontMetrics = Type1FontMetrics::helveticaBoldOblique();
        $this->codeFontMetrics = Type1FontMetrics::courier();
    }

    // ── Page geometry getters/setters ────────────────────────────────────────

    public function getPageWidth(): int
    {
        return $this->pageWidth;
    }

    public function setPageWidth(int $width): self
    {
        $this->pageWidth = $width;

        return $this;
    }

    public function getPageHeight(): int
    {
        return $this->pageHeight;
    }

    public function setPageHeight(int $height): self
    {
        $this->pageHeight = $height;

        return $this;
    }

    public function getMarginTop(): float
    {
        return $this->marginTop;
    }

    public function setMarginTop(float $margin): self
    {
        $this->marginTop = $margin;

        return $this;
    }

    public function getMarginRight(): float
    {
        return $this->marginRight;
    }

    public function setMarginRight(float $margin): self
    {
        $this->marginRight = $margin;

        return $this;
    }

    public function getMarginBottom(): float
    {
        return $this->marginBottom;
    }

    public function setMarginBottom(float $margin): self
    {
        $this->marginBottom = $margin;

        return $this;
    }

    public function getMarginLeft(): float
    {
        return $this->marginLeft;
    }

    public function setMarginLeft(float $margin): self
    {
        $this->marginLeft = $margin;

        return $this;
    }

    // ── Typography getters/setters ───────────────────────────────────────────

    public function getBaseFontSize(): float
    {
        return $this->baseFontSize;
    }

    public function setBaseFontSize(float $size): self
    {
        $this->baseFontSize = $size;

        return $this;
    }

    public function getLineHeightMultiplier(): float
    {
        return $this->lineHeightMultiplier;
    }

    public function setLineHeightMultiplier(float $multiplier): self
    {
        $this->lineHeightMultiplier = $multiplier;

        return $this;
    }

    /**
     * Sets the font-size multiplier applied to a heading level (relative to
     * getBaseFontSize()).
     *
     * @throws \InvalidArgumentException when $level is outside [1, 6].
     */
    public function setHeadingScale(int $level, float $multiplier): self
    {
        if ($level < 1 || $level > 6) {
            throw new InvalidArgumentException("Heading level must be between 1 and 6; got {$level}.");
        }

        $this->headingScale[$level] = $multiplier;

        return $this;
    }

    /** Returns the font size in points for the given heading level (1–6). */
    public function headingFontSize(int $level): float
    {
        $multiplier = $this->headingScale[$level] ?? 1.0;

        return $this->baseFontSize * $multiplier;
    }

    // ── Block styling getters/setters ───────────────────────────────────────

    /** Background fill behind fenced code blocks. Defaults to a light grey. */
    public function getCodeBackgroundColor(): Color
    {
        return $this->codeBackgroundColor;
    }

    public function setCodeBackgroundColor(Color $color): self
    {
        $this->codeBackgroundColor = $color;

        return $this;
    }

    /** Colour of the vertical rule drawn beside blockquotes. Defaults to a mid grey. */
    public function getQuoteRuleColor(): Color
    {
        return $this->quoteRuleColor;
    }

    public function setQuoteRuleColor(Color $color): self
    {
        $this->quoteRuleColor = $color;

        return $this;
    }

    /** Horizontal distance in points blockquote text is indented past its rule. */
    public function getQuoteIndent(): float
    {
        return $this->quoteIndent;
    }

    public function setQuoteIndent(float $indent): self
    {
        $this->quoteIndent = $indent;

        return $this;
    }

    /** Cell padding in points applied on all sides of every table cell. */
    public function getTablePadding(): float
    {
        return $this->tablePadding;
    }

    public function setTablePadding(float $padding): self
    {
        $this->tablePadding = $padding;

        return $this;
    }

    /**
     * Where footnote definitions are rendered when the Markdown contains at
     * least one `[^label]` reference. Defaults to FootnotePlacement::PerPage
     * (reserved space at the bottom of whichever page first references each
     * one — the conventional placement for printed documents).
     */
    public function getFootnotePlacement(): FootnotePlacement
    {
        return $this->footnotePlacement;
    }

    public function setFootnotePlacement(FootnotePlacement $placement): self
    {
        $this->footnotePlacement = $placement;

        return $this;
    }

    /**
     * Heading text for the footnotes section appended to the end of the
     * document. Only used when getFootnotePlacement() is
     * FootnotePlacement::DocumentEnd. Defaults to "Footnotes" — override
     * for a non-English document (e.g. "Notes de bas de page", "Fußnoten").
     */
    public function getFootnotesHeading(): string
    {
        return $this->footnotesHeading;
    }

    public function setFootnotesHeading(string $heading): self
    {
        $this->footnotesHeading = $heading;

        return $this;
    }

    // ── Font role getters/setters ───────────────────────────────────────────

    /** Resource name used for unstyled text. Defaults to "F1" (Helvetica). */
    public function getRegularFontName(): string
    {
        return $this->regularFontName;
    }

    /** Metrics used to measure and wrap unstyled text. */
    public function getRegularFontMetrics(): FontMetrics
    {
        return $this->regularFontMetrics;
    }

    /**
     * Sets the resource name and metrics used for unstyled text.
     *
     * You must register a font under $name yourself (see the class
     * docblock) before calling PdfDocumentBuilder::build().
     */
    public function setRegularFont(string $name, FontMetrics $metrics): self
    {
        $this->regularFontName = $name;
        $this->regularFontMetrics = $metrics;

        return $this;
    }

    /** Resource name used for bold text. Defaults to "F2" (Helvetica-Bold). */
    public function getBoldFontName(): string
    {
        return $this->boldFontName;
    }

    /** Metrics used to measure and wrap bold text. */
    public function getBoldFontMetrics(): FontMetrics
    {
        return $this->boldFontMetrics;
    }

    /**
     * Sets the resource name and metrics used for bold text.
     *
     * You must register a font under $name yourself (see the class
     * docblock) before calling PdfDocumentBuilder::build().
     */
    public function setBoldFont(string $name, FontMetrics $metrics): self
    {
        $this->boldFontName = $name;
        $this->boldFontMetrics = $metrics;

        return $this;
    }

    /** Resource name used for italic text. Defaults to "F3" (Helvetica-Oblique). */
    public function getItalicFontName(): string
    {
        return $this->italicFontName;
    }

    /** Metrics used to measure and wrap italic text. */
    public function getItalicFontMetrics(): FontMetrics
    {
        return $this->italicFontMetrics;
    }

    /**
     * Sets the resource name and metrics used for italic text.
     *
     * You must register a font under $name yourself (see the class
     * docblock) before calling PdfDocumentBuilder::build().
     */
    public function setItalicFont(string $name, FontMetrics $metrics): self
    {
        $this->italicFontName = $name;
        $this->italicFontMetrics = $metrics;

        return $this;
    }

    /** Resource name used for bold italic text. Defaults to "F4" (Helvetica-BoldOblique). */
    public function getBoldItalicFontName(): string
    {
        return $this->boldItalicFontName;
    }

    /** Metrics used to measure and wrap bold italic text. */
    public function getBoldItalicFontMetrics(): FontMetrics
    {
        return $this->boldItalicFontMetrics;
    }

    /**
     * Sets the resource name and metrics used for bold italic text.
     *
     * You must register a font under $name yourself (see the class
     * docblock) before calling PdfDocumentBuilder::build().
     */
    public function setBoldItalicFont(string $name, FontMetrics $metrics): self
    {
        $this->boldItalicFontName = $name;
        $this->boldItalicFontMetrics = $metrics;

        return $this;
    }

    /** Resource name used for inline code and fenced code blocks. Defaults to "F5" (Courier). */
    public function getCodeFontName(): string
    {
        return $this->codeFontName;
    }

    /** Metrics used to measure and wrap code text. */
    public function getCodeFontMetrics(): FontMetrics
    {
        return $this->codeFontMetrics;
    }

    /**
     * Sets the resource name and metrics used for inline code and fenced
     * code blocks.
     *
     * You must register a font under $name yourself (see the class
     * docblock) before calling PdfDocumentBuilder::build().
     */
    public function setCodeFont(string $name, FontMetrics $metrics): self
    {
        $this->codeFontName = $name;
        $this->codeFontMetrics = $metrics;

        return $this;
    }

    // ── Geometry helpers ─────────────────────────────────────────────────────

    /** Returns the usable content width (page width minus left and right margins). */
    public function contentWidth(): float
    {
        return $this->pageWidth - $this->marginLeft - $this->marginRight;
    }

    /** Returns the usable content height (page height minus top and bottom margins). */
    public function contentHeight(): float
    {
        return $this->pageHeight - $this->marginTop - $this->marginBottom;
    }
}
