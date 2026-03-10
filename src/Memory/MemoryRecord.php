<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Memory;

/**
 * Value object representing a single conversation memory entry.
 *
 * @since 1.0.0
 */
class MemoryRecord
{
    /** @var string */
    private $id;

    /** @var string */
    private $conversationId;

    /** @var int */
    private $userId;

    /** @var string */
    private $presetName;

    /** @var string */
    private $role;

    /** @var string */
    private $content;

    /** @var int */
    private $tokenCount;

    /** @var array|null */
    private $metadata;

    /** @var string */
    private $createdAt;

    private function __construct()
    {
    }

    /**
     * Creates a MemoryRecord from a database row or array.
     *
     * @since 1.0.0
     *
     * @param array|object $data Row data.
     * @return self
     */
    public static function fromArray($data): self
    {
        $data = (array) $data;

        $record = new self();
        $record->id = (string) ($data['id'] ?? '');
        $record->conversationId = (string) ($data['conversation_id'] ?? '');
        $record->userId = (int) ($data['user_id'] ?? 0);
        $record->presetName = (string) ($data['preset_name'] ?? '');
        $record->role = (string) ($data['role'] ?? '');
        $record->content = (string) ($data['content'] ?? '');
        $record->tokenCount = (int) ($data['token_count'] ?? 0);
        $record->metadata = isset($data['metadata']) && $data['metadata'] !== null
            ? (is_string($data['metadata']) ? json_decode($data['metadata'], true) : (array) $data['metadata'])
            : null;
        $record->createdAt = (string) ($data['created_at'] ?? '');

        return $record;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function conversationId(): string
    {
        return $this->conversationId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function presetName(): string
    {
        return $this->presetName;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function tokenCount(): int
    {
        return $this->tokenCount;
    }

    public function metadata(): ?array
    {
        return $this->metadata;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isModel(): bool
    {
        return $this->role === 'model';
    }

    /**
     * Checks if this record is a compaction summary.
     *
     * @since 1.0.0
     */
    public function isSummary(): bool
    {
        return $this->role === 'summary';
    }

    /**
     * Returns the record as an associative array.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'preset_name' => $this->presetName,
            'role' => $this->role,
            'content' => $this->content,
            'token_count' => $this->tokenCount,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
        ];
    }
}
