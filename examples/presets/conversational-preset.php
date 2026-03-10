<?php

/**
 * Example: Conversational AI Preset
 *
 * A preset that supports multi-turn conversation with automatic
 * history management. The memory system stores messages in the database
 * and injects them into subsequent turns via the SDK's withHistory().
 *
 * Required file structure in your plugin:
 *
 *   your-plugin/
 *   ├── your-plugin.php
 *   ├── src/
 *   │   └── Presets/
 *   │       └── ChatPreset.php          <-- this class
 *   └── templates/
 *       └── presets/
 *           ├── chat.php                <-- prompt template
 *           └── system/
 *               └── chat-assistant.php  <-- system prompt (optional)
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * A conversational preset that maintains chat history across turns.
 */
class ChatPreset extends BasePreset
{
    public function name(): string
    {
        return 'chat';
    }

    public function label(): string
    {
        return __('Chat Assistant', 'your-plugin');
    }

    public function description(): string
    {
        return __('A conversational assistant that remembers previous messages.', 'your-plugin');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge(
                [
                    'message' => [
                        'type' => 'string',
                        'description' => 'The user message.',
                    ],
                ],
                $this->conversationalInputProperties()
            ),
            'required' => ['message'],
        ];
    }

    // Points to templates/presets/chat.php in your plugin.
    protected function templateName(): string
    {
        return 'chat';
    }

    // Points to templates/presets/system/chat-assistant.php in your plugin.
    protected function systemTemplateName(): ?string
    {
        return 'chat-assistant';
    }

    // Enable multi-turn conversation.
    protected function conversational(): bool
    {
        return true;
    }

    // Keep the last 30 messages in context.
    protected function historySize(): int
    {
        return 30;
    }

    protected function maxTokens(): int
    {
        return 2000;
    }

    protected function temperature(): float
    {
        return 0.8;
    }

    // Override annotations: chat is not idempotent.
    protected function annotations(): array
    {
        return [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => false,
        ];
    }
}

/*
 * --------------------------------------------------------------------------
 * Prompt template: templates/presets/chat.php
 * --------------------------------------------------------------------------
 *
 * <?= $message ?>
 *
 * --------------------------------------------------------------------------
 * System prompt: templates/presets/system/chat-assistant.php
 * --------------------------------------------------------------------------
 *
 * You are a helpful WordPress assistant. Answer questions clearly and
 * concisely. When the user refers to something from earlier in the
 * conversation, use the provided history to maintain context.
 *
 * --------------------------------------------------------------------------
 * Registration in your-plugin.php:
 * --------------------------------------------------------------------------
 *
 * add_action('wp_abilities_api_init', function() {
 *     if (!class_exists(\WordPress\InfomaniakAiToolkit\Presets\BasePreset::class)) {
 *         return;
 *     }
 *     $preset = new \YourPlugin\Presets\ChatPreset();
 *     $preset->registerAsAbility();
 * });
 *
 * --------------------------------------------------------------------------
 * Usage in PHP:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\ChatPreset();
 *
 * // First turn (conversation_id is auto-generated):
 * $result = $preset->execute(['message' => 'Hello! What is WordPress?']);
 * // => ['conversation_id' => 'uuid-here', 'result' => 'WordPress is...']
 *
 * // Second turn (pass the conversation_id back):
 * $result = $preset->execute([
 *     'message' => 'How do I create a custom post type?',
 *     'conversation_id' => $result['conversation_id'],
 * ]);
 * // The AI now has context from the first turn.
 *
 * // Clear conversation when done:
 * \WordPress\InfomaniakAiToolkit\Memory\MemoryStore::clearConversation(
 *     $result['conversation_id']
 * );
 *
 * --------------------------------------------------------------------------
 * Usage via REST API:
 * --------------------------------------------------------------------------
 *
 * # First turn:
 * POST /wp-json/wp-abilities/v1/abilities/infomaniak/chat/run
 * Content-Type: application/json
 *
 * { "message": "Hello! What is WordPress?" }
 *
 * # Response: { "conversation_id": "uuid-here", "result": "WordPress is..." }
 *
 * # Second turn (include conversation_id):
 * POST /wp-json/wp-abilities/v1/abilities/infomaniak/chat/run
 * Content-Type: application/json
 *
 * { "message": "How do I create a custom post type?", "conversation_id": "uuid-here" }
 *
 * --------------------------------------------------------------------------
 * Querying conversation history:
 * --------------------------------------------------------------------------
 *
 * use WordPress\InfomaniakAiToolkit\Memory\MemoryStore;
 *
 * // Get all messages for a conversation:
 * $records = MemoryStore::query()
 *     ->forConversation($conversationId)
 *     ->oldestFirst()
 *     ->get();
 *
 * foreach ($records as $record) {
 *     printf("[%s] %s\n", $record->role(), $record->content());
 * }
 *
 * // Count messages:
 * $count = MemoryStore::query()
 *     ->forConversation($conversationId)
 *     ->count();
 *
 * --------------------------------------------------------------------------
 * Memory compaction (recommended for long conversations):
 * --------------------------------------------------------------------------
 *
 * The CompactingStrategy automatically summarizes old messages when token
 * usage exceeds a threshold. Compaction runs in the background via the
 * shutdown hook — zero latency for the user.
 *
 * use WordPress\InfomaniakAiToolkit\Memory\CompactingStrategy;
 * use WordPress\InfomaniakAiToolkit\Memory\MemoryStrategy;
 *
 * class ChatWithCompaction extends BasePreset
 * {
 *     // ...same as ChatPreset above...
 *
 *     protected function memoryStrategy(): MemoryStrategy
 *     {
 *         return new CompactingStrategy(
 *             tokenBudget: 16000,         // Max token budget for history.
 *             compactionThreshold: 0.8,   // Compact at 80% of budget.
 *             recentKeepCount: 6,         // Keep 6 recent messages intact.
 *             summaryMaxTokens: 300,      // Max tokens for the summary.
 *         );
 *     }
 * }
 *
 * How it works:
 * - After each turn, checks token usage via a single SQL SUM query.
 * - If over 80% of budget, schedules compaction via the `shutdown` hook.
 * - Compaction runs after the HTTP response is sent (zero user latency).
 * - Old messages are summarized into a single 'summary' record.
 * - Next turn loads: [summary] + [recent messages] — fast, no AI call.
 * - Hooks: 'infomaniak_ai_memory_before_compact' (filter),
 *          'infomaniak_ai_memory_compacted' (action).
 *
 * --------------------------------------------------------------------------
 * Custom memory strategy:
 * --------------------------------------------------------------------------
 *
 * You can implement a custom MemoryStrategy to change how history is loaded:
 *
 * use WordPress\InfomaniakAiToolkit\Memory\MemoryStrategy;
 *
 * class MyCustomStrategy implements MemoryStrategy
 * {
 *     public function loadMessages(
 *         string $conversationId,
 *         int $maxMessages,
 *         int $userId = 0
 *     ): array {
 *         // Your custom logic here...
 *     }
 * }
 *
 * // Then override in your preset:
 * protected function memoryStrategy(): MemoryStrategy
 * {
 *     return new MyCustomStrategy();
 * }
 */
