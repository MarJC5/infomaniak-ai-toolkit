<?php

/**
 * Plugin Name: AI Provider for Infomaniak
 * Plugin URI: https://www.infomaniak.com/en/hosting/ai-tools
 * Description: AI Provider for Infomaniak for the WordPress AI Client. Provides access to open-source models (Llama, Mistral, DeepSeek, Qwen) hosted in Switzerland.
 * Requires at least: 6.9
 * Requires PHP: 8.0
 * Version: 1.0.0
 * Author: Custom
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-provider-for-infomaniak
 *
 * @package WordPress\InfomaniakAiProvider
 */

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\InfomaniakAiProvider\Commands\CommandLoader;
use WordPress\InfomaniakAiProvider\Provider\InfomaniakProvider;
use WordPress\InfomaniakAiProvider\Memory\MemorySchema;
use WordPress\InfomaniakAiProvider\Usage\UsageSchema;
use WordPress\InfomaniakAiProvider\Usage\UsageTracker;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

// Create database tables on plugin activation.
register_activation_hook(__FILE__, [UsageSchema::class, 'install']);
register_activation_hook(__FILE__, [MemorySchema::class, 'install']);

/**
 * Upgrades the usage table schema if needed and starts usage tracking.
 *
 * @since 1.0.0
 */
function init_usage_tracking(): void
{
    UsageSchema::maybeUpgrade();
    UsageTracker::init();
}

add_action('init', __NAMESPACE__ . '\\init_usage_tracking');

/**
 * Upgrades the memory table schema if needed.
 *
 * @since 1.0.0
 */
function init_memory(): void
{
    MemorySchema::maybeUpgrade();
}

add_action('init', __NAMESPACE__ . '\\init_memory');

/**
 * Loads the plugin text domain for translations.
 *
 * @since 1.0.0
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        'ai-provider-for-infomaniak',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

add_action('init', __NAMESPACE__ . '\\load_textdomain');

/**
 * Registers the AI Provider for Infomaniak with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(InfomaniakProvider::class)) {
        return;
    }

    $registry->registerProvider(InfomaniakProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Registers the plugin settings.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_settings(): void
{
    register_setting('infomaniak_ai', 'infomaniak_ai_product_id', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '',
    ]);

    add_settings_section(
        'infomaniak_ai_settings',
        __('Infomaniak AI Configuration', 'ai-provider-for-infomaniak'),
        function () {
            echo '<p>';
            echo esc_html__(
                'Configure your Infomaniak AI product settings. Your API token is managed via Settings > Connectors.',
                'ai-provider-for-infomaniak'
            );
            echo '</p>';
        },
        'infomaniak-ai'
    );

    add_settings_field(
        'infomaniak_ai_product_id',
        __('Product ID', 'ai-provider-for-infomaniak'),
        function () {
            $value = InfomaniakProvider::getProductId();
            $isFromOption = !has_filter('infomaniak_ai_product_id')
                && !defined('INFOMANIAK_AI_PRODUCT_ID');
            echo '<input type="text" name="infomaniak_ai_product_id" value="'
                . esc_attr($value) . '" class="regular-text"'
                . ($isFromOption ? '' : ' disabled') . ' />';
            echo '<p class="description">';
            if (defined('INFOMANIAK_AI_PRODUCT_ID')) {
                echo esc_html__(
                    'Currently set via the INFOMANIAK_AI_PRODUCT_ID constant.',
                    'ai-provider-for-infomaniak'
                );
            } elseif (has_filter('infomaniak_ai_product_id')) {
                echo esc_html__(
                    'Currently set via the infomaniak_ai_product_id filter.',
                    'ai-provider-for-infomaniak'
                );
            } else {
                echo esc_html__(
                    'Your Infomaniak AI product ID. Find it in your Infomaniak manager at AI Tools.',
                    'ai-provider-for-infomaniak'
                );
            }
            echo '</p>';
        },
        'infomaniak-ai',
        'infomaniak_ai_settings'
    );
}

/**
 * Adds the settings page under the Settings menu.
 *
 * @since 1.0.0
 *
 * @return void
 */
function add_settings_page(): void
{
    add_options_page(
        __('Infomaniak AI', 'ai-provider-for-infomaniak'),
        __('Infomaniak AI', 'ai-provider-for-infomaniak'),
        'manage_options',
        'infomaniak-ai',
        __NAMESPACE__ . '\\render_settings_page'
    );
}

/**
 * Renders the settings page.
 *
 * @since 1.0.0
 *
 * @return void
 */
function render_settings_page(): void
{
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('infomaniak_ai');
            do_settings_sections('infomaniak-ai');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', __NAMESPACE__ . '\\register_settings');
add_action('admin_menu', __NAMESPACE__ . '\\add_settings_page');

/**
 * Fetches and caches the list of available models from Infomaniak.
 *
 * Uses a transient with a 12-hour expiry. The list is stored as an array of
 * ['id' => '...', 'name' => '...', 'type' => '...'] entries.
 * Only models with info_status "ready" and supported types (llm, image) are included.
 *
 * @since 1.0.0
 *
 * @param bool $force Force a refresh, ignoring the cache.
 * @return array List of available models, each with 'id', 'name', and 'type' keys.
 */
function refresh_models_cache(bool $force = false): array
{
    $transient_key = 'infomaniak_ai_models';

    if (!$force) {
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            return $cached;
        }
    }

    // Read the API key from the connector setting.
    try {
        remove_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
        $apiKey = get_option('connectors_ai_infomaniak_api_key', '');
    } finally {
        add_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
    }

    if (empty($apiKey)) {
        return [];
    }

    $url = 'https://api.infomaniak.com/1/ai/models';
    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($body['data']) || !is_array($body['data'])) {
        return [];
    }

    $supported_types = ['llm', 'image'];

    $models = [];
    foreach ($body['data'] as $model) {
        if (!is_array($model) || empty($model['name'])) {
            continue;
        }

        // Only include models that are ready.
        if (($model['info_status'] ?? '') !== 'ready') {
            continue;
        }

        // Only include supported types.
        $type = $model['type'] ?? '';
        if (!in_array($type, $supported_types, true)) {
            continue;
        }

        $models[] = [
            'id' => $model['name'],
            'name' => $model['description'] ?? $model['name'],
            'type' => $type,
        ];
    }

    set_transient($transient_key, $models, 12 * HOUR_IN_SECONDS);

    return $models;
}

