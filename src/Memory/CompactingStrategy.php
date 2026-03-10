<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Builders\MessageBuilder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\InfomaniakAiToolkit\Usage\UsageTracker;

/**
 * Memory strategy that summarizes old messages when the token budget is exceeded.
 *
 * Compaction runs asynchronously via the `shutdown` hook (after the HTTP response
 * is sent to the client), so users experience zero added latency.
 *
 * How it works:
 * 1. `loadMessages()` — never calls AI. Returns [summary] + [recent] if a summary
 *    exists, or falls back to a sliding window if no summary is available yet.
 * 2. `maybeScheduleCompaction()` — called by BasePreset after each turn. Uses a
 *    single SQL SUM query to check token usage. If over threshold, registers a
 *    shutdown hook to run `runCompaction()`.
 * 3. `runCompaction()` — runs in the shutdown hook. Calls AI to summarize old
 *    messages, stores the summary, and deletes compacted records.
 *
 * @since 1.0.0
 */
class CompactingStrategy implements MemoryStrategy
{
    /** @var int */
    private const DEFAULT_TOKEN_BUDGET = 16000;

    /** @var float */
    private const DEFAULT_COMPACTION_THRESHOLD = 0.8;

    /** @var int */
    private const DEFAULT_RECENT_KEEP = 6;

    /** @var int */
    private const DEFAULT_SUMMARY_MAX_TOKENS = 300;

    /** @var int */
    private int $tokenBudget;

    /** @var float */
    private float $compactionThreshold;

    /** @var int */
    private int $recentKeepCount;

    /** @var int */
    private int $summaryMaxTokens;

    /** @var string|null */
    private ?string $summaryModel;

    /**
     * @since 1.0.0
     *
     * @param int         $tokenBudget         Max token budget for history.
     * @param float       $compactionThreshold Schedule compaction at this % of budget.
     * @param int         $recentKeepCount     Recent messages to keep uncompacted.
     * @param int         $summaryMaxTokens    Max tokens for the summary output.
     * @param string|null $summaryModel        Model for summarization (null = default).
     */
    public function __construct(
        int $tokenBudget = self::DEFAULT_TOKEN_BUDGET,
        float $compactionThreshold = self::DEFAULT_COMPACTION_THRESHOLD,
        int $recentKeepCount = self::DEFAULT_RECENT_KEEP,
        int $summaryMaxTokens = self::DEFAULT_SUMMARY_MAX_TOKENS,
        ?string $summaryModel = null
    ) {
        $this->tokenBudget = $tokenBudget;
        $this->compactionThreshold = $compactionThreshold;
        $this->recentKeepCount = $recentKeepCount;
        $this->summaryMaxTokens = $summaryMaxTokens;
        $this->summaryModel = $summaryModel;
    }

    /**
     * {@inheritDoc}
     *
     * Never calls AI — always fast. Uses a pre-computed summary if available,
     * or falls back to a sliding window truncation.
     *
     * @since 1.0.0
     */
    public function loadMessages(
        string $conversationId,
        int $maxMessages,
        int $userId = 0
    ): array {
        // Load all non-summary records (newest first).
        $query = MemoryQuery::new()
            ->forConversation($conversationId)
            ->excludeRole('summary')
            ->recent($maxMessages);

        if ($userId > 0) {
            $query->forUser($userId);
        }

        $records = $query->get();
        $records = array_reverse($records); // Chronological order.

        // Check for an existing summary.
        $summaryRecords = MemoryQuery::new()
            ->forConversation($conversationId)
            ->forRole('summary')
            ->recent(1)
            ->get();

        $summary = $summaryRecords[0] ?? null;

        $messages = [];

        // If a summary exists, prepend it as a user message with context.
        if ($summary !== null) {
            $builder = new MessageBuilder();
            $builder->usingUserRole();
            $builder->withText('[Previous conversation summary] ' . $summary->content());

            try {
                $messages[] = $builder->get();
            } catch (\Throwable $e) {
                // Skip summary if it fails to build.
            }
        }

        // Convert records to Message DTOs.
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

        /** This filter is documented in MemoryStore::loadHistory() */
        return apply_filters(
            'infomaniak_ai_memory_history_loaded',
            $messages,
            $conversationId,
            $maxMessages
        );
    }

    /**
     * Checks if compaction should be scheduled and registers a shutdown hook.
     *
     * Uses a single SQL SUM query to check token usage — no records loaded into PHP.
     * The shutdown hook fires after the HTTP response is sent to the client,
     * so the user experiences zero latency.
     *
     * @since 1.0.0
     *
     * @param string $conversationId The conversation to check.
     * @param int    $userId         The user who owns the conversation.
     */
    public function maybeScheduleCompaction(string $conversationId, int $userId): void
    {
        $totalTokens = MemoryQuery::new()
            ->forConversation($conversationId)
            ->excludeRole('summary')
            ->sumTokens();

        if ($totalTokens < (int) ($this->tokenBudget * $this->compactionThreshold)) {
            return;
        }

        // Prevent double-registration in the same request.
        static $scheduled = [];
        $key = $conversationId . ':' . $userId;
        if (isset($scheduled[$key])) {
            return;
        }
        $scheduled[$key] = true;

        $recentKeep = $this->recentKeepCount;
        $summaryMaxTokens = $this->summaryMaxTokens;
        $summaryModel = $this->summaryModel;

        add_action('shutdown', static function () use (
            $conversationId,
            $userId,
            $recentKeep,
            $summaryMaxTokens,
            $summaryModel
        ) {
            self::runCompaction(
                $conversationId,
                $userId,
                $recentKeep,
                $summaryMaxTokens,
                $summaryModel
            );
        });
    }

