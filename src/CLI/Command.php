<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\CLI;

use WP_CLI;
use WP_CLI_Command;
use WordPress\InfomaniakAiProvider\Commands\CommandLoader;
use WordPress\InfomaniakAiProvider\Commands\MarkdownCommand;
use WordPress\InfomaniakAiProvider\Memory\MemoryRecord;
use WordPress\InfomaniakAiProvider\Memory\MemoryStore;
use WordPress\InfomaniakAiProvider\Usage\UsageRecord;
use WordPress\InfomaniakAiProvider\Usage\UsageTracker;
use function WordPress\InfomaniakAiProvider\get_available_models;
use function WordPress\InfomaniakAiProvider\refresh_models_cache;

/**
 * Manage the Infomaniak AI provider: usage, models, commands, memory, and cache.
 *
 * @since 1.0.0
 */
class Command extends WP_CLI_Command
{
    /**
     * Displays AI usage statistics.
     *
     * ## OPTIONS
     *
     * [--user=<user_id>]
     * : Filter by WordPress user ID.
     *
     * [--model=<model_id>]
     * : Filter by model ID (e.g. "llama-3.3-70b").
     *
     * [--preset=<preset_name>]
     * : Filter by preset name (e.g. "summarize").
     *
     * [--from=<date>]
     * : Show records from this date (Y-m-d or Y-m-d H:i:s).
     *
     * [--to=<date>]
     * : Show records until this date (Y-m-d or Y-m-d H:i:s).
     *
     * [--limit=<number>]
     * : Maximum number of records to display.
     * ---
     * default: 25
     * ---
     *
     * [--summary]
     * : Show aggregate summary instead of individual records.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # Show last 25 usage records
     *     wp infomaniak-ai usage
     *
     *     # Show usage for a specific user this month
     *     wp infomaniak-ai usage --user=1 --from=2026-03-01
     *
     *     # Show summary of total token usage
     *     wp infomaniak-ai usage --summary
     *
     *     # Export as JSON
     *     wp infomaniak-ai usage --format=json --limit=1000
     *
     * @subcommand usage
     */
    public function usage($args, $assoc_args): void
    {
        $query = UsageTracker::query();

        if (isset($assoc_args['user'])) {
            $query->forUser((int) $assoc_args['user']);
        }
        if (isset($assoc_args['model'])) {
            $query->forModel($assoc_args['model']);
        }
        if (isset($assoc_args['preset'])) {
            $query->forPreset($assoc_args['preset']);
        }
        if (isset($assoc_args['from'])) {
            $query->from($assoc_args['from']);
        }
        if (isset($assoc_args['to'])) {
            $query->to($assoc_args['to']);
        }

        if (isset($assoc_args['summary'])) {
            $count = $query->count();
            $promptTokens = $query->sum('prompt_tokens');
            $completionTokens = $query->sum('completion_tokens');
            $totalTokens = $query->sumTotalTokens();

            $summary = [[
                'total_requests'    => $count,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens'      => $totalTokens,
            ]];

            $format = $assoc_args['format'] ?? 'table';
            \WP_CLI\Utils\format_items(
                $format,
                $summary,
                ['total_requests', 'prompt_tokens', 'completion_tokens', 'total_tokens']
            );
            return;
        }

        $limit = (int) ($assoc_args['limit'] ?? 25);
        $query->limit($limit);

        $records = $query->get();

        if (empty($records)) {
            WP_CLI::warning(__('No usage records found.', 'ai-provider-for-infomaniak'));
            return;
        }

        $items = array_map(
            static fn(UsageRecord $r): array => $r->toArray(),
            $records
        );

        $fields = ['created_at', 'user_id', 'model_id', 'preset_name', 'prompt_tokens', 'completion_tokens', 'total_tokens'];
        $format = $assoc_args['format'] ?? 'table';

        \WP_CLI\Utils\format_items($format, $items, $fields);
    }

