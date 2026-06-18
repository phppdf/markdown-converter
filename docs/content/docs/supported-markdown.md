---
title: "Supported Markdown"
weight: 20
---

`MarkdownConverter::fromMarkdown()` parses Markdown directly into layout
primitives (text, lists, tables) on top of phppdf/phppdf — there is no
intermediate HTML step.

## Syntax

- ATX headings (`# … ######`) and Setext headings (underlined with `===` / `---`)
- Paragraphs, with **bold**, *italic*, ***bold italic***, and `inline code` mixed freely
- Fenced code blocks
- Unordered and ordered lists, with nested sub-lists and GFM task-list checkboxes (`- [ ]` / `- [x]`)
- Blockquotes, including nested blockquotes
- Thematic breaks (`---`)
- GFM pipe tables, including per-column alignment
- Clickable `[text](url)` hyperlinks — work inside paragraphs, headings, list items, and blockquotes; not inside table cells (rendered as visible text only there)
- Standalone `![alt](src)` images, alone on their own paragraph, embedded as a real image scaled to the full content width — `src` must be a local file path, remote URLs are not fetched
- Footnotes (`[^label]` references and `[^label]: definition`), numbered by order of first reference rather than definition order
- Raw HTML blocks — silently discarded; this converter has no HTML renderer

## Footnotes

Footnote definitions can appear anywhere in the source. Where they render is
controlled by `MarkdownConverterConfig::setFootnotePlacement()`:

- `FootnotePlacement::PerPage` (default) — each definition is rendered at the bottom of whichever page first references it
- `FootnotePlacement::DocumentEnd` — every referenced footnote is collected into one ordered list appended at the end of the document, headed by `MarkdownConverterConfig::getFootnotesHeading()`

## Styling

`MarkdownConverterConfig` exposes the following layout and styling options:

- Page size (`setPageWidth()` / `setPageHeight()`) and margins (`setMarginTop()`, `setMarginRight()`, `setMarginBottom()`, `setMarginLeft()`)
- `setBaseFontSize()` and `setLineHeightMultiplier()`
- Per-level heading scale via `setHeadingScale(int $level, float $multiplier)`
- `setCodeBackgroundColor()` — background fill behind fenced code blocks
- `setQuoteRuleColor()` and `setQuoteIndent()` — the vertical rule and text indent for blockquotes
- `setTablePadding()` — cell padding applied on all sides of every table cell

## Known limitations (v1)

- No loose lists — a blank line always ends the whole list, rather than separating multiple paragraphs within a single item.
- An image mixed inline with other text in the same paragraph renders as its alt text only — only a standalone image is embedded.
- Links inside table cells render as their visible text only — not clickable.
- Paragraphs, headings, fenced code blocks, and tables are split across pages when taller than one (tables repeat their header row on the continuation page). Lists, blockquotes, and images are never split — one taller than a full page is still placed anyway and may overflow.
