<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownConverterConfig;

use PhpPdf\Markdown\MarkdownConverterConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownConverterConfig::class)]
#[CoversMethod(MarkdownConverterConfig::class, 'getFootnotesHeading')]
#[CoversMethod(MarkdownConverterConfig::class, 'setFootnotesHeading')]
final class FootnotesHeadingTest extends TestCase
{
    #[Test]
    public function defaultsToFootnotes(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act / Assert
        self::assertSame('Footnotes', $config->getFootnotesHeading());
    }

    #[Test]
    public function returnsTheValuePassedToSetter(): void
    {
        // Arrange
        $config = new MarkdownConverterConfig();

        // Act
        $config->setFootnotesHeading('Notes de bas de page');

        // Assert
        self::assertSame('Notes de bas de page', $config->getFootnotesHeading());
    }
}
