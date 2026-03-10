<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

use WordPress\AiClient\Messages\DTO\Message;

/**
 * Sliding window memory strategy: loads the last N messages.
 *
 * This is the default strategy. It retrieves the most recent messages
 * from the conversation, providing a simple FIFO window of context
 * for the AI model.
 *
 * @since 1.0.0
 */
class SlidingWindowStrategy implements MemoryStrategy
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function loadMessages(
        string $conversationId,
        int $maxMessages,
        int $userId = 0
    ): array {
        return MemoryStore::loadHistory($conversationId, $maxMessages, $userId);
    }
}
