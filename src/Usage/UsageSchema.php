<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Usage;

/**
 * Handles database table creation and schema versioning for usage tracking.
 *
 * @since 1.0.0
 */
class UsageSchema
{
    /**
     * Current schema version.
     *
     * @var string
     */
    private const VERSION = '1.0.0';

    /**
     * Option name for the stored schema version.
     *
     * @var string
     */
    private const VERSION_OPTION = 'infomaniak_ai_usage_db_version';

    /**
     * Returns the full table name including the WordPress prefix.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'infomaniak_ai_usage';
    }

    /**
     * Creates the usage table if it does not exist.
     *
     * Called on plugin activation.
     *
     * @since 1.0.0
     */
    public static function install(): void
    {
        global $wpdb;

        $table = self::tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id varchar(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            provider_id varchar(100) NOT NULL DEFAULT '',
            model_id varchar(255) NOT NULL DEFAULT '',
            model_name varchar(255) NOT NULL DEFAULT '',
            preset_name varchar(255) DEFAULT NULL,
            prompt_tokens int unsigned NOT NULL DEFAULT 0,
            completion_tokens int unsigned NOT NULL DEFAULT 0,
            total_tokens int unsigned NOT NULL DEFAULT 0,
            capability varchar(100) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY provider_id (provider_id),
            KEY preset_name (preset_name),
            KEY created_at (created_at),
            KEY user_created (user_id, created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::VERSION_OPTION, self::VERSION);
    }

    /**
     * Checks if the schema needs upgrading and runs install if so.
     *
     * Called on init to handle upgrades after plugin updates.
     *
     * @since 1.0.0
     */
    public static function maybeUpgrade(): void
    {
        $installed = get_option(self::VERSION_OPTION, '');

        if ($installed !== self::VERSION) {
            self::install();
        }
    }
}
