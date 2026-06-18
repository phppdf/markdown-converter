<?php

declare(strict_types=1);

namespace PhpPdf\Markdown;

/**
 * Where footnote definitions are rendered, configured via
 * MarkdownConverterConfig::setFootnotePlacement().
 */
enum FootnotePlacement
{
    /**
     * Reserve space at the bottom of whichever page first references each
     * footnote, and render its definition there — the conventional
     * placement for printed documents.
     *
     * Known limitation: when a paragraph containing a footnote reference is
     * itself split across pages (see MarkdownFlow::nextChunk()), the
     * definition is reserved on the *first* page the paragraph appears on,
     * even on the rare occasion the reference itself falls in the
     * continuation on the next page.
     */
    case PerPage;

    /**
     * Collect every referenced footnote into one ordered list, appended as
     * its own section (headed by MarkdownConverterConfig::getFootnotesHeading())
     * at the very end of the document — simpler, but the reader has to flip
     * to the back to read a definition.
     */
    case DocumentEnd;
}