    /**
     * Lists available AI models from Infomaniak.
     *
     * ## OPTIONS
     *
     * [--type=<type>]
     * : Filter by model type.
     * ---
     * options:
     *   - llm
     *   - image
     * ---
     *
     * [--refresh]
     * : Force refresh from the Infomaniak API instead of using cached data.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # List all available models
     *     wp infomaniak-ai models
     *
     *     # List only LLM models
     *     wp infomaniak-ai models --type=llm
     *
     *     # Force refresh from API
     *     wp infomaniak-ai models --refresh
     *
     *     # Output as JSON
     *     wp infomaniak-ai models --format=json
     *
     * @subcommand models
     */
    public function models($args, $assoc_args): void
    {
        $force = isset($assoc_args['refresh']);

        if ($force) {
            WP_CLI::log(__('Refreshing models from Infomaniak API...', 'ai-provider-for-infomaniak'));
        }

        $models = refresh_models_cache($force);

        if (empty($models)) {
            WP_CLI::error(__('No models available. Check your API key and product ID configuration.', 'ai-provider-for-infomaniak'));
        }

        if (isset($assoc_args['type'])) {
            $type = $assoc_args['type'];
            $models = array_values(array_filter(
                $models,
                static fn(array $m): bool => ($m['type'] ?? '') === $type
            ));
        }

        if (empty($models)) {
            WP_CLI::warning(__('No models match the given filters.', 'ai-provider-for-infomaniak'));
            return;
        }

        $format = $assoc_args['format'] ?? 'table';
        \WP_CLI\Utils\format_items($format, $models, ['id', 'name', 'type']);

        if (!$force) {
            WP_CLI::log(__('Showing cached data. Use --refresh to fetch from API.', 'ai-provider-for-infomaniak'));
        }
    }

    /**
     * Manages the models and commands cache.
     *
     * ## OPTIONS
     *
     * <action>
     * : The cache action to perform.
     * ---
     * options:
     *   - clear
     *   - status
     * ---
     *
     * [--type=<type>]
     * : Which cache to target. Defaults to all.
     * ---
     * default: all
     * options:
     *   - all
     *   - models
     *   - commands
     * ---
     *
     * ## EXAMPLES
     *
     *     # Clear all caches
     *     wp infomaniak-ai cache clear
     *
     *     # Clear only the models cache
     *     wp infomaniak-ai cache clear --type=models
     *
     *     # Show cache status
     *     wp infomaniak-ai cache status
     *
     * @subcommand cache
     */
    public function cache($args, $assoc_args): void
    {
        $action = $args[0] ?? 'status';
        $type = $assoc_args['type'] ?? 'all';

        if ($action === 'status') {
            $this->cacheStatus();
            return;
        }

        $cleared = [];

        if ($type === 'all' || $type === 'models') {
            delete_transient('infomaniak_ai_models');
            $cleared[] = 'models';
        }

        if ($type === 'all' || $type === 'commands') {
            CommandLoader::clearCache();
            $cleared[] = 'commands';
        }

        WP_CLI::success(
            sprintf(
                /* translators: %s: comma-separated list of cleared caches */
                __('Cleared caches: %s.', 'ai-provider-for-infomaniak'),
                implode(', ', $cleared)
            )
        );
    }

