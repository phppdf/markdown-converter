<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'getQuoteIndent')]
#[CoversMethod(MarkdownConverterConfig::class, 'setQuoteIndent')]
final class QuoteIndentTest extends TestCase
{
    #[Test]
    public function defaultsToFourteenPoints(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame(14.0, $config->getQuoteIndent());
    }

    #[Test]
    public function returnsTheValuePassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act
        $config->setQuoteIndent(30.0);

        // Assert
        self::assertSame(30.0, $config->getQuoteIndent());
    }
}
