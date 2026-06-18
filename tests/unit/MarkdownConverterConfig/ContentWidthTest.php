<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'contentWidth')]
final class ContentWidthTest extends TestCase
{
    #[Test]
    public function subtractsLeftAndRightMarginsFromPageWidth(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();
        $config->setPageWidth(600)->setMarginLeft(50)->setMarginRight(30);

        // Act
        $width = $config->contentWidth();

        // Assert
        self::assertSame(520.0, $width);
    }
}
