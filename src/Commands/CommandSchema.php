<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Commands;

/**
 * Handles database table creation and schema versioning for custom commands.
 *
 * @since 1.0.0
 */
class CommandSchema
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
    private const VERSION_OPTION = 'ai_provider_toolkit_commands_db_version';

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
        return $wpdb->prefix . 'ai_provider_toolkit_commands';
    }

    /**
     * Creates the commands table if it does not exist.
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
            slug varchar(100) NOT NULL,
            label varchar(255) NOT NULL DEFAULT '',
            description text NOT NULL,
            prompt_template longtext NOT NULL,
            system_prompt longtext DEFAULT NULL,
            temperature float NOT NULL DEFAULT 0.7,
            max_tokens int unsigned NOT NULL DEFAULT 1000,
            model varchar(255) DEFAULT NULL,
            model_type varchar(20) NOT NULL DEFAULT 'llm',
            category varchar(100) NOT NULL DEFAULT 'content',
            permission varchar(100) NOT NULL DEFAULT 'edit_posts',
            conversational tinyint(1) NOT NULL DEFAULT 0,
            provider varchar(100) NOT NULL DEFAULT 'infomaniak',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (slug),
            KEY category (category)
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
