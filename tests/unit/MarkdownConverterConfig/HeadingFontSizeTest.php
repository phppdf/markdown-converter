<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use InvalidArgumentException;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'headingFontSize')]
#[CoversMethod(MarkdownConverterConfig::class, 'setHeadingScale')]
final class HeadingFontSizeTest extends TestCase
{
    #[Test]
    public function scalesBaseFontSizeByDefaultLevelMultiplier(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setBaseFontSize(10.0);

        // Act
        $size = $config->headingFontSize(1);

        // Assert
        self::assertSame(20.0, $size);
    }

    #[Test]
    public function usesCustomScaleAfterSetHeadingScale(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setBaseFontSize(10.0)->setHeadingScale(3, 2.5);

        // Act
        $size = $config->headingFontSize(3);

        // Assert
        self::assertSame(25.0, $size);
    }

    #[Test]
    public function throwsForLevelBelowOne(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);
        $config->setHeadingScale(0, 1.0);
    }

    #[Test]
    public function throwsForLevelAboveSix(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        $this->expectException(InvalidArgumentException::class);
        $config->setHeadingScale(7, 1.0);
    }
}
