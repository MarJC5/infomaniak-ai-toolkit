<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Streaming;

/**
 * Value object returned after a streaming generation completes.
 *
 * @since 1.0.0
 */
class StreamingResult
{
    private string $fullText;
    private array $tokenUsage;
    private string $finishReason;
    private array $toolCalls;

    public function __construct(
        string $fullText,
        array $tokenUsage,
        string $finishReason = 'stop',
        array $toolCalls = []
    ) {
        $this->fullText = $fullText;
        $this->tokenUsage = $tokenUsage;
        $this->finishReason = $finishReason;
        $this->toolCalls = $toolCalls;
    }

    public function getFullText(): string
    {
        return $this->fullText;
    }

    public function getTokenUsage(): array
    {
        return $this->tokenUsage;
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }
}
