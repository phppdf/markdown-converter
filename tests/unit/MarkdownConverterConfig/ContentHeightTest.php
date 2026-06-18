<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'contentHeight')]
final class ContentHeightTest extends TestCase
{
    #[Test]
    public function subtractsTopAndBottomMarginsFromPageHeight(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setPageHeight(800)->setMarginTop(40)->setMarginBottom(20);

        // Act
        $height = $config->contentHeight();

        // Assert
        self::assertSame(740.0, $height);
    }
}
