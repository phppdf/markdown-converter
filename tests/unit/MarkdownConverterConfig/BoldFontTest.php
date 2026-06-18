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
#[CoversMethod(MarkdownConverterConfig::class, 'getBoldFontName')]
#[CoversMethod(MarkdownConverterConfig::class, 'getBoldFontMetrics')]
#[CoversMethod(MarkdownConverterConfig::class, 'setBoldFont')]
final class BoldFontTest extends TestCase
{
    #[Test]
    public function defaultsToF2AndHelveticaBold(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('F2', $config->getBoldFontName());
        self::assertEquals(Type1FontMetrics::helveticaBold(), $config->getBoldFontMetrics());
    }

    #[Test]
    public function returnsTheNameAndMetricsPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $metrics = Type1FontMetrics::timesBold();

        // Act
        $config->setBoldFont('Custom2', $metrics);

        // Assert
        self::assertSame('Custom2', $config->getBoldFontName());
        self::assertSame($metrics, $config->getBoldFontMetrics());
    }
}
