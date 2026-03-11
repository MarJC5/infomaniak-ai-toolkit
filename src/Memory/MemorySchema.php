<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

/**
 * Handles database table creation and schema versioning for conversation memory.
 *
 * @since 1.0.0
 */
class MemorySchema
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
    private const VERSION_OPTION = 'ai_provider_toolkit_memory_db_version';

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
        return $wpdb->prefix . 'ai_provider_toolkit_memory';
    }

    /**
     * Creates the memory table if it does not exist.
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
            conversation_id varchar(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            preset_name varchar(255) NOT NULL DEFAULT '',
            role varchar(20) NOT NULL DEFAULT '',
            content longtext NOT NULL,
            token_count int unsigned NOT NULL DEFAULT 0,
            metadata text DEFAULT NULL,
            created_at datetime(6) NOT NULL DEFAULT '0000-00-00 00:00:00.000000',
            PRIMARY KEY  (id),
            KEY conversation_id (conversation_id),
            KEY user_id (user_id),
            KEY conv_created (conversation_id, created_at),
            KEY user_conv (user_id, conversation_id)
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
