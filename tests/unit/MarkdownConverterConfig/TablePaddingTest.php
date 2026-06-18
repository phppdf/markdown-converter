<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'getTablePadding')]
#[CoversMethod(MarkdownConverterConfig::class, 'setTablePadding')]
final class TablePaddingTest extends TestCase
{
    #[Test]
    public function defaultsToSixPoints(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame(6.0, $config->getTablePadding());
    }

    #[Test]
    public function returnsTheValuePassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act
        $config->setTablePadding(12.0);

        // Assert
        self::assertSame(12.0, $config->getTablePadding());
    }
}
