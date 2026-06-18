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
#[CoversMethod(MarkdownConverterConfig::class, 'getItalicFontName')]
#[CoversMethod(MarkdownConverterConfig::class, 'getItalicFontMetrics')]
#[CoversMethod(MarkdownConverterConfig::class, 'setItalicFont')]
final class ItalicFontTest extends TestCase
{
    #[Test]
    public function defaultsToF3AndHelveticaOblique(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('F3', $config->getItalicFontName());
        self::assertEquals(Type1FontMetrics::helveticaOblique(), $config->getItalicFontMetrics());
    }

    #[Test]
    public function returnsTheNameAndMetricsPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $metrics = Type1FontMetrics::timesItalic();

        // Act
        $config->setItalicFont('Custom3', $metrics);

        // Assert
        self::assertSame('Custom3', $config->getItalicFontName());
        self::assertSame($metrics, $config->getItalicFontMetrics());
    }
}
