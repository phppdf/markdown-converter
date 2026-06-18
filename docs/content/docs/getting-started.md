---
title: "Getting started"
weight: 10
---

## Installation

```bash
composer require phppdf/markdown-converter
```

## Your first PDF from Markdown

`MarkdownConverter::fromMarkdown()` never registers fonts for you — you must
register a font under each resource name `MarkdownConverterConfig` expects
(`F1`–`F5` by default) before calling `$builder->build()`.

```php
use PhpPdf\Builder\PdfDocumentBuilder;
use PhpPdf\Markdown\MarkdownConverter;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PhpPdf\Output\PdfMemoryOutput;
use PhpPdf\Serialization\PdfDocumentSerializer;

$markdown = "# Hello World\n\nWelcome to the PDF.";

$config = new MarkdownConverterConfig();

$builder = new PdfDocumentBuilder();
$builder->globalFont($config->getRegularFontName(), 'Helvetica')
    ->globalFont($config->getBoldFontName(), 'Helvetica-Bold')
    ->globalFont($config->getItalicFontName(), 'Helvetica-Oblique')
    ->globalFont($config->getBoldItalicFontName(), 'Helvetica-BoldOblique')
    ->globalFont($config->getCodeFontName(), 'Courier');

MarkdownConverter::fromMarkdown($markdown, $config, $builder);

$output = new PdfMemoryOutput();
(new PdfDocumentSerializer($output))->writeDocument($builder->build());

header('Content-Type: application/pdf');
echo $output->getContent();
```

## Custom layout

```php
use PhpPdf\Markdown\MarkdownConverterConfig;

$config = new MarkdownConverterConfig();
$config->setMarginTop(54)->setMarginBottom(54);
$config->setBaseFontSize(10);
```

Because `MarkdownConverter::fromMarkdown()` returns the same
`PdfDocumentBuilder` you passed in — a plain builder from
[phppdf/phppdf](https://github.com/phppdf/phppdf) — you can:

- Prepend / append additional pages, hand-crafted or from other sources
- Add metadata, encryption, outlines, or digital signatures
- Apply compression

## Custom fonts

`MarkdownConverterConfig` only tells the converter which resource name and
`FontMetrics` to use for each text role (regular, bold, italic, bold italic,
code) — registering the font under that name is up to you. Use
`setRegularFont()` / `setBoldFont()` / `setItalicFont()` / `setBoldItalicFont()`
/ `setCodeFont()` to point a role at your own resource name and metrics, then
register a matching embedded font under that same name:

```php
use PhpPdf\Builder\PdfDocumentBuilder;
use PhpPdf\Font\TrueTypeFont;
use PhpPdf\Font\TrueTypeFontMetrics;
use PhpPdf\Markdown\MarkdownConverterConfig;

$font = TrueTypeFont::fromFile('/fonts/Roboto-Regular.ttf');

$config = new MarkdownConverterConfig();
$config->setRegularFont('F1', TrueTypeFontMetrics::fromFont($font));

$builder = new PdfDocumentBuilder();
$builder->globalEmbeddedFont('F1', $font);
```

Getting the metrics right matters: word-wrapping is computed from them, so
mismatched metrics will measure and wrap text incorrectly.

## Next steps

- [Supported Markdown](../supported-markdown/) — syntax understood by the converter and known limitations
