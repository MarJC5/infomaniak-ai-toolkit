<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Usage;

/**
 * Fluent query builder for usage records.
 *
 * Example:
 *
 *     UsageTracker::query()
 *         ->forUser(42)
 *         ->from('2026-03-01')
 *         ->to('2026-03-31')
 *         ->sumTotalTokens();
 *
 * @since 1.0.0
 */
class UsageQuery
{
    /** @var array */
    private $wheres = [];

    /** @var array */
    private $values = [];

    /** @var int|null */
    private $limitNum = null;

    /** @var int|null */
    private $offsetNum = null;

    /**
     * Filters by WordPress user ID.
     *
     * @since 1.0.0
     *
     * @param int $userId
     * @return $this
     */
    public function forUser(int $userId): self
    {
        $this->wheres[] = 'user_id = %d';
        $this->values[] = $userId;
        return $this;
    }

    /**
     * Filters by provider ID.
     *
     * @since 1.0.0
     *
     * @param string $providerId
     * @return $this
     */
    public function forProvider(string $providerId): self
    {
        $this->wheres[] = 'provider_id = %s';
        $this->values[] = $providerId;
        return $this;
    }

    /**
     * Filters by model ID.
     *
     * @since 1.0.0
     *
     * @param string $modelId
     * @return $this
     */
    public function forModel(string $modelId): self
    {
        $this->wheres[] = 'model_id = %s';
        $this->values[] = $modelId;
        return $this;
    }

    /**
     * Filters by preset name.
     *
     * @since 1.0.0
     *
     * @param string $presetName
     * @return $this
     */
    public function forPreset(string $presetName): self
    {
        $this->wheres[] = 'preset_name = %s';
        $this->values[] = $presetName;
        return $this;
    }

    /**
     * Filters records created on or after the given date.
     *
     * @since 1.0.0
     *
     * @param string $date Date in Y-m-d or Y-m-d H:i:s format.
     * @return $this
     */
    public function from(string $date): self
    {
        $this->wheres[] = 'created_at >= %s';
        $this->values[] = $date;
        return $this;
    }

    /**
     * Filters records created on or before the given date.
     *
     * @since 1.0.0
     *
     * @param string $date Date in Y-m-d or Y-m-d H:i:s format.
     * @return $this
     */
    public function to(string $date): self
    {
        $this->wheres[] = 'created_at <= %s';
        $this->values[] = $date;
        return $this;
    }

    /**
     * Limits the number of results.
     *
     * @since 1.0.0
     *
     * @param int $limit
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limitNum = $limit;
        return $this;
    }

    /**
     * Offsets the results (for pagination).
     *
     * @since 1.0.0
     *
     * @param int $offset
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->offsetNum = $offset;
        return $this;
    }

    /**
     * Executes the query and returns an array of UsageRecord objects.
     *
     * @since 1.0.0
     *
     * @return UsageRecord[]
     */
    public function get(): array
    {
        global $wpdb;

        $table = UsageSchema::tableName();
        $sql = "SELECT * FROM {$table}";
        $sql .= $this->buildWhere();
        $sql .= ' ORDER BY created_at DESC';
        $sql .= $this->buildLimit();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            : $sql;

        $rows = $wpdb->get_results($query, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        if (!is_array($rows)) {
            return [];
        }

        return array_map([UsageRecord::class, 'fromArray'], $rows);
    }

    /**
     * Returns the count of matching records.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function count(): int
    {
        global $wpdb;

        $table = UsageSchema::tableName();
        $sql = "SELECT COUNT(*) FROM {$table}";
        $sql .= $this->buildWhere();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            : $sql;

        return (int) $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Returns the sum of a given column for matching records.
     *
     * @since 1.0.0
     *
     * @param string $column Column name (prompt_tokens, completion_tokens, total_tokens).
     * @return int
     */
    public function sum(string $column): int
    {
        $allowed = ['prompt_tokens', 'completion_tokens', 'total_tokens'];
        if (!in_array($column, $allowed, true)) {
            return 0;
        }

        global $wpdb;

        $table = UsageSchema::tableName();
        $sql = "SELECT COALESCE(SUM({$column}), 0) FROM {$table}";
        $sql .= $this->buildWhere();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            : $sql;

        return (int) $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Shortcut: returns the sum of total_tokens.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function sumTotalTokens(): int
    {
        return $this->sum('total_tokens');
    }

    /**
     * Builds the WHERE clause.
     *
     * @return string
     */
    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        return ' WHERE ' . implode(' AND ', $this->wheres);
    }

    /**
     * Builds the LIMIT/OFFSET clause.
     *
     * @return string
     */
    private function buildLimit(): string
    {
        $sql = '';

        if ($this->limitNum !== null) {
            $sql .= ' LIMIT ' . (int) $this->limitNum;
        }

        if ($this->offsetNum !== null) {
            if ($this->limitNum === null) {
                $sql .= ' LIMIT 18446744073709551615';
            }
            $sql .= ' OFFSET ' . (int) $this->offsetNum;
        }

        return $sql;
    }
}
