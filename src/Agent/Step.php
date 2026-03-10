<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Represents a single iteration of the agent loop.
 *
 * Captures which tools were called and what results were returned
 * for debugging, logging, and introspection.
 *
 * @since 1.0.0
 */
class Step
{
    /**
     * @param int                $iteration    The iteration number (1-based).
     * @param FunctionCall[]     $toolCalls    Tool calls made by the model.
     * @param FunctionResponse[] $toolResults  Results returned to the model.
     */
    public function __construct(
        private int $iteration,
        private array $toolCalls,
        private array $toolResults,
    ) {
    }

    /**
     * Returns the iteration number.
     *
     * @since 1.0.0
     */
    public function getIteration(): int
    {
        return $this->iteration;
    }

    /**
     * Returns the tool calls made by the model in this step.
     *
     * @since 1.0.0
     *
     * @return FunctionCall[]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    /**
     * Returns the tool results sent back to the model.
     *
     * @since 1.0.0
     *
     * @return FunctionResponse[]
     */
    public function getToolResults(): array
    {
        return $this->toolResults;
    }
}
