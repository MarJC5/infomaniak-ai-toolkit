<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

use WordPress\AiClient\Builders\MessageBuilder;
use WordPress\AiClient\Messages\DTO\Message;

/**
 * Stores and retrieves conversation messages.
 *
 * Central class for the memory system. Provides:
 * - storeMessage(): persists a user or model message
 * - loadHistory(): retrieves Message[] for injection into withHistory()
 * - generateId(): creates unique UUIDs for messages and conversations
 *
 * @since 1.0.0
 */
class MemoryStore
{
    /**
     * Estimates the token count for a piece of text.
     *
     * Uses a ~4 characters per token heuristic. This is a fallback
     * for when real token counts from the SDK are unavailable.
     *
     * @since 1.0.0
     *
     * @param string $text The text to estimate.
     * @return int Estimated token count.
     */
    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text, 'UTF-8') / 4);
    }

    /**
     * Stores a single message in the conversation history.
     *
     * If no token_count is provided in the context, an estimate based on
     * the content length (~4 chars/token) is used as a fallback.
     *
     * @since 1.0.0
     *
     * @param string $conversationId The conversation identifier.
     * @param string $role           'user' or 'model'.
     * @param string $content        The message text.
     * @param array  $context        Additional context: user_id, preset_name, token_count, metadata.
     * @return MemoryRecord|null The stored record, or null on failure.
     */
    public static function storeMessage(
        string $conversationId,
        string $role,
        string $content,
        array $context = []
    ): ?MemoryRecord {
        $tokenCount = (int) ($context['token_count'] ?? 0);
        if ($tokenCount <= 0) {
            $tokenCount = self::estimateTokens($content);
        }

        $data = [
            'id'              => self::generateId(),
            'conversation_id' => $conversationId,
            'user_id'         => (int) ($context['user_id'] ?? get_current_user_id()),
            'preset_name'     => (string) ($context['preset_name'] ?? ''),
            'role'            => $role,
            'content'         => $content,
            'token_count'     => $tokenCount,
            'metadata'        => isset($context['metadata'])
                ? wp_json_encode($context['metadata'])
                : null,
            'created_at'      => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        ];

        /**
         * Filters message data before storing in conversation memory.
         *
         * Return false to cancel storage.
         *
         * @since 1.0.0
         *
         * @param array  $data           The message data to store.
         * @param string $conversationId The conversation ID.
         * @param string $role           The message role ('user' or 'model').
         * @param string $content        The raw message content.
         */
        $data = apply_filters('infomaniak_ai_memory_before_store', $data, $conversationId, $role, $content);

        if ($data === false || !is_array($data)) {
            return null;
        }

        global $wpdb;

        $wpdb->insert(
            MemorySchema::tableName(),
            $data,
            [
                '%s', // id
                '%s', // conversation_id
                '%d', // user_id
                '%s', // preset_name
                '%s', // role
                '%s', // content
                '%d', // token_count
                '%s', // metadata
                '%s', // created_at
            ]
        );

        if (!$wpdb->rows_affected) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[MemoryStore] Insert failed — table: ' . MemorySchema::tableName() . ' | wpdb error: ' . $wpdb->last_error . ' | last query: ' . $wpdb->last_query);
            return null;
        }

        $record = MemoryRecord::fromArray($data);

        /**
         * Fires after a message has been stored in conversation memory.
         *
         * Use this to implement quotas, alerts, or real-time updates.
         *
         * @since 1.0.0
         *
         * @param MemoryRecord $record         The stored memory record.
         * @param string       $conversationId The conversation ID.
         */
        do_action('infomaniak_ai_memory_stored', $record, $conversationId);

        return $record;
    }

    /**
     * Loads conversation history as SDK Message objects for withHistory().
     *
     * Retrieves the last $limit messages for the conversation and converts
     * them to Message DTOs compatible with the WordPress AI Client SDK.
     * Summary records (role='summary') are excluded since they are only
     * used by the CompactingStrategy.
     *
     * @since 1.0.0
     *
     * @param string $conversationId The conversation identifier.
     * @param int    $limit          Maximum number of messages to load.
     * @param int    $userId         Optional user ID filter (0 = no filter).
     * @return Message[] Array of Message DTOs, oldest first (chronological order).
     */
    public static function loadHistory(
        string $conversationId,
        int $limit = 20,
        int $userId = 0
    ): array {
        $query = MemoryQuery::new()
            ->forConversation($conversationId)
            ->excludeRole('summary')
            ->recent($limit);

        if ($userId > 0) {
            $query->forUser($userId);
        }

        $records = $query->get();

        // Records come back newest-first from recent(), reverse for chronological order.
        $records = array_reverse($records);

        $messages = [];
        foreach ($records as $record) {
            $builder = new MessageBuilder();

            if ($record->isUser()) {
                $builder->usingUserRole();
            } else {
                $builder->usingModelRole();
            }

            $builder->withText($record->content());

            try {
                $messages[] = $builder->get();
            } catch (\Throwable $e) {
                continue;
            }
        }

        /**
         * Filters the loaded history Message objects before injection into the prompt.
         *
         * Use this to modify, truncate, or enrich the conversation history
         * before it is sent to the AI model.
         *
         * @since 1.0.0
         *
         * @param Message[] $messages       The loaded Message DTOs.
         * @param string    $conversationId The conversation ID.
         * @param int       $limit          The requested message limit.
         */
        return apply_filters(
            'infomaniak_ai_memory_history_loaded',
            $messages,
            $conversationId,
            $limit
        );
    }

    /**
     * Generates a unique UUID.
     *
     * Used for both message IDs and conversation IDs.
     *
     * @since 1.0.0
     *
     * @return string A UUID v4 string (36 characters).
     */
    public static function generateId(): string
    {
        return wp_generate_uuid4();
    }

    /**
     * Creates a new MemoryQuery instance.
     *
     * @since 1.0.0
     *
     * @return MemoryQuery
     */
    public static function query(): MemoryQuery
    {
        return MemoryQuery::new();
    }

    /**
     * Deletes all messages for a conversation.
     *
     * @since 1.0.0
     *
     * @param string $conversationId The conversation to clear.
     * @return int Number of deleted records.
     */
    public static function clearConversation(string $conversationId): int
    {
        return MemoryQuery::new()
            ->forConversation($conversationId)
            ->delete();
    }
}
