<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Usage;

/**
 * Value object representing a single usage log entry.
 *
 * @since 1.0.0
 */
class UsageRecord
{
    /** @var string */
    private $id;

    /** @var int */
    private $userId;

    /** @var string */
    private $providerId;

    /** @var string */
    private $modelId;

    /** @var string */
    private $modelName;

    /** @var string|null */
    private $presetName;

    /** @var int */
    private $promptTokens;

    /** @var int */
    private $completionTokens;

    /** @var int */
    private $totalTokens;

    /** @var string */
    private $capability;

    /** @var string */
    private $createdAt;

    private function __construct()
    {
    }

    /**
     * Creates a UsageRecord from a database row or array.
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
        $record->userId = (int) ($data['user_id'] ?? 0);
        $record->providerId = (string) ($data['provider_id'] ?? '');
        $record->modelId = (string) ($data['model_id'] ?? '');
        $record->modelName = (string) ($data['model_name'] ?? '');
        $record->presetName = isset($data['preset_name']) ? (string) $data['preset_name'] : null;
        $record->promptTokens = (int) ($data['prompt_tokens'] ?? 0);
        $record->completionTokens = (int) ($data['completion_tokens'] ?? 0);
        $record->totalTokens = (int) ($data['total_tokens'] ?? 0);
        $record->capability = (string) ($data['capability'] ?? '');
        $record->createdAt = (string) ($data['created_at'] ?? '');

        return $record;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function modelId(): string
    {
        return $this->modelId;
    }

    public function modelName(): string
    {
        return $this->modelName;
    }

    public function presetName(): ?string
    {
        return $this->presetName;
    }

    public function promptTokens(): int
    {
        return $this->promptTokens;
    }

    public function completionTokens(): int
    {
        return $this->completionTokens;
    }

    public function totalTokens(): int
    {
        return $this->totalTokens;
    }

    public function capability(): string
    {
        return $this->capability;
    }

    public function createdAt(): string
    {
        return $this->createdAt;
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
            'user_id' => $this->userId,
            'provider_id' => $this->providerId,
            'model_id' => $this->modelId,
            'model_name' => $this->modelName,
            'preset_name' => $this->presetName,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'capability' => $this->capability,
            'created_at' => $this->createdAt,
        ];
    }
}
