<?php

declare(strict_types=1);

namespace PhpPdf\Markdown\MarkdownFlow;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;
use PhpPdf\Markdown\MarkdownFlow;

#[BeforeMethods('setUp')]
#[Warmup(1)]
#[Revs(20)]
final class NextChunkBench
{
    private string $markdown;

    public function setUp(): void
    {
        $paragraph = str_repeat('word ', 80);
        $this->markdown = implode("\n\n", array_fill(0, 40, $paragraph));
    }

    public function benchDrainAllChunks(): void
    {
        $flow = MarkdownFlow::fromMarkdown($this->markdown);

        while (!$flow->isEmpty()) {
            $flow->nextChunk(maxWidth: 400, maxHeight: 150);
        }
    }
}
