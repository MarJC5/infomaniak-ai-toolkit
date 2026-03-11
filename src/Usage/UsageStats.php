<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Usage;

/**
 * Aggregated usage statistics for the admin dashboard.
 *
 * Runs GROUP BY queries against the usage table to produce
 * daily totals, top models, and top presets breakdowns.
 *
 * @since 1.1.0
 */
class UsageStats
{
    /**
     * Returns daily token totals for the last N days (zero-filled).
     *
     * Each entry contains 'date', 'total_tokens', and 'request_count'.
     * Days without usage are filled with zeros to ensure a consistent
     * number of data points for sparkline rendering.
     *
     * @since 1.1.0
     *
     * @param int $days Number of days to look back.
     * @return array<int, array{date: string, total_tokens: int, request_count: int}>
     */
    public static function dailyTotals(int $days = 30): array
    {
        global $wpdb;

        $table = UsageSchema::tableName();
        $from  = gmdate('Y-m-d', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) AS date,
                    SUM(total_tokens) AS total_tokens,
                    COUNT(*) AS request_count
             FROM {$table}
             WHERE created_at >= %s
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $from . ' 00:00:00'
        ), ARRAY_A);

        // Index results by date for quick lookup.
        $indexed = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $indexed[$row['date']] = [
                    'date'          => $row['date'],
                    'total_tokens'  => (int) $row['total_tokens'],
                    'request_count' => (int) $row['request_count'],
                ];
            }
        }

        // Zero-fill gaps to get exactly $days data points.
        $filled = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', strtotime("-{$i} days"));
            $filled[] = $indexed[$date] ?? [
                'date'          => $date,
                'total_tokens'  => 0,
                'request_count' => 0,
            ];
        }

        return $filled;
    }

    /**
     * Returns total tokens for a date range.
     *
     * @since 1.1.0
     *
     * @param string $from Start date (Y-m-d).
     * @param string $to   End date (Y-m-d).
     * @return int
     */
    public static function totalTokens(string $from, string $to): int
    {
        global $wpdb;

        $table = UsageSchema::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_tokens), 0) FROM {$table}
             WHERE created_at >= %s AND created_at <= %s",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ));
    }

    /**
     * Returns total request count for a date range.
     *
     * @since 1.1.0
     *
     * @param string $from Start date (Y-m-d).
     * @param string $to   End date (Y-m-d).
     * @return int
     */
    public static function totalRequests(string $from, string $to): int
    {
        global $wpdb;

        $table = UsageSchema::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE created_at >= %s AND created_at <= %s",
            $from . ' 00:00:00',
            $to . ' 23:59:59'
        ));
    }

    /**
     * Returns top models by total tokens consumed.
     *
     * @since 1.1.0
     *
     * @param int    $limit Max models to return.
     * @param string $from  Start date (Y-m-d).
     * @param string $to    End date (Y-m-d).
     * @return array<int, array{provider_id: string, model_name: string, total_tokens: int, request_count: int}>
     */
    public static function topModels(int $limit = 5, string $from = '', string $to = ''): array
    {
        global $wpdb;

        $table  = UsageSchema::tableName();
        $wheres = [];
        $values = [];

        if ($from !== '') {
            $wheres[] = 'created_at >= %s';
            $values[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $wheres[] = 'created_at <= %s';
            $values[] = $to . ' 23:59:59';
        }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';

        $sql = "SELECT provider_id,
                       model_name,
                       SUM(total_tokens) AS total_tokens,
                       COUNT(*) AS request_count
                FROM {$table}
                {$where}
                GROUP BY provider_id, model_name
                ORDER BY total_tokens DESC
                LIMIT %d";

        $values[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row) => [
            'provider_id'   => $row['provider_id'],
            'model_name'    => $row['model_name'],
            'total_tokens'  => (int) $row['total_tokens'],
            'request_count' => (int) $row['request_count'],
        ], $rows);
    }

    /**
     * Returns top presets by total tokens consumed.
     *
     * Excludes direct API calls (NULL preset_name).
     *
     * @since 1.1.0
     *
     * @param int    $limit Max presets to return.
     * @param string $from  Start date (Y-m-d).
     * @param string $to    End date (Y-m-d).
     * @return array<int, array{preset_name: string, total_tokens: int, request_count: int}>
     */
    public static function topPresets(int $limit = 5, string $from = '', string $to = ''): array
    {
        global $wpdb;

        $table  = UsageSchema::tableName();
        $wheres = ['preset_name IS NOT NULL'];
        $values = [];

        if ($from !== '') {
            $wheres[] = 'created_at >= %s';
            $values[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $wheres[] = 'created_at <= %s';
            $values[] = $to . ' 23:59:59';
        }

        $where = 'WHERE ' . implode(' AND ', $wheres);

        $sql = "SELECT preset_name,
                       SUM(total_tokens) AS total_tokens,
                       COUNT(*) AS request_count
                FROM {$table}
                {$where}
                GROUP BY preset_name
                ORDER BY total_tokens DESC
                LIMIT %d";

        $values[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row) => [
            'preset_name'   => $row['preset_name'],
            'total_tokens'  => (int) $row['total_tokens'],
            'request_count' => (int) $row['request_count'],
        ], $rows);
    }

    /**
     * Returns tokens used today (UTC).
     *
     * @since 1.1.0
     *
     * @return int
     */
    public static function tokensToday(): int
    {
        $today = gmdate('Y-m-d');
        return self::totalTokens($today, $today);
    }

    /**
     * Returns request count for today (UTC).
     *
     * @since 1.1.0
     *
     * @return int
     */
    public static function requestsToday(): int
    {
        $today = gmdate('Y-m-d');
        return self::totalRequests($today, $today);
    }

    /**
     * Checks whether any usage data exists.
     *
     * @since 1.1.0
     *
     * @return bool
     */
    public static function hasData(): bool
    {
        global $wpdb;

        $table = UsageSchema::tableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT EXISTS(SELECT 1 FROM {$table} LIMIT 1)") === 1;
    }
}