/**
 * Returns the cached list of available Infomaniak models.
 *
 * @since 1.0.0
 *
 * @return array List of models with 'id', 'name', and 'type' keys.
 */
function get_available_models(): array
{
    return refresh_models_cache();
}

/**
 * Refreshes the models cache after the connector keys are passed to the AI client.
 *
 * Runs at init priority 25, after connectors pass keys at priority 20.
 *
 * @since 1.0.0
 */
function maybe_refresh_models(): void
{
    if (empty(InfomaniakProvider::getProductId())) {
        return;
    }

    $cached = get_transient('infomaniak_ai_models');

    // Refresh if no cache exists or if cache has old format (missing 'type' key).
    $needs_refresh = false === $cached;
    if (!$needs_refresh && is_array($cached) && !empty($cached)) {
        $first = reset($cached);
        if (is_array($first) && !array_key_exists('type', $first)) {
            $needs_refresh = true;
        }
    }

    if ($needs_refresh) {
        refresh_models_cache(true);
    }
}

add_action('init', __NAMESPACE__ . '\\maybe_refresh_models', 25);

/**
 * Forces a models cache refresh when the product ID setting is updated.
 *
 * @since 1.0.0
 */
function on_product_id_updated(): void
{
    delete_transient('infomaniak_ai_models');
}

add_action('update_option_infomaniak_ai_product_id', __NAMESPACE__ . '\\on_product_id_updated');

/**
 * Adds plugin slug to the Infomaniak connector script module data.
 *
 * This ensures the connector item gets a CSS class we can target
 * for icon injection on the Settings > Connectors page.
 *
 * @since 1.0.0
 *
 * @param array $data Script module data.
 * @return array Modified data with plugin slug added.
 */
function add_connector_plugin_data(array $data): array
{
    if (isset($data['connectors']['infomaniak'])) {
        if (!isset($data['connectors']['infomaniak']['plugin'])) {
            $data['connectors']['infomaniak']['plugin'] = [];
        }
        $data['connectors']['infomaniak']['plugin']['slug'] = 'ai-provider-for-infomaniak';
    }
    return $data;
}

add_filter('script_module_data_options-connectors-wp-admin', __NAMESPACE__ . '\\add_connector_plugin_data');

/**
 * Conditionally registers the icon injection script for the Connectors page.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page.
 */
function enqueue_connector_icon(string $hook_suffix): void
{
    $screen = get_current_screen();
    $is_connectors = (
        (isset($_GET['page']) && 'options-connectors-wp-admin' === $_GET['page']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        || ($screen && 'options-connectors' === $screen->id)
    );

    if (!$is_connectors) {
        return;
    }

    add_action('admin_print_footer_scripts', __NAMESPACE__ . '\\print_connector_icon_script');
}

/**
 * Prints the inline script that injects the Infomaniak logo SVG.
 *
 * Since WordPress core hardcodes connector logos for built-in providers only,
 * this uses a MutationObserver to inject the SVG icon into the DOM
 * once the React-rendered connector item appears.
 *
 * @since 1.0.0
 */
function print_connector_icon_script(): void
{
    // Infomaniak logo SVG adapted to 40x40 to match other connector logos.
    $svg = '<svg width="40" height="40" viewBox="0 0 81 80" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<rect x="0.666504" width="80" height="80" rx="13.3333" fill="#0098FF"/>'
        . '<path d="M34.5674 13.3333H19.3331V66.6666H34.5674V56.6257L40.1704 51.1686'
        . 'L48.044 66.6666H64.853L50.0947 41.5643L64.0473 28.0308H45.7002L34.5674 40.8367V13.3333Z" fill="white"/>'
        . '</svg>';
    ?>
    <script>
    (function() {
        var svg = <?php echo wp_json_encode( $svg ); ?>;
        var selector = '.connector-item--ai-provider-for-infomaniak';

        function injectIcon() {
            var item = document.querySelector(selector);
            if (!item || item.dataset.infomaniakIcon) return;

            var hstack = item.firstElementChild && item.firstElementChild.firstElementChild;
            if (!hstack) return;
            if (hstack.querySelector('svg')) return;

            var wrapper = document.createElement('div');
            wrapper.style.flex = 'none';
            wrapper.style.lineHeight = '0';
            wrapper.innerHTML = svg;
            hstack.insertBefore(wrapper, hstack.firstElementChild);
            item.dataset.infomaniakIcon = '1';
        }

        injectIcon();

        var observer = new MutationObserver(function() { injectIcon(); });
        observer.observe(document.body, { childList: true, subtree: true });
        setTimeout(function() { observer.disconnect(); }, 10000);
    })();
    </script>
    <?php
}

add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_connector_icon', 20);

// Register markdown commands as WordPress Abilities.
add_action('wp_abilities_api_init', [CommandLoader::class, 'registerAll']);

// WP-CLI command registration.
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('infomaniak-ai', CLI\Command::class);
}
