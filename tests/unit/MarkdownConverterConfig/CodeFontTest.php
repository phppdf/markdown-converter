<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Font\Type1FontMetrics;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'getCodeFontName')]
#[CoversMethod(MarkdownConverterConfig::class, 'getCodeFontMetrics')]
#[CoversMethod(MarkdownConverterConfig::class, 'setCodeFont')]
final class CodeFontTest extends TestCase
{
    #[Test]
    public function defaultsToF5AndCourier(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('F5', $config->getCodeFontName());
        self::assertEquals(Type1FontMetrics::courier(), $config->getCodeFontMetrics());
    }

    #[Test]
    public function returnsTheNameAndMetricsPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $metrics = Type1FontMetrics::courierBold();

        // Act
        $config->setCodeFont('Custom5', $metrics);

        // Assert
        self::assertSame('Custom5', $config->getCodeFontName());
        self::assertSame($metrics, $config->getCodeFontMetrics());
    }
}
