---
title: "markdown-converter documentation"
---

markdown-converter is a PHP library that converts Markdown into PDF documents. It builds on top of [phppdf/phppdf](https://github.com/phppdf/phppdf): Markdown is parsed directly into layout primitives (text, lists, tables) — there is no intermediate HTML step — and the result is a `PdfDocumentBuilder` that you can further customise before serialising.

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.4+ |
| phppdf/phppdf | dev |

## Installation

```bash
composer require phppdf/markdown-converter
```
