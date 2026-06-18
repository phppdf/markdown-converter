<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownChunk;

use PhpPdf\Builder\PdfPageBuilder;
use PhpPdf\Content\PdfContentStreamBuilder;
use PhpPdf\Markdown\MarkdownChunk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownChunk::class)]
#[CoversMethod(MarkdownChunk::class, 'draw')]
final class DrawTest extends TestCase
{
    #[Test]
    public function invokesEveryRenderOpWithTheGivenStreamPageAndOrigin(): void
    {
        // Arrange
        $calls = [];
        $chunk = new MarkdownChunk([
            static function (
                PdfContentStreamBuilder $stream,
                PdfPageBuilder $page,
                float $x,
                float $y,
            ) use (&$calls): void {
                $calls[] = [$x, $y];
            },
            static function (
                PdfContentStreamBuilder $stream,
                PdfPageBuilder $page,
                float $x,
                float $y,
            ) use (&$calls): void {
                $calls[] = [$x, $y];
            },
        ], 20.0);
        $stream = new PdfContentStreamBuilder();
        $page = new PdfPageBuilder();

        // Act
        $chunk->draw($stream, $page, 72.0, 700.0);

        // Assert
        self::assertSame([[72.0, 700.0], [72.0, 700.0]], $calls);
    }

    #[Test]
    public function doesNothingWhenEmpty(): void
    {
        // Arrange
        $chunk = new MarkdownChunk([], 0.0);
        $stream = new PdfContentStreamBuilder();
        $page = new PdfPageBuilder();

        // Act
        $chunk->draw($stream, $page, 0.0, 0.0);

        // Assert
        self::assertSame([], $stream->build()->getOperations());
    }
}
