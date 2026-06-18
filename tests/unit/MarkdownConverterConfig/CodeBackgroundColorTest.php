<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Color\Color;
use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'getCodeBackgroundColor')]
#[CoversMethod(MarkdownConverterConfig::class, 'setCodeBackgroundColor')]
final class CodeBackgroundColorTest extends TestCase
{
    #[Test]
    public function defaultsToLightGrey(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act
        $color = $config->getCodeBackgroundColor();

        // Assert
        self::assertEquals(Color::fromHex('#f3f3f3'), $color);
    }

    #[Test]
    public function returnsTheColorPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $color = Color::fromHex('#000000');

        // Act
        $config->setCodeBackgroundColor($color);

        // Assert
        self::assertSame($color, $config->getCodeBackgroundColor());
    }
}
