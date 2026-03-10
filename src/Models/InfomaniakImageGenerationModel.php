<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;

/**
 * Class for an Infomaniak image generation model using the OpenAI-compatible Images API.
 *
 * Infomaniak's image generation endpoint follows the OpenAI Images API format.
 * The main difference is the URL structure:
 * /1/ai/{product_id}/openai/images/generations (prefix /1/, without /v1/)
 *
 * @since 1.0.0
 */
class InfomaniakImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * Maps the standard OpenAI path to Infomaniak's v1 API URL format.
     * e.g. 'images/generations' -> 'https://api.infomaniak.com/1/ai/{product_id}/openai/images/generations'
     *
     * @since 1.0.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $productId = InfomaniakProvider::getProductId();

        if (empty($productId)) {
            throw new RuntimeException(
                __(
                    'Infomaniak AI product ID is not configured. Set it via Settings > Infomaniak AI, the INFOMANIAK_AI_PRODUCT_ID constant, or the infomaniak_ai_product_id filter.',
                    'infomaniak-ai-toolkit'
                )
            );
        }

        // Map 'images/generations' -> /1/ai/{product_id}/openai/images/generations
        $fullPath = '/1/ai/' . $productId . '/openai/' . ltrim($path, '/');

        return new Request(
            $method,
            InfomaniakProvider::url($fullPath),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }

    /**
     * {@inheritDoc}
     *
     * The Infomaniak Images API returns a `created` timestamp instead of `id`.
     *
     * @since 1.0.0
     */
    protected function getResultId(array $responseData): string
    {
        return isset($responseData['created']) && is_int($responseData['created'])
            ? 'img-' . $responseData['created']
            : '';
    }
}
