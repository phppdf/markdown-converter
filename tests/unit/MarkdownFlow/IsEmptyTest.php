<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownFlow;

use PhpPdf\Markdown\Internal\MarkdownLayoutEngine;
use PhpPdf\Markdown\Internal\MarkdownParser;
use PhpPdf\Markdown\MarkdownFlow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownFlow::class)]
#[CoversMethod(MarkdownFlow::class, 'isEmpty')]
#[UsesClass(MarkdownParser::class)]
#[UsesClass(MarkdownLayoutEngine::class)]
final class IsEmptyTest extends TestCase
{
    #[Test]
    public function isTrueForBlankMarkdown(): void
    {
        // Arrange / Act
        $flow = MarkdownFlow::fromMarkdown('   ');

        // Assert
        self::assertTrue($flow->isEmpty());
    }

    #[Test]
    public function isFalseUntilEveryBlockHasBeenTaken(): void
    {
        // Arrange
        $flow = MarkdownFlow::fromMarkdown('# Title');

        // Act / Assert
        self::assertFalse($flow->isEmpty());

        $flow->nextChunk(maxWidth: 500, maxHeight: 1000);

        self::assertTrue($flow->isEmpty());
    }
}
