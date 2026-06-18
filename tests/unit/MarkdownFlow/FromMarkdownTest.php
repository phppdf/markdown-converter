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
#[CoversMethod(MarkdownFlow::class, 'fromMarkdown')]
#[UsesClass(MarkdownParser::class)]
#[UsesClass(MarkdownLayoutEngine::class)]
final class FromMarkdownTest extends TestCase
{
    #[Test]
    public function returnsAFlowInstance(): void
    {
        // Arrange / Act
        $flow = MarkdownFlow::fromMarkdown('# Title');

        // Assert
        self::assertInstanceOf(MarkdownFlow::class, $flow);
    }

    #[Test]
    public function isNotEmptyForNonBlankMarkdown(): void
    {
        // Arrange / Act
        $flow = MarkdownFlow::fromMarkdown('Hello world');

        // Assert
        self::assertFalse($flow->isEmpty());
    }
}
