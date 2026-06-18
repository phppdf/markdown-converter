<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownFlow;

use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Content\PdfContentStreamBuilder;
use PhpPdf\Markdown\Internal\MarkdownLayoutEngine;
use PhpPdf\Markdown\Internal\MarkdownParser;
use PhpPdf\Markdown\MarkdownChunk;
use PhpPdf\Markdown\MarkdownFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownFlow::class)]
#[CoversMethod(MarkdownFlow::class, 'nextChunk')]
#[UsesClass(MarkdownParser::class)]
#[UsesClass(MarkdownLayoutEngine::class)]
#[UsesClass(MarkdownChunk::class)]
final class NextChunkTest extends TestCase
{
    #[Test]
    public function returnsAChunkWithPositiveHeightWhenContentFits(): void
    {
        // Arrange
        $flow = MarkdownFlow::fromMarkdown('# Title' . "\n\n" . 'A short paragraph.');

        // Act
        $chunk = $flow->nextChunk(maxWidth: 400, maxHeight: 1000);

        // Assert
        self::assertGreaterThan(0.0, $chunk->getHeight());
        self::assertTrue($flow->isEmpty());
    }

    #[Test]
    public function drainsBlocksAcrossSuccessiveCallsUntilEmpty(): void
    {
        // Arrange: enough paragraphs that no single region fits them all.
        $paragraph = str_repeat('word ', 80);
        $flow = MarkdownFlow::fromMarkdown(implode("\n\n", array_fill(0, 10, $paragraph)));

        // Act
        $chunks = [];

        while (!$flow->isEmpty()) {
            $chunks[] = $flow->nextChunk(maxWidth: 400, maxHeight: 150);
        }

        // Assert
        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertFalse($chunk->isEmpty());
        }
    }

    #[Test]
    public function returnsEmptyChunkWhenFlowIsAlreadyEmpty(): void
    {
        // Arrange
        $flow = MarkdownFlow::fromMarkdown('');

        // Act
        $chunk = $flow->nextChunk(maxWidth: 400, maxHeight: 1000);

        // Assert
        self::assertTrue($chunk->isEmpty());
        self::assertSame(0.0, $chunk->getHeight());
    }

    #[Test]
    public function splitsAParagraphTallerThanMaxHeightAcrossChunks(): void
    {
        // Arrange: one paragraph with far more lines than a single small
        // region could ever fit, so it must be split (rather than just
        // overflowing the first chunk and being placed whole).
        $paragraph = str_repeat('word ', 200);
        $flow = MarkdownFlow::fromMarkdown($paragraph);

        // Act
        $chunks = [];

        while (!$flow->isEmpty()) {
            $chunk = $flow->nextChunk(maxWidth: 200, maxHeight: 50);
            self::assertLessThanOrEqual(50.0, $chunk->getHeight());
            $chunks[] = $chunk;
        }

        // Assert: it took more than one chunk, and each one actually drew something.
        self::assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            self::assertFalse($chunk->isEmpty());
        }
    }

    #[Test]
    public function chunkCanBeDrawnIntoAStreamSharedWithOtherContent(): void
    {
        // Arrange: simulates a caller mixing hand-drawn content with a
        // markdown chunk on the same page/stream, which is the scenario this
        // low-level API exists for.
        $flow = MarkdownFlow::fromMarkdown('# Heading' . "\n\n" . 'Body text.');
        $chunk = $flow->nextChunk(maxWidth: 400, maxHeight: 1000);
        $stream = new PdfContentStreamBuilder();
        $stream->rectangle(0, 0, 10, 10)->fill();
        $page = new PdfPageBuilder();

        // Act
        $chunk->draw($stream, $page, x: 72, y: 700);
        $stream->rectangle(0, 0, 20, 20)->fill();

        // Assert
        $operations = $stream->build()->getOperations();
        self::assertGreaterThan(2, count($operations));
    }
}