    /**
     * Runs the compaction process in the background.
     *
     * Called from the shutdown hook — the HTTP response has already been sent.
     *
     * @since 1.0.0
     *
     * @param string      $conversationId  The conversation to compact.
     * @param int         $userId          The user who owns the conversation.
     * @param int         $recentKeep      Number of recent messages to keep.
     * @param int         $summaryMaxTokens Max tokens for the summary.
     * @param string|null $summaryModel    Model to use for summarization.
     */
    public static function runCompaction(
        string $conversationId,
        int $userId,
        int $recentKeep = self::DEFAULT_RECENT_KEEP,
        int $summaryMaxTokens = self::DEFAULT_SUMMARY_MAX_TOKENS,
        ?string $summaryModel = null
    ): void {
        // Load all non-summary records in chronological order.
        $records = MemoryQuery::new()
            ->forConversation($conversationId)
            ->excludeRole('summary')
            ->oldestFirst()
            ->get();

        if (count($records) <= $recentKeep) {
            return;
        }

        // Find existing summary (if any).
        $existingSummaries = MemoryQuery::new()
            ->forConversation($conversationId)
            ->forRole('summary')
            ->recent(1)
            ->get();

        $existingSummary = $existingSummaries[0] ?? null;

        // Split: old messages to compact, recent to keep.
        $toCompact = array_slice($records, 0, -$recentKeep);

        if (empty($toCompact)) {
            return;
        }

        /**
         * Filters the records before compaction.
         *
         * Return false to cancel compaction for this conversation.
         *
         * @since 1.0.0
         *
         * @param MemoryRecord[] $toCompact      Records that will be summarized.
         * @param string         $conversationId The conversation ID.
         * @param MemoryRecord|null $existingSummary The existing summary, if any.
         */
        $toCompact = apply_filters(
            'infomaniak_ai_memory_before_compact',
            $toCompact,
            $conversationId,
            $existingSummary
        );

        if ($toCompact === false || !is_array($toCompact) || empty($toCompact)) {
            return;
        }

        // Summarize via AI call (background, no user waiting).
        $summaryText = self::summarize($toCompact, $existingSummary, $summaryMaxTokens, $summaryModel);

        if (empty($summaryText)) {
            return;
        }

        $lastCompacted = end($toCompact);

        // Store the new summary record.
        $summaryRecord = MemoryStore::storeMessage($conversationId, 'summary', $summaryText, [
            'user_id' => $userId,
            'preset_name' => 'memory_compaction',
            'metadata' => [
                'compacted_count' => count($toCompact),
                'compacted_from' => $toCompact[0]->createdAt(),
                'compacted_to' => $lastCompacted->createdAt(),
            ],
        ]);

        if ($summaryRecord === null) {
            return;
        }

        // Delete compacted messages.
        $idsToDelete = array_map(
            static fn (MemoryRecord $record): string => $record->id(),
            $toCompact
        );

        // Also delete the old summary if it exists.
        if ($existingSummary !== null) {
            $idsToDelete[] = $existingSummary->id();
        }

        MemoryQuery::new()->deleteByIds($idsToDelete);

        /**
         * Fires after conversation messages have been compacted.
         *
         * @since 1.0.0
         *
         * @param MemoryRecord $summaryRecord  The new summary record.
         * @param string       $conversationId The conversation ID.
         * @param int          $compactedCount Number of messages that were compacted.
         */
        do_action(
            'infomaniak_ai_memory_compacted',
            $summaryRecord,
            $conversationId,
            count($toCompact)
        );
    }

    /**
     * Calls the AI to produce a summary of the given records.
     *
     * Runs in the shutdown hook context — no user is waiting for this.
     *
     * @param MemoryRecord[]    $records         Records to summarize.
     * @param MemoryRecord|null $existingSummary  Previous summary to build upon.
     * @param int               $summaryMaxTokens Max tokens for the output.
     * @param string|null       $summaryModel     Model to use (null = default).
     * @return string The summary text, or empty string on failure.
     */
    private static function summarize(
        array $records,
        ?MemoryRecord $existingSummary,
        int $summaryMaxTokens,
        ?string $summaryModel
    ): string {
        if (!class_exists(AiClient::class)) {
            return '';
        }

        // Build the conversation text to summarize.
        $lines = [];

        if ($existingSummary !== null) {
            $lines[] = '[Previous summary]: ' . $existingSummary->content();
            $lines[] = '';
            $lines[] = '[New messages to incorporate]:';
        }

        foreach ($records as $record) {
            $lines[] = strtoupper($record->role()) . ': ' . $record->content();
        }

        $prompt = implode("\n", $lines);

        // Track this AI call as memory_compaction for cost attribution.
        if (class_exists(UsageTracker::class)) {
            UsageTracker::setCurrentPreset('memory_compaction');
        }

        try {
            $builder = AiClient::prompt($prompt)
                ->usingProvider('infomaniak')
                ->usingTemperature(0.3)
                ->usingMaxTokens($summaryMaxTokens)
                ->usingSystemInstruction(
                    'You are a conversation summarizer. Produce a concise summary of the conversation. '
                    . 'Preserve key facts, decisions, user preferences, and important context. '
                    . 'Use bullet points. Do not add commentary or interpretation. '
                    . 'If a previous summary is provided, incorporate it into the new summary.'
                );

            if ($summaryModel !== null) {
                $builder->usingModelPreference($summaryModel);
            }

            $result = $builder->generateText();

            return (string) $result;
        } catch (\Throwable $e) {
            return '';
        } finally {
            if (class_exists(UsageTracker::class)) {
                UsageTracker::clearCurrentPreset();
            }
        }
    }
}
