<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownChunk;

use PhpPdf\Markdown\MarkdownChunk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownChunk::class)]
#[CoversMethod(MarkdownChunk::class, 'getHeight')]
final class GetHeightTest extends TestCase
{
    #[Test]
    public function returnsTheHeightPassedToTheConstructor(): void
    {
        // Arrange
        $chunk = new MarkdownChunk([], 42.5);

        // Act
        $height = $chunk->getHeight();

        // Assert
        self::assertSame(42.5, $height);
    }
}
