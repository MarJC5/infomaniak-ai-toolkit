<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Usage;

use WordPress\AiClient\Events\AfterGenerateResultEvent;

/**
 * Tracks AI usage globally by hooking into the WordPress AI Client.
 *
 * Listens on `wp_ai_client_after_generate_result` to log every AI generation
 * (presets and direct calls). Preset attribution is handled via a static context
 * set by BasePreset::execute().
 *
 * @since 1.0.0
 */
class UsageTracker
{
    /**
     * Stack of preset names for nested tracking.
     *
     * Supports nested preset calls (e.g. PresetA calling PresetB)
     * by pushing/popping instead of overwriting a single value.
     *
     * @var string[]
     */
    private static array $presetStack = [];

    /**
     * Token usage from the most recent AI generation.
     *
     * Populated in onAfterGenerateResult() before the database insert,
     * allowing BasePreset::execute() to read real token counts after
     * generateText() completes.
     *
     * @var array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null
     */
    private static ?array $lastTokenUsage = null;

    /**
     * Registers the usage tracking hook.
     *
     * @since 1.0.0
     */
    public static function init(): void
    {
        add_action(
            'wp_ai_client_after_generate_result',
            [self::class, 'onAfterGenerateResult'],
            10,
            1
        );
    }

    /**
     * Sets the current preset context for attribution.
     *
     * Called by BasePreset::execute() before the AI call.
     *
     * @since 1.0.0
     *
     * @param string $presetName The preset identifier.
     */
    public static function setCurrentPreset(string $presetName): void
    {
        self::$presetStack[] = $presetName;
    }

    /**
     * Clears the current preset context.
     *
     * Called by BasePreset::execute() in a finally block.
     * Pops the stack so outer presets resume their context.
     *
     * @since 1.0.0
     */
    public static function clearCurrentPreset(): void
    {
        array_pop(self::$presetStack);
    }

    /**
     * Returns the current preset name, or null if none.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public static function getCurrentPreset(): ?string
    {
        $current = end(self::$presetStack);
        return $current !== false ? $current : null;
    }

    /**
     * Returns the token usage from the most recent AI generation.
     *
     * Call this after generateText() to get real token counts
     * for the generation that just completed.
     *
     * @since 1.0.0
     *
     * @return array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null
     */
    public static function getLastTokenUsage(): ?array
    {
        return self::$lastTokenUsage;
    }

    /**
     * Clears the last token usage data.
     *
     * @since 1.0.0
     */
    public static function clearLastTokenUsage(): void
    {
        self::$lastTokenUsage = null;
    }

    /**
     * Hook callback: logs usage after each AI generation.
     *
     * @since 1.0.0
     *
     * @param AfterGenerateResultEvent $event The event fired by the AI Client.
     */
    public static function onAfterGenerateResult($event): void
    {
        $result = $event->getResult();
        $tokenUsage = $result->getTokenUsage();
        $model = $event->getModel();
        $capability = $event->getCapability();

        $modelMeta = $model->metadata();
        $providerMeta = $model->providerMetadata();

        // Store token usage for retrieval by BasePreset::execute().
        self::$lastTokenUsage = [
            'prompt_tokens'     => $tokenUsage->getPromptTokens(),
            'completion_tokens' => $tokenUsage->getCompletionTokens(),
            'total_tokens'      => $tokenUsage->getTotalTokens(),
        ];

        $data = [
            'id' => wp_generate_uuid4(),
            'user_id' => get_current_user_id(),
            'provider_id' => $providerMeta->getId(),
            'model_id' => $modelMeta->getId(),
            'model_name' => $modelMeta->getName(),
            'preset_name' => self::getCurrentPreset(),
            'prompt_tokens' => $tokenUsage->getPromptTokens(),
            'completion_tokens' => $tokenUsage->getCompletionTokens(),
            'total_tokens' => $tokenUsage->getTotalTokens(),
            'capability' => $capability !== null ? $capability->value : '',
            'created_at' => current_time('mysql', true),
        ];

        /**
         * Filters whether usage should be tracked for this generation.
         *
         * Return false to skip logging (e.g. to filter by provider).
         *
         * @since 1.0.0
         *
         * @param bool  $shouldTrack Whether to track this usage. Default true.
         * @param array $data        The usage data that would be inserted.
         * @param AfterGenerateResultEvent $event The original event.
         */
        $shouldTrack = apply_filters('infomaniak_ai_should_track_usage', true, $data, $event);

        if (!$shouldTrack) {
            return;
        }

        self::insertRecord($data, $event);
    }

    /**
     * Inserts a usage record into the database.
     *
     * @since 1.0.0
     *
     * @param array                    $data  Row data.
     * @param AfterGenerateResultEvent $event The original AI Client event.
     */
    private static function insertRecord(array $data, $event): void
    {
        global $wpdb;

        $table = UsageSchema::tableName();

        $wpdb->insert(
            $table,
            $data,
            [
                '%s', // id
                '%d', // user_id
                '%s', // provider_id
                '%s', // model_id
                '%s', // model_name
                '%s', // preset_name
                '%d', // prompt_tokens
                '%d', // completion_tokens
                '%d', // total_tokens
                '%s', // capability
                '%s', // created_at
            ]
        );

        if ($wpdb->rows_affected) {
            $record = UsageRecord::fromArray($data);

            /**
             * Fires after a usage record has been logged.
             *
             * Use this to implement quotas, alerts, or billing.
             *
             * @since 1.0.0
             *
             * @param UsageRecord $record The logged usage record.
             * @param array       $data   The raw data that was inserted.
             * @param AfterGenerateResultEvent $event The original AI Client event.
             */
            do_action('infomaniak_ai_usage_logged', $record, $data, $event);
        }
    }

    /**
     * Creates a new UsageQuery instance.
     *
     * @since 1.0.0
     *
     * @return UsageQuery
     */
    public static function query(): UsageQuery
    {
        return new UsageQuery();
    }
}
