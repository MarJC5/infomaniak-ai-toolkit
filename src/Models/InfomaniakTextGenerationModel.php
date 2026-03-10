<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Models;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;

/**
 * Class for an Infomaniak text generation model using the OpenAI-compatible Chat Completions API.
 *
 * Infomaniak's API follows the OpenAI Chat Completions format, so most of the heavy lifting
 * is handled by the abstract base class. The main difference is the URL structure which
 * includes a product ID: /2/ai/{product_id}/openai/v1/chat/completions
 *
 * @since 1.0.0
 */
class InfomaniakTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * Maps the standard OpenAI path to Infomaniak's v2 API URL format.
     * e.g. 'chat/completions' -> 'https://api.infomaniak.com/2/ai/{product_id}/openai/v1/chat/completions'
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

        // Map 'chat/completions' -> /2/ai/{product_id}/openai/v1/chat/completions
        $fullPath = '/2/ai/' . $productId . '/openai/v1/' . ltrim($path, '/');

        return new Request(
            $method,
            InfomaniakProvider::url($fullPath),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
