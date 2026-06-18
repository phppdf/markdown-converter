# markdown-converter

[![CI](https://github.com/phppdf/markdown-converter/actions/workflows/ci.yml/badge.svg)](https://github.com/phppdf/markdown-converter/actions/workflows/ci.yml)

Convert Markdown into PDF documents on top of [phppdf/phppdf](https://github.com/phppdf/phppdf).

Markdown is parsed directly into layout primitives (text, lists, tables) — there is no intermediate HTML step.

## Installation

```bash
composer require phppdf/markdown-converter
```

Requires PHP 8.4+.

## Usage

`MarkdownConverter::fromMarkdown()` never registers fonts for you — you must register a font under each resource name `MarkdownConverterConfig` expects (`F1`–`F5` by default) before calling `$builder->build()`.

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

## Supported Markdown

ATX and Setext headings, paragraphs, **bold**, *italic*, ***bold italic***, `inline code`, fenced code blocks, ordered/unordered lists (with nesting and GFM task-list checkboxes), blockquotes, thematic breaks, GFM pipe tables (with column alignment), hyperlinks, standalone images, and footnotes.

See the [full syntax reference and known limitations](https://phppdf.github.io/markdown-converter/docs/supported-markdown/).

## Documentation

Full documentation, including custom layout and custom fonts, is available at [phppdf.github.io/markdown-converter](https://phppdf.github.io/markdown-converter/).

## License

MIT
