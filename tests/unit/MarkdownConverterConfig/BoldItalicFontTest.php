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
#[CoversMethod(MarkdownConverterConfig::class, 'getBoldItalicFontName')]
#[CoversMethod(MarkdownConverterConfig::class, 'getBoldItalicFontMetrics')]
#[CoversMethod(MarkdownConverterConfig::class, 'setBoldItalicFont')]
final class BoldItalicFontTest extends TestCase
{
    #[Test]
    public function defaultsToF4AndHelveticaBoldOblique(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('F4', $config->getBoldItalicFontName());
        self::assertEquals(Type1FontMetrics::helveticaBoldOblique(), $config->getBoldItalicFontMetrics());
    }

    #[Test]
    public function returnsTheNameAndMetricsPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $metrics = Type1FontMetrics::timesBoldItalic();

        // Act
        $config->setBoldItalicFont('Custom4', $metrics);

        // Assert
        self::assertSame('Custom4', $config->getBoldItalicFontName());
        self::assertSame($metrics, $config->getBoldItalicFontMetrics());
    }
}
