<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

/**
 * Fluent query builder for conversation memory records.
 *
 * Example:
 *
 *     MemoryQuery::new()
 *         ->forConversation('abc-123')
 *         ->forUser(42)
 *         ->recent(10)
 *         ->get();
 *
 * @since 1.0.0
 */
class MemoryQuery
{
    /** @var string[] */
    private array $wheres = [];

    /** @var array */
    private array $values = [];

    /** @var int|null */
    private ?int $limitNum = null;

    /** @var int|null */
    private ?int $offsetNum = null;

    /** @var string */
    private string $orderDirection = 'DESC';

    /**
     * Named constructor for fluent API.
     *
     * @since 1.0.0
     *
     * @return self
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Filters by conversation ID.
     *
     * @since 1.0.0
     */
    public function forConversation(string $conversationId): self
    {
        $this->wheres[] = 'conversation_id = %s';
        $this->values[] = $conversationId;
        return $this;
    }

    /**
     * Filters by user ID.
     *
     * @since 1.0.0
     */
    public function forUser(int $userId): self
    {
        $this->wheres[] = 'user_id = %d';
        $this->values[] = $userId;
        return $this;
    }

    /**
     * Filters by preset name.
     *
     * @since 1.0.0
     */
    public function forPreset(string $presetName): self
    {
        $this->wheres[] = 'preset_name = %s';
        $this->values[] = $presetName;
        return $this;
    }

    /**
     * Filters by message role ('user' or 'model').
     *
     * @since 1.0.0
     */
    public function forRole(string $role): self
    {
        $this->wheres[] = 'role = %s';
        $this->values[] = $role;
        return $this;
    }

    /**
     * Excludes a specific role from results.
     *
     * @since 1.0.0
     */
    public function excludeRole(string $role): self
    {
        $this->wheres[] = 'role != %s';
        $this->values[] = $role;
        return $this;
    }

    /**
     * Filters messages created on or after the given date.
     *
     * @since 1.0.0
     */
    public function from(string $date): self
    {
        $this->wheres[] = 'created_at >= %s';
        $this->values[] = $date;
        return $this;
    }

    /**
     * Filters messages created on or before the given date.
     *
     * @since 1.0.0
     */
    public function to(string $date): self
    {
        $this->wheres[] = 'created_at <= %s';
        $this->values[] = $date;
        return $this;
    }

    /**
     * Shortcut: limit to the N most recent messages.
     *
     * @since 1.0.0
     */
    public function recent(int $count): self
    {
        $this->limitNum = $count;
        $this->orderDirection = 'DESC';
        return $this;
    }

    /**
     * Sets the maximum number of results.
     *
     * @since 1.0.0
     */
    public function limit(int $limit): self
    {
        $this->limitNum = $limit;
        return $this;
    }

    /**
     * Sets the offset for pagination.
     *
     * @since 1.0.0
     */
    public function offset(int $offset): self
    {
        $this->offsetNum = $offset;
        return $this;
    }

    /**
     * Orders results oldest first (ASC).
     *
     * @since 1.0.0
     */
    public function oldestFirst(): self
    {
        $this->orderDirection = 'ASC';
        return $this;
    }

    /**
     * Executes the query and returns MemoryRecord[].
     *
     * @since 1.0.0
     *
     * @return MemoryRecord[]
     */
    public function get(): array
    {
        global $wpdb;

        $table = MemorySchema::tableName();
        $sql = "SELECT * FROM {$table}";
        $sql .= $this->buildWhere();
        $sql .= " ORDER BY created_at {$this->orderDirection}, id {$this->orderDirection}";
        $sql .= $this->buildLimit();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values)
            : $sql;

        $rows = $wpdb->get_results($query, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([MemoryRecord::class, 'fromArray'], $rows);
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

        $table = MemorySchema::tableName();
        $sql = "SELECT COUNT(*) FROM {$table}";
        $sql .= $this->buildWhere();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values)
            : $sql;

        return (int) $wpdb->get_var($query);
    }

    /**
     * Returns the sum of token_count for matching records.
     *
     * Uses a single SQL SUM query for performance.
     *
     * @since 1.0.0
     *
     * @return int
     */
    public function sumTokens(): int
    {
        global $wpdb;

        $table = MemorySchema::tableName();
        $sql = "SELECT COALESCE(SUM(token_count), 0) FROM {$table}";
        $sql .= $this->buildWhere();

        $query = !empty($this->values)
            ? $wpdb->prepare($sql, $this->values) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            : $sql;

        return (int) $wpdb->get_var($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Deletes records by their IDs.
     *
     * @since 1.0.0
     *
     * @param string[] $ids Record IDs to delete.
     * @return int Number of deleted rows.
     */
    public function deleteByIds(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;

        $table = MemorySchema::tableName();
        $placeholders = implode(',', array_fill(0, count($ids), '%s'));
        $sql = "DELETE FROM {$table} WHERE id IN ({$placeholders})";

        $query = $wpdb->prepare($sql, $ids); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return (int) $wpdb->query($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Deletes all matching records.
     *
     * Requires at least one WHERE condition for safety.
     *
     * @since 1.0.0
     *
     * @return int Number of deleted rows.
     */
    public function delete(): int
    {
        global $wpdb;

        if (empty($this->wheres)) {
            return 0;
        }

        $table = MemorySchema::tableName();
        $sql = "DELETE FROM {$table}";
        $sql .= $this->buildWhere();

        $query = $wpdb->prepare($sql, $this->values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return (int) $wpdb->query($query); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Builds the WHERE clause.
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