    /**
     * Lists discovered markdown AI commands.
     *
     * ## OPTIONS
     *
     * [--verbose]
     * : Show additional fields (category, temperature, max_tokens, model, conversational).
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # List all markdown commands
     *     wp infomaniak-ai commands
     *
     *     # List with full details
     *     wp infomaniak-ai commands --verbose
     *
     *     # Output as JSON
     *     wp infomaniak-ai commands --format=json
     *
     * @subcommand commands
     */
    public function commands($args, $assoc_args): void
    {
        $commands = CommandLoader::discover();

        if (empty($commands)) {
            WP_CLI::warning(__('No markdown commands found.', 'ai-provider-for-infomaniak'));

            $dirs = CommandLoader::getCommandDirs();
            if (empty($dirs)) {
                WP_CLI::log(__('No command directories exist. Expected locations:', 'ai-provider-for-infomaniak'));
                WP_CLI::log('  - {plugin}/ai-commands/');
                WP_CLI::log('  - {theme}/ai-commands/');
            } else {
                WP_CLI::log(__('Scanned directories:', 'ai-provider-for-infomaniak'));
                foreach ($dirs as $dir) {
                    WP_CLI::log("  - {$dir}");
                }
            }
            return;
        }

        $verbose = isset($assoc_args['verbose']);

        $items = [];
        foreach ($commands as $cmd) {
            $item = [
                'name'        => $cmd->name(),
                'label'       => $cmd->label(),
                'description' => $cmd->description(),
            ];

            if ($verbose && $cmd instanceof MarkdownCommand) {
                $config = $cmd->getConfig();
                $item['category']       = $config['category'] ?? 'content';
                $item['temperature']    = $config['temperature'] ?? 0.7;
                $item['max_tokens']     = $config['max_tokens'] ?? 1000;
                $item['model']          = $config['model'] ?? '-';
                $item['conversational'] = !empty($config['conversational']) ? 'yes' : 'no';
            }

            $items[] = $item;
        }

        $fields = $verbose
            ? ['name', 'label', 'description', 'category', 'temperature', 'max_tokens', 'model', 'conversational']
            : ['name', 'label', 'description'];

        $format = $assoc_args['format'] ?? 'table';
        \WP_CLI\Utils\format_items($format, $items, $fields);

        $dirs = CommandLoader::getCommandDirs();
        if (!empty($dirs)) {
            WP_CLI::log('');
            WP_CLI::log(__('Command directories:', 'ai-provider-for-infomaniak'));
            foreach ($dirs as $dir) {
                WP_CLI::log("  {$dir}");
            }
        }
    }

    /**
     * Inspects conversation memory records.
     *
     * ## OPTIONS
     *
     * [--conversation=<conversation_id>]
     * : Filter by conversation ID.
     *
     * [--user=<user_id>]
     * : Filter by WordPress user ID.
     *
     * [--preset=<preset_name>]
     * : Filter by preset name.
     *
     * [--from=<date>]
     * : Show records from this date (Y-m-d or Y-m-d H:i:s).
     *
     * [--to=<date>]
     * : Show records until this date (Y-m-d or Y-m-d H:i:s).
     *
     * [--limit=<number>]
     * : Maximum number of records to display.
     * ---
     * default: 25
     * ---
     *
     * [--summary]
     * : Show aggregate summary (message count and total tokens).
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     *   - count
     * ---
     *
     * ## EXAMPLES
     *
     *     # List recent memory records
     *     wp infomaniak-ai memory
     *
     *     # View messages in a specific conversation
     *     wp infomaniak-ai memory --conversation=abc-123
     *
     *     # Show summary
     *     wp infomaniak-ai memory --summary
     *
     *     # List memory for a user
     *     wp infomaniak-ai memory --user=1
     *
     * @subcommand memory
     */
    public function memory($args, $assoc_args): void
    {
        $query = MemoryStore::query();

        if (isset($assoc_args['conversation'])) {
            $query->forConversation($assoc_args['conversation']);
        }
        if (isset($assoc_args['user'])) {
            $query->forUser((int) $assoc_args['user']);
        }
        if (isset($assoc_args['preset'])) {
            $query->forPreset($assoc_args['preset']);
        }
        if (isset($assoc_args['from'])) {
            $query->from($assoc_args['from']);
        }
        if (isset($assoc_args['to'])) {
            $query->to($assoc_args['to']);
        }

        if (isset($assoc_args['summary'])) {
            $count = $query->count();
            $tokens = $query->sumTokens();

            $summary = [[
                'total_messages' => $count,
                'total_tokens'   => $tokens,
            ]];

            $format = $assoc_args['format'] ?? 'table';
            \WP_CLI\Utils\format_items($format, $summary, ['total_messages', 'total_tokens']);
            return;
        }

        $limit = (int) ($assoc_args['limit'] ?? 25);
        $query->limit($limit);
        $records = $query->get();

        if (empty($records)) {
            WP_CLI::warning(__('No memory records found.', 'ai-provider-for-infomaniak'));
            return;
        }

        $format = $assoc_args['format'] ?? 'table';
        $truncate = ($format === 'table');

        $items = array_map(
            static function (MemoryRecord $r) use ($truncate): array {
                $arr = $r->toArray();
                if ($truncate && mb_strlen($arr['content'], 'UTF-8') > 80) {
                    $arr['content'] = mb_substr($arr['content'], 0, 77, 'UTF-8') . '...';
                }
                unset($arr['metadata']);
                return $arr;
            },
            $records
        );

        $fields = ['conversation_id', 'user_id', 'role', 'content', 'token_count', 'created_at'];
        \WP_CLI\Utils\format_items($format, $items, $fields);
    }

