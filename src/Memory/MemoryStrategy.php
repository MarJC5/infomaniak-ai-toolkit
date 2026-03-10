<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

use WordPress\AiClient\Messages\DTO\Message;

/**
 * Interface for memory retrieval strategies.
 *
 * Implementations determine how conversation history is selected and
 * prepared for injection into the AI prompt. The default implementation
 * is SlidingWindowStrategy (last N messages). Future implementations
 * could include summarization, embedding-based retrieval, etc.
 *
 * @since 1.0.0
 */
interface MemoryStrategy
{
    /**
     * Loads conversation history as Message objects.
     *
     * @since 1.0.0
     *
     * @param string $conversationId The conversation identifier.
     * @param int    $maxMessages    The maximum number of messages to include.
     * @param int    $userId         User ID for scoping (0 = no filter).
     * @return Message[] Chronologically ordered Message DTOs.
     */
    public function loadMessages(
        string $conversationId,
        int $maxMessages,
        int $userId = 0
    ): array;
}
