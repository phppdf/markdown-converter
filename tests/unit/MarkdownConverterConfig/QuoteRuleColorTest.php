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
#[CoversMethod(MarkdownConverterConfig::class, 'getQuoteRuleColor')]
#[CoversMethod(MarkdownConverterConfig::class, 'setQuoteRuleColor')]
final class QuoteRuleColorTest extends TestCase
{
    #[Test]
    public function defaultsToMidGrey(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act
        $color = $config->getQuoteRuleColor();

        // Assert
        self::assertEquals(Color::fromHex('#bbbbbb'), $color);
    }

    #[Test]
    public function returnsTheColorPassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $color = Color::fromHex('#ff0000');

        // Act
        $config->setQuoteRuleColor($color);

        // Assert
        self::assertSame($color, $config->getQuoteRuleColor());
    }
}