    /**
     * Clears conversation memory records.
     *
     * Requires at least one filter to prevent accidental full deletion.
     *
     * ## OPTIONS
     *
     * [--conversation=<conversation_id>]
     * : Clear a specific conversation.
     *
     * [--user=<user_id>]
     * : Clear all conversations for a user.
     *
     * [--before=<date>]
     * : Clear all records older than this date (Y-m-d or Y-m-d H:i:s).
     *
     * [--yes]
     * : Skip confirmation prompt.
     *
     * ## EXAMPLES
     *
     *     # Clear a specific conversation
     *     wp infomaniak-ai memory-clear --conversation=abc-123
     *
     *     # Clear all conversations for a user
     *     wp infomaniak-ai memory-clear --user=42
     *
     *     # Clear old conversations (> 30 days)
     *     wp infomaniak-ai memory-clear --before=2026-02-08
     *
     *     # Non-interactive
     *     wp infomaniak-ai memory-clear --before=2026-02-08 --yes
     *
     * @subcommand memory-clear
     * @alias clear-memory
     */
    public function memory_clear($args, $assoc_args): void
    {
        if (!isset($assoc_args['conversation']) && !isset($assoc_args['user']) && !isset($assoc_args['before'])) {
            WP_CLI::error(__('You must specify at least one of: --conversation, --user, or --before.', 'ai-provider-for-infomaniak'));
        }

        $query = MemoryStore::query();

        if (isset($assoc_args['conversation'])) {
            $query->forConversation($assoc_args['conversation']);
        }
        if (isset($assoc_args['user'])) {
            $query->forUser((int) $assoc_args['user']);
        }
        if (isset($assoc_args['before'])) {
            $query->to($assoc_args['before']);
        }

        $count = $query->count();

        if ($count === 0) {
            WP_CLI::warning(__('No matching records found.', 'ai-provider-for-infomaniak'));
            return;
        }

        WP_CLI::log(
            sprintf(
                /* translators: %d: number of records */
                __('Found %d records to delete.', 'ai-provider-for-infomaniak'),
                $count
            )
        );

        if (!isset($assoc_args['yes'])) {
            WP_CLI::confirm(__('Are you sure you want to delete these records?', 'ai-provider-for-infomaniak'));
        }

        $deleted = $query->delete();

        WP_CLI::success(
            sprintf(
                /* translators: %d: number of deleted records */
                __('Deleted %d memory records.', 'ai-provider-for-infomaniak'),
                $deleted
            )
        );
    }

    /**
     * Shows cache status information.
     */
    private function cacheStatus(): void
    {
        $modelsCache = get_transient('infomaniak_ai_models');
        $modelsCount = is_array($modelsCache) ? count($modelsCache) : 0;

        if ($modelsCache !== false) {
            $modelsStatus = sprintf(
                /* translators: %d: number of cached models */
                __('cached (%d models)', 'ai-provider-for-infomaniak'),
                $modelsCount
            );
        } else {
            $modelsStatus = __('empty', 'ai-provider-for-infomaniak');
        }

        $commands = CommandLoader::discover();
        $commandsCount = count($commands);
        $commandsStatus = sprintf(
            /* translators: %d: number of commands */
            __('%d commands loaded', 'ai-provider-for-infomaniak'),
            $commandsCount
        );

        $items = [
            [
                'cache'  => 'models',
                'status' => $modelsStatus,
                'ttl'    => __('12 hours', 'ai-provider-for-infomaniak'),
            ],
            [
                'cache'  => 'commands',
                'status' => $commandsStatus,
                'ttl'    => __('per-request', 'ai-provider-for-infomaniak'),
            ],
        ];

        \WP_CLI\Utils\format_items('table', $items, ['cache', 'status', 'ttl']);
    }
}
