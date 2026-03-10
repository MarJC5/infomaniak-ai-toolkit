<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Metadata;

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;

/**
 * Class for the Infomaniak model metadata directory.
 *
 * Fetches available models from Infomaniak's models API at /1/ai/models,
 * which returns all model types (llm, image, embedding, stt, reranker)
 * with their status and metadata.
 *
 * @since 1.0.0
 */
class InfomaniakModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * {@inheritDoc}
     *
     * Creates requests pointing to Infomaniak's models endpoint.
     * The abstract class sends 'models' as the path, which we map to /1/ai/models.
     *
     * @since 1.0.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        // Map 'models' -> /1/ai/models (no product_id required)
        $fullPath = '/1/ai/' . ltrim($path, '/');

        return new Request(
            $method,
            InfomaniakProvider::url($fullPath),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * Parses Infomaniak's model list response from /1/ai/models.
     *
     * Response format: { result: "success", data: [{ name, type, description, info_status, ... }] }
     *
     * Only models with info_status "ready" are included.
     * Only types supported by the SDK are included: llm (textGeneration) and image (imageGeneration).
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        $responseData = $response->getData();

        $modelsData = [];
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            $modelsData = $responseData['data'];
        }

        if (empty($modelsData)) {
            throw ResponseException::fromMissingData('Infomaniak', 'data');
        }

        $llmCapabilities = [
            CapabilityEnum::textGeneration(),
            CapabilityEnum::chatHistory(),
        ];

        $llmOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::presencePenalty()),
            new SupportedOption(OptionEnum::frequencyPenalty()),
            new SupportedOption(
                OptionEnum::outputMimeType(),
                ['text/plain', 'application/json']
            ),
            new SupportedOption(OptionEnum::outputSchema()),
            new SupportedOption(OptionEnum::functionDeclarations()),
            new SupportedOption(OptionEnum::logprobs()),
            new SupportedOption(OptionEnum::topLogprobs()),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(
                OptionEnum::inputModalities(),
                [[ModalityEnum::text()]]
            ),
            new SupportedOption(
                OptionEnum::outputModalities(),
                [[ModalityEnum::text()]]
            ),
        ];

        $imageCapabilities = [
            CapabilityEnum::imageGeneration(),
        ];

        $imageOptions = [
            new SupportedOption(
                OptionEnum::inputModalities(),
                [[ModalityEnum::text()]]
            ),
            new SupportedOption(
                OptionEnum::outputModalities(),
                [[ModalityEnum::image()]]
            ),
            new SupportedOption(OptionEnum::candidateCount()),
            new SupportedOption(OptionEnum::outputMimeType(), ['image/png']),
            new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::inline()]),
            new SupportedOption(OptionEnum::outputMediaOrientation(), [
                MediaOrientationEnum::square(),
                MediaOrientationEnum::landscape(),
                MediaOrientationEnum::portrait(),
            ]),
            new SupportedOption(OptionEnum::outputMediaAspectRatio(), ['1:1', '3:2', '2:3']),
            new SupportedOption(OptionEnum::customOptions()),
        ];

        $models = [];
        foreach ($modelsData as $modelData) {
            if (!is_array($modelData)) {
                continue;
            }

            $modelId = $modelData['name'] ?? null;
            if (!$modelId) {
                continue;
            }

            // Only include models that are ready.
            $status = $modelData['info_status'] ?? null;
            if ($status !== 'ready') {
                continue;
            }

            $type = $modelData['type'] ?? null;

            if ($type === 'llm') {
                $capabilities = $llmCapabilities;
                $options = $llmOptions;
            } elseif ($type === 'image') {
                $capabilities = $imageCapabilities;
                $options = $imageOptions;
            } else {
                // Skip unsupported types (embedding, stt, reranker).
                continue;
            }

            $displayName = $modelData['description'] ?? (string) $modelId;

            $models[] = new ModelMetadata(
                (string) $modelId,
                (string) $displayName,
                $capabilities,
                $options
            );
        }

        if (!empty($models)) {
            usort($models, [$this, 'modelSortCallback']);
        }

        return $models;
    }

    /**
     * Callback for sorting models by name.
     *
     * Prefers well-known model families in this order:
     * 1. Llama models
     * 2. Mistral/Mixtral models
     * 3. DeepSeek models
     * 4. Others alphabetically
     *
     * @since 1.0.0
     *
     * @param ModelMetadata $a First model.
     * @param ModelMetadata $b Second model.
     * @return int Comparison result.
     */
    protected function modelSortCallback(ModelMetadata $a, ModelMetadata $b): int
    {
        $aId = strtolower($a->getId());
        $bId = strtolower($b->getId());

        $aWeight = $this->getModelFamilyWeight($aId);
        $bWeight = $this->getModelFamilyWeight($bId);

        if ($aWeight !== $bWeight) {
            return $aWeight - $bWeight;
        }

        return strcmp($aId, $bId);
    }

    /**
     * Returns a sort weight for a model based on its family.
     *
     * @since 1.0.0
     *
     * @param string $modelId The lowercase model ID.
     * @return int The sort weight (lower = higher priority).
     */
    private function getModelFamilyWeight(string $modelId): int
    {
        if (str_contains($modelId, 'llama')) {
            return 0;
        }
        if (str_contains($modelId, 'mistral') || str_contains($modelId, 'mixtral')) {
            return 1;
        }
        if (str_contains($modelId, 'deepseek')) {
            return 2;
        }
        return 3;
    }
}
