<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Operations;

use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Operations\Contracts\OperationInterface;
use WordPress\AiClient\Operations\DTO\GenerativeAiOperation;
use WordPress\AiClient\Providers\Contracts\ProviderOperationsHandlerInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;

/**
 * Handles async batch operations for Infomaniak AI.
 *
 * Infomaniak's async endpoints (e.g. audio transcription) return a batch_id.
 * This handler provides:
 * - Status polling via GET /1/ai/{product_id}/results/{batch_id}
 * - Full batch metadata retrieval via getBatchResult()
 * - Binary file download via downloadBatchResult()
 *
 * @since 1.0.0
 */
class InfomaniakOperationsHandler implements
    ProviderOperationsHandlerInterface,
    WithHttpTransporterInterface,
    WithRequestAuthenticationInterface
{
    use WithHttpTransporterTrait;
    use WithRequestAuthenticationTrait;

    /**
     * {@inheritDoc}
     *
     * Fetches the status of an async batch operation from the Infomaniak API.
     *
     * @since 1.0.0
     *
     * @param string $operationId The batch ID returned by an async endpoint.
     * @return OperationInterface The operation with its current state.
     * @throws InvalidArgumentException If the operation is not found or the response is invalid.
     */
    public function getOperation(string $operationId): OperationInterface
    {
        $batchResult = $this->getBatchResult($operationId);

        return new GenerativeAiOperation($operationId, $batchResult->getState());
    }

    /**
     * Gets the full batch result metadata for an async operation.
     *
     * Returns a BatchResult containing the status, inline data, download URL,
     * file name, and file size.
     *
     * @since 1.0.0
     *
     * @param string $batchId The batch ID returned by an async endpoint.
     * @return BatchResult The full batch result with metadata.
     * @throws InvalidArgumentException If the batch is not found or the response is invalid.
     */
    public function getBatchResult(string $batchId): BatchResult
    {
        $response = $this->sendAuthenticatedRequest(
            HttpMethodEnum::GET(),
            $this->buildResultsPath($batchId),
            ['Content-Type' => 'application/json']
        );

        if ($response->getStatusCode() === 404) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: %s: batch ID */
                    __('Batch "%s" not found.', 'infomaniak-ai-toolkit'),
                    $batchId
                )
            );
        }

        $data = $response->getData();

        if ($data === null) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: %s: batch ID */
                    __('Batch "%s" returned an invalid response.', 'infomaniak-ai-toolkit'),
                    $batchId
                )
            );
        }

        // The Infomaniak API wraps the job result in a "data" key.
        $jobResult = $data['data'] ?? $data;

        return BatchResult::fromApiResponse($batchId, $jobResult);
    }

    /**
     * Downloads the binary output of a completed async batch.
     *
     * Calls GET /1/ai/{product_id}/results/{batch_id}/download.
     * The batch must have succeeded before calling this method.
     *
     * @since 1.0.0
     *
     * @param string $batchId The batch ID.
     * @return string The raw binary content of the result file.
     * @throws InvalidArgumentException If the batch is not found.
     * @throws RuntimeException If the download fails or returns empty content.
     */
    public function downloadBatchResult(string $batchId): string
    {
        $response = $this->sendAuthenticatedRequest(
            HttpMethodEnum::GET(),
            $this->buildResultsPath($batchId) . '/download'
        );

        if ($response->getStatusCode() === 404) {
            throw new InvalidArgumentException(
                sprintf(
                    /* translators: %s: batch ID */
                    __('Batch "%s" not found or result not yet available.', 'infomaniak-ai-toolkit'),
                    $batchId
                )
            );
        }

        if (!$response->isSuccessful()) {
            throw new RuntimeException(
                sprintf(
                    /* translators: 1: batch ID, 2: HTTP status code */
                    __('Failed to download batch "%1$s" result. HTTP status: %2$d.', 'infomaniak-ai-toolkit'),
                    $batchId,
                    $response->getStatusCode()
                )
            );
        }

        $body = $response->getBody();

        if ($body === null || $body === '') {
            throw new RuntimeException(
                sprintf(
                    /* translators: %s: batch ID */
                    __('Batch "%s" download returned empty content.', 'infomaniak-ai-toolkit'),
                    $batchId
                )
            );
        }

        return $body;
    }

    /**
     * Builds the API path for the results endpoint.
     *
     * @since 1.0.0
     *
     * @param string $batchId The batch ID.
     * @return string The path: /1/ai/{product_id}/results/{batch_id}
     * @throws InvalidArgumentException If the product ID is not configured.
     */
    private function buildResultsPath(string $batchId): string
    {
        $productId = InfomaniakProvider::getProductId();

        if (empty($productId)) {
            throw new InvalidArgumentException(
                __(
                    'Infomaniak AI product ID is not configured. Set it via Settings > Infomaniak AI, the INFOMANIAK_AI_PRODUCT_ID constant, or the infomaniak_ai_product_id filter.',
                    'infomaniak-ai-toolkit'
                )
            );
        }

        return '/1/ai/' . $productId . '/results/' . $batchId;
    }

    /**
     * Sends an authenticated request to the Infomaniak API.
     *
     * @since 1.0.0
     *
     * @param HttpMethodEnum        $method  The HTTP method.
     * @param string                $path    The API path (appended to base URL).
     * @param array<string, string> $headers Optional request headers.
     * @return Response The HTTP response.
     */
    private function sendAuthenticatedRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = []
    ): Response {
        $request = new Request(
            $method,
            InfomaniakProvider::url($path),
            $headers
        );

        $request = $this->getRequestAuthentication()->authenticateRequest($request);

        return $this->getHttpTransporter()->send($request);
    }
}
