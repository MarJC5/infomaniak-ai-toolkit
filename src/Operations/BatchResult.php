<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Operations;

use WordPress\AiClient\Operations\Enums\OperationStateEnum;

/**
 * Value object for an Infomaniak async batch result.
 *
 * Represents the response from GET /1/ai/{product_id}/results/{batch_id},
 * which includes the job status, result data, and download metadata.
 *
 * @since 1.0.0
 */
class BatchResult
{
    /**
     * Maps Infomaniak batch statuses to SDK operation states.
     *
     * @var array<string, string>
     */
    private const STATUS_MAP = [
        'pending'    => OperationStateEnum::STARTING,
        'processing' => OperationStateEnum::PROCESSING,
        'success'    => OperationStateEnum::SUCCEEDED,
        'failed'     => OperationStateEnum::FAILED,
        'cancelled'  => OperationStateEnum::CANCELED,
    ];

    /**
     * @var string The batch ID.
     */
    private string $batchId;

    /**
     * @var OperationStateEnum The current operation state.
     */
    private OperationStateEnum $state;

    /**
     * @var string|null The download URL, available when succeeded.
     */
    private ?string $url;

    /**
     * @var string|null The result file name.
     */
    private ?string $fileName;

    /**
     * @var int|null The result file size in bytes.
     */
    private ?int $fileSize;

    /**
     * @var mixed The inline result data (string or array), available when succeeded.
     */
    private $data;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string             $batchId  The batch ID.
     * @param OperationStateEnum $state    The operation state.
     * @param string|null        $url      The download URL.
     * @param string|null        $fileName The result file name.
     * @param int|null           $fileSize The result file size in bytes.
     * @param mixed              $data     The inline result data.
     */
    public function __construct(
        string $batchId,
        OperationStateEnum $state,
        ?string $url = null,
        ?string $fileName = null,
        ?int $fileSize = null,
        $data = null
    ) {
        $this->batchId = $batchId;
        $this->state = $state;
        $this->url = $url;
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->data = $data;
    }

    /**
     * Creates a BatchResult from the Infomaniak API response data.
     *
     * @since 1.0.0
     *
     * @param string $batchId The batch ID.
     * @param array  $data    The API response data (from the "data" envelope).
     * @return self
     */
    public static function fromApiResponse(string $batchId, array $data): self
    {
        $infomaniakStatus = $data['status'] ?? 'pending';
        $sdkState = self::STATUS_MAP[$infomaniakStatus] ?? OperationStateEnum::PROCESSING;

        return new self(
            $batchId,
            OperationStateEnum::from($sdkState),
            $data['url'] ?? null,
            $data['file_name'] ?? null,
            isset($data['file_size']) ? (int) $data['file_size'] : null,
            $data['data'] ?? null
        );
    }

    /**
     * Gets the batch ID.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function getBatchId(): string
    {
        return $this->batchId;
    }

    /**
     * Gets the operation state.
     *
     * @since 1.0.0
     *
     * @return OperationStateEnum
     */
    public function getState(): OperationStateEnum
    {
        return $this->state;
    }

    /**
     * Whether the batch has completed successfully.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isSucceeded(): bool
    {
        return $this->state->isSucceeded();
    }

    /**
     * Whether the batch is still in progress.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isInProgress(): bool
    {
        return $this->state->isStarting() || $this->state->isProcessing();
    }

    /**
     * Gets the download URL for the result file.
     *
     * Available only when the batch has succeeded.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function getDownloadUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Gets the result file name.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /**
     * Gets the result file size in bytes.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    /**
     * Gets the inline result data.
     *
     * Depending on the async task, this can be a string (e.g. transcription text)
     * or an array (e.g. structured data). Available only when succeeded.
     *
     * @since 1.0.0
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Whether inline result data is available.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * Whether a download URL is available.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function hasDownloadUrl(): bool
    {
        return $this->url !== null;
    }
}
