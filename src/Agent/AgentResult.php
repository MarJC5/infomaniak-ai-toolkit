<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

/**
 * Result of an agent loop execution.
 *
 * Contains the final text response, the full execution trace (steps),
 * accumulated token usage across all iterations, and the finish reason.
 *
 * @since 1.0.0
 */
class AgentResult
{
    /**
     * @param string $text            The final text response from the model.
     * @param Step[] $steps           Execution trace (one Step per tool-calling iteration).
     * @param int    $iterationCount  Number of tool-calling iterations performed.
     * @param array  $totalTokenUsage Accumulated token usage across all API calls.
     * @param string $finishReason    The finish reason of the final response.
     */
    public function __construct(
        private string $text,
        private array $steps,
        private int $iterationCount,
        private array $totalTokenUsage,
        private string $finishReason,
    ) {
    }

    /**
     * Returns the final text response.
     *
     * @since 1.0.0
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Returns all execution steps.
     *
     * @since 1.0.0
     *
     * @return Step[]
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Returns the number of tool-calling iterations.
     *
     * @since 1.0.0
     */
    public function getIterationCount(): int
    {
        return $this->iterationCount;
    }

    /**
     * Returns accumulated token usage across all API calls.
     *
     * @since 1.0.0
     *
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}
     */
    public function getTotalTokenUsage(): array
    {
        return $this->totalTokenUsage;
    }

    /**
     * Returns the finish reason of the final response.
     *
     * @since 1.0.0
     */
    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    /**
     * Whether the agent used any tools during execution.
     *
     * @since 1.0.0
     */
    public function hasToolCalls(): bool
    {
        return !empty($this->steps);
    }
}
