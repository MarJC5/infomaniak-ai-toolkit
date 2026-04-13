<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Admin;

use WordPress\InfomaniakAiToolkit\Commands\CommandLoader;
use WordPress\InfomaniakAiToolkit\Commands\CommandStore;
use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;
use WordPress\InfomaniakAiToolkit\RateLimit\RateLimitConfig;
use WordPress\InfomaniakAiToolkit\Usage\UsageStats;

/**
 * Manages the plugin settings page with tabbed navigation.
 *
 * Registers settings, enqueues assets, and renders the
 * settings page with a Claude-inspired design.
 *
 * @since 1.0.0
 */
class SettingsPage
{
    /**
     * Valid tab slugs.
     *
     * @var string[]
     */
    private const TABS = ['general', 'commands', 'rate-limits', 'usage'];

    /**
     * Registers all admin hooks.
     *
     * @since 1.0.0
     */
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_menu', [self::class, 'addPage']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('wp_ajax_infomaniak_ai_test_connection', [self::class, 'ajaxTestConnection']);
        add_action('wp_ajax_infomaniak_ai_save_command', [self::class, 'ajaxSaveCommand']);
        add_action('wp_ajax_infomaniak_ai_delete_command', [self::class, 'ajaxDeleteCommand']);
    }

    /**
     * Registers plugin settings with the WordPress Settings API.
     *
     * Only uses register_setting() for sanitization and nonce handling.
     * Sections and fields are rendered manually in the view templates.
     *
     * @since 1.0.0
     */
    public static function registerSettings(): void
    {
        register_setting('infomaniak_ai', 'infomaniak_ai_product_id', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        register_setting('infomaniak_ai', RateLimitConfig::optionName(), [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitizeRateLimits'],
            'default'           => [],
        ]);
    }

    /**
     * Adds the settings page under the Settings menu.
     *
     * @since 1.0.0
     */
    public static function addPage(): void
    {
        add_options_page(
            __('Infomaniak AI', 'infomaniak-ai-toolkit'),
            __('Infomaniak AI', 'infomaniak-ai-toolkit'),
            'manage_options',
            'infomaniak-ai',
            [self::class, 'render']
        );
    }

    /**
     * Enqueues admin CSS only on the plugin settings page.
     *
     * @since 1.0.0
     *
     * @param string $hookSuffix The current admin page hook suffix.
     */
    public static function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_infomaniak-ai') {
            return;
        }

        wp_enqueue_style(
            'infomaniak-ai-admin',
            plugins_url('assets/css/admin.css', INFOMANIAK_AI_PLUGIN_FILE),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'infomaniak-ai-admin',
            plugins_url('assets/js/admin.js', INFOMANIAK_AI_PLUGIN_FILE),
            [],
            '1.0.0',
            true
        );

        wp_localize_script('infomaniak-ai-admin', 'infomaniakAiAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('infomaniak_ai_commands'),
        ]);
    }

    /**
     * Renders the settings page.
     *
     * @since 1.0.0
     */
    public static function render(): void
    {
        $currentTab = self::getCurrentTab();
        $tabs       = self::tabLabels();

        // Gather data for the current tab.
        $data = match ($currentTab) {
            'general'     => self::getGeneralData(),
            'commands'    => self::getCommandsData(),
            'rate-limits' => self::getRateLimitsData(),
            'usage'       => self::getUsageData(),
            default       => self::getGeneralData(),
        };

        include __DIR__ . '/views/settings-page.php';
    }

    /**
     * Sanitizes the rate limits input from the settings form.
     *
     * @since 1.0.0
     *
     * @param mixed $input Raw input from the form.
     * @return array Sanitized rate limits.
     */
    public static function sanitizeRateLimits($input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $role => $config) {
            if (!is_array($config)) {
                continue;
            }
            $sanitized[sanitize_key($role)] = [
                'limit'  => max(0, (int) ($config['limit'] ?? 0)),
                'window' => RateLimitConfig::sanitizeWindow($config['window'] ?? 'hour'),
            ];
        }

        return $sanitized;
    }

    /**
     * Handles the AJAX test connection request.
     *
     * @since 1.0.0
     */
    public static function ajaxTestConnection(): void
    {
        check_ajax_referer('infomaniak_ai_test_connection');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'infomaniak-ai-toolkit'));
        }

        // Retrieve the unmasked API key.
        try {
            remove_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
            $apiKey = get_option('connectors_ai_infomaniak_api_key', '');
        } finally {
            add_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
        }

        if (empty($apiKey)) {
            wp_send_json_error(__('API key not configured. Set it in Settings > Connectors.', 'infomaniak-ai-toolkit'));
        }

        $productId = InfomaniakProvider::getProductId();
        if (empty($productId)) {
            wp_send_json_error(__('Product ID not configured.', 'infomaniak-ai-toolkit'));
        }

        $response = wp_remote_get('https://api.infomaniak.com/1/ai/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            wp_send_json_error(
                /* translators: %d: HTTP status code */
                sprintf(__('API returned HTTP %d.', 'infomaniak-ai-toolkit'), $code)
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['data']) || !is_array($body['data'])) {
            wp_send_json_error(__('Unexpected API response.', 'infomaniak-ai-toolkit'));
        }

        $readyCount = 0;
        foreach ($body['data'] as $model) {
            if (is_array($model) && ($model['info_status'] ?? '') === 'ready') {
                $readyCount++;
            }
        }

        wp_send_json_success(
            /* translators: %d: number of available models */
            sprintf(__('Connection successful. %d models available.', 'infomaniak-ai-toolkit'), $readyCount)
        );
    }

    /**
     * Returns translated tab labels.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Tab slug => translated label.
     */
    private static function tabLabels(): array
    {
        return [
            'general'     => __('General', 'infomaniak-ai-toolkit'),
            'commands'    => __('Commands', 'infomaniak-ai-toolkit'),
            'rate-limits' => __('Rate Limits', 'infomaniak-ai-toolkit'),
            'usage'       => __('Usage', 'infomaniak-ai-toolkit'),
        ];
    }

    /**
     * Returns the current tab slug from the URL.
     *
     * @since 1.0.0
     *
     * @return string Valid tab slug, defaults to 'general'.
     */
    private static function getCurrentTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = sanitize_key($_GET['tab'] ?? 'general');

        return in_array($tab, self::TABS, true) ? $tab : 'general';
    }

    /**
     * Returns template data for the General tab.
     *
     * @since 1.0.0
     *
     * @return array{productId: string, isFromOption: bool, source: string}
     */
    private static function getGeneralData(): array
    {
        $productId    = InfomaniakProvider::getProductId();
        $isFromOption = !has_filter('infomaniak_ai_product_id')
            && !defined('INFOMANIAK_AI_PRODUCT_ID');

        $source = 'option';
        if (defined('INFOMANIAK_AI_PRODUCT_ID')) {
            $source = 'constant';
        } elseif (has_filter('infomaniak_ai_product_id')) {
            $source = 'filter';
        }

        return [
            'productId'    => $productId,
            'isFromOption' => $isFromOption,
            'source'       => $source,
        ];
    }

    /**
     * Returns template data for the Usage tab.
     *
     * @since 1.0.0
     *
     * @return array
     */
    private static function getUsageData(): array
    {
        $hasData = UsageStats::hasData();

        if (!$hasData) {
            return ['hasData' => false];
        }

        $from = gmdate('Y-m-d', strtotime('-30 days'));
        $to   = gmdate('Y-m-d');

        $dailyTotals = UsageStats::dailyTotals(30);

        return [
            'hasData'       => true,
            'dailyTotals'   => $dailyTotals,
            'totalTokens'   => UsageStats::totalTokens($from, $to),
            'totalRequests' => UsageStats::totalRequests($from, $to),
            'tokensToday'   => UsageStats::tokensToday(),
            'requestsToday' => UsageStats::requestsToday(),
            'topModels'     => UsageStats::topModels(5, $from, $to),
            'topPresets'    => UsageStats::topPresets(5, $from, $to),
            'sparklineData' => array_column($dailyTotals, 'total_tokens'),
        ];
    }

    /**
     * Returns template data for the Rate Limits tab.
     *
     * @since 1.0.0
     *
     * @return array{limits: array, windows: string[], windowLabels: array<string, string>, optionName: string}
     */
    private static function getRateLimitsData(): array
    {
        return [
            'limits'       => RateLimitConfig::getAll(),
            'windows'      => RateLimitConfig::validWindows(),
            'windowLabels' => [
                'hour'  => __('Hour', 'infomaniak-ai-toolkit'),
                'day'   => __('Day', 'infomaniak-ai-toolkit'),
                'month' => __('Month', 'infomaniak-ai-toolkit'),
            ],
            'optionName'   => RateLimitConfig::optionName(),
        ];
    }

    /**
     * Returns template data for the Commands tab.
     *
     * Routes to list, new, or edit view based on query parameters.
     *
     * @since 1.0.0
     *
     * @return array
     */
    private static function getCommandsData(): array
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = sanitize_key($_GET['action'] ?? '');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $slug = sanitize_key($_GET['command'] ?? '');

        if ($action === 'new') {
            return [
                'view'   => 'new',
                'models' => \WordPress\InfomaniakAiToolkit\get_available_models(),
            ];
        }

        if ($action === 'edit' && $slug !== '') {
            $command = CommandStore::get($slug);

            if ($command === null) {
                return [
                    'view'     => 'list',
                    'commands' => CommandLoader::discoverWithSource(),
                ];
            }

            return [
                'view'    => 'edit',
                'slug'    => $slug,
                'command' => $command,
                'models'  => \WordPress\InfomaniakAiToolkit\get_available_models(),
            ];
        }

        return [
            'view'     => 'list',
            'commands' => CommandLoader::discoverWithSource(),
        ];
    }

    /**
     * Handles AJAX save command request (create or update).
     *
     * @since 1.0.0
     */
    public static function ajaxSaveCommand(): void
    {
        check_ajax_referer('infomaniak_ai_commands');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'infomaniak-ai-toolkit'));
        }

        $isNew = !empty($_POST['is_new']);
        $slug  = CommandStore::sanitizeSlug($_POST['slug'] ?? '');

        if ($slug === '') {
            wp_send_json_error(__('Command name is required.', 'infomaniak-ai-toolkit'));
        }

        $data = CommandStore::sanitizeCommand($_POST);

        if (empty($data['description'])) {
            wp_send_json_error(__('Description is required.', 'infomaniak-ai-toolkit'));
        }

        if (empty($data['prompt_template'])) {
            wp_send_json_error(__('Prompt template is required.', 'infomaniak-ai-toolkit'));
        }

        // When creating, check for conflict with file-based commands.
        if ($isNew) {
            $allCommands = CommandLoader::discoverWithSource();
            if (isset($allCommands[$slug]) && $allCommands[$slug]['source'] === 'file') {
                wp_send_json_error(
                    /* translators: %s: command slug */
                    sprintf(__('A file-based command with the name "%s" already exists.', 'infomaniak-ai-toolkit'), $slug)
                );
            }

            if (CommandStore::exists($slug)) {
                wp_send_json_error(
                    /* translators: %s: command slug */
                    sprintf(__('A command with the name "%s" already exists.', 'infomaniak-ai-toolkit'), $slug)
                );
            }
        }

        $saved = CommandStore::save($slug, $data);

        if (!$saved) {
            wp_send_json_error(__('Failed to save command.', 'infomaniak-ai-toolkit'));
        }

        CommandLoader::clearCache();

        wp_send_json_success([
            'redirect' => admin_url('options-general.php?page=infomaniak-ai&tab=commands'),
        ]);
    }

    /**
     * Handles AJAX delete command request.
     *
     * @since 1.0.0
     */
    public static function ajaxDeleteCommand(): void
    {
        check_ajax_referer('infomaniak_ai_commands');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'infomaniak-ai-toolkit'));
        }

        $slug = CommandStore::sanitizeSlug($_POST['slug'] ?? '');

        if ($slug === '') {
            wp_send_json_error(__('Command slug is required.', 'infomaniak-ai-toolkit'));
        }

        $deleted = CommandStore::delete($slug);

        if (!$deleted) {
            wp_send_json_error(__('Failed to delete command.', 'infomaniak-ai-toolkit'));
        }

        CommandLoader::clearCache();

        wp_send_json_success();
    }
}
