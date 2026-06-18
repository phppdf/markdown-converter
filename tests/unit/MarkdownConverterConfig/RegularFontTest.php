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
#[CoversMethod(MarkdownConverterConfig::class, 'getRegularFontName')]
#[CoversMethod(MarkdownConverterConfig::class, 'getRegularFontMetrics')]
#[CoversMethod(MarkdownConverterConfig::class, 'setRegularFont')]
final class RegularFontTest extends TestCase
{
    #[Test]
    public function defaultsToF1AndHelvetica(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('F1', $config->getRegularFontName());
        self::assertEquals(Type1FontMetrics::helvetica(), $config->getRegularFontMetrics());
    }

    #[Test]
    public function returnsTheNameAndMetricsPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $metrics = Type1FontMetrics::timesRoman();

        // Act
        $config->setRegularFont('Custom1', $metrics);

        // Assert
        self::assertSame('Custom1', $config->getRegularFontName());
        self::assertSame($metrics, $config->getRegularFontMetrics());
    }
}
