<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownChunk;

use PhpPdf\Markdown\MarkdownChunk;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownChunk::class)]
#[CoversMethod(MarkdownChunk::class, 'isEmpty')]
final class IsEmptyTest extends TestCase
{
    #[Test]
    public function isTrueWithNoRenderOps(): void
    {
        // Arrange
        $chunk = new MarkdownChunk([], 0.0);

        // Act / Assert
        self::assertTrue($chunk->isEmpty());
    }

    #[Test]
    public function isFalseWithAtLeastOneRenderOp(): void
    {
        // Arrange
        $chunk = new MarkdownChunk([static function (): void {
        }], 10.0);

        // Act / Assert
        self::assertFalse($chunk->isEmpty());
    }
}
