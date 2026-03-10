<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Commands;

/**
 * CRUD operations for admin-created commands stored in the database.
 *
 * @since 1.2.0
 */
class CommandStore
{
    /**
     * Returns all stored commands keyed by slug.
     *
     * @since 1.2.0
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAll(): array
    {
        global $wpdb;

        $table = CommandSchema::tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY slug ASC", ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $commands = [];
        foreach ($rows as $row) {
            $commands[$row['slug']] = $row;
        }

        return $commands;
    }

    /**
     * Returns a single command by slug.
     *
     * @since 1.2.0
     *
     * @param string $slug Command slug.
     * @return array<string, mixed>|null Null if not found.
     */
    public static function get(string $slug): ?array
    {
        global $wpdb;

        $table = CommandSchema::tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Saves a command (insert or update).
     *
     * @since 1.2.0
     *
     * @param string               $slug Command slug.
     * @param array<string, mixed> $data Command data.
     * @return bool True on success.
     */
    public static function save(string $slug, array $data): bool
    {
        global $wpdb;

        $table = CommandSchema::tableName();
        $now = current_time('mysql', true);

        $row = [
            'slug'            => $slug,
            'label'           => $data['label'] ?? '',
            'description'     => $data['description'] ?? '',
            'prompt_template' => $data['prompt_template'] ?? '',
            'system_prompt'   => $data['system_prompt'] ?? null,
            'temperature'     => $data['temperature'] ?? 0.7,
            'max_tokens'      => $data['max_tokens'] ?? 1000,
            'model'           => $data['model'] ?? null,
            'model_type'      => $data['model_type'] ?? 'llm',
            'category'        => $data['category'] ?? 'content',
            'permission'      => $data['permission'] ?? 'edit_posts',
            'conversational'  => $data['conversational'] ?? 0,
            'provider'        => $data['provider'] ?? 'infomaniak',
            'updated_at'      => $now,
        ];

        $formats = [
            '%s', '%s', '%s', '%s', '%s',
            '%f', '%d', '%s', '%s', '%s',
            '%s', '%d', '%s', '%s',
        ];

        $existing = self::get($slug);

        if ($existing) {
            unset($row['slug']);
            array_shift($formats);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update($table, $row, ['slug' => $slug], $formats, ['%s']);
        } else {
            $row['created_at'] = $now;
            $formats[] = '%s';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->insert($table, $row, $formats);
        }

        return $result !== false;
    }

    /**
     * Deletes a command by slug.
     *
     * @since 1.2.0
     *
     * @param string $slug Command slug.
     * @return bool True if the row was deleted.
     */
    public static function delete(string $slug): bool
    {
        global $wpdb;

        $table = CommandSchema::tableName();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete($table, ['slug' => $slug], ['%s']);

        return $result !== false && $result > 0;
    }

    /**
     * Checks if a command exists in the database.
     *
     * @since 1.2.0
     *
     * @param string $slug Command slug.
     * @return bool
     */
    public static function exists(string $slug): bool
    {
        return self::get($slug) !== null;
    }

    /**
     * Sanitizes a command slug.
     *
     * Uses the same regex as CommandLoader: lowercase alphanumerics and hyphens.
     *
     * @since 1.2.0
     *
     * @param string $input Raw input.
     * @return string Sanitized slug.
     */
    public static function sanitizeSlug(string $input): string
    {
        $slug = preg_replace('/[^a-z0-9-]/', '-', strtolower(trim($input)));
        return trim($slug, '-');
    }

    /**
     * Sanitizes a full command data array.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $data Raw input.
     * @return array<string, mixed> Sanitized data.
     */
    public static function sanitizeCommand(array $data): array
    {
        $validModelTypes = ['llm', 'image'];

        return [
            'label'           => sanitize_text_field($data['label'] ?? ''),
            'description'     => sanitize_text_field($data['description'] ?? ''),
            'prompt_template' => sanitize_textarea_field($data['prompt_template'] ?? ''),
            'system_prompt'   => sanitize_textarea_field($data['system_prompt'] ?? ''),
            'temperature'     => max(0.0, min(2.0, (float) ($data['temperature'] ?? 0.7))),
            'max_tokens'      => max(1, min(100000, (int) ($data['max_tokens'] ?? 1000))),
            'model'           => sanitize_text_field($data['model'] ?? ''),
            'model_type'      => in_array($data['model_type'] ?? '', $validModelTypes, true)
                ? $data['model_type']
                : 'llm',
            'category'        => sanitize_key($data['category'] ?? 'content'),
            'permission'      => sanitize_key($data['permission'] ?? 'edit_posts'),
            'conversational'  => !empty($data['conversational']) ? 1 : 0,
            'provider'        => sanitize_key($data['provider'] ?? 'infomaniak'),
        ];
    }
}
