<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Contracts\ProviderOperationsHandlerInterface;
use WordPress\AiClient\Providers\Contracts\ProviderWithOperationsHandlerInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\InfomaniakAiToolkit\Metadata\InfomaniakModelMetadataDirectory;
use WordPress\InfomaniakAiToolkit\Models\InfomaniakImageGenerationModel;
use WordPress\InfomaniakAiToolkit\Models\InfomaniakTextGenerationModel;
use WordPress\InfomaniakAiToolkit\Operations\InfomaniakOperationsHandler;

/**
 * Class for the Infomaniak AI Toolkit.
 *
 * Provides access to Infomaniak's AI services which offer open-source models
 * (Llama, Mistral, DeepSeek, Qwen) via an OpenAI-compatible API hosted in Switzerland.
 *
 * @since 1.0.0
 */
class InfomaniakProvider extends AbstractApiProvider implements ProviderWithOperationsHandlerInterface
{
    /**
     * @var InfomaniakOperationsHandler|null Cached operations handler instance.
     */
    private static ?InfomaniakOperationsHandler $operationsHandlerInstance = null;
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function baseUrl(): string
    {
        return 'https://api.infomaniak.com';
    }

    /**
     * Gets the Infomaniak AI product ID from configuration.
     *
     * Checks in order:
     * 1. The `infomaniak_ai_product_id` filter
     * 2. The `INFOMANIAK_AI_PRODUCT_ID` constant
     * 3. The `infomaniak_ai_product_id` WordPress option
     *
     * @since 1.0.0
     *
     * @return string The product ID, or empty string if not configured.
     */
    public static function getProductId(): string
    {
        /**
         * Filters the Infomaniak AI product ID.
         *
         * @since 1.0.0
         *
         * @param string|null $product_id The product ID, or null to use default sources.
         */
        $productId = apply_filters('infomaniak_ai_product_id', null);
        if ($productId !== null) {
            return self::sanitizeProductId((string) $productId);
        }

        if (defined('INFOMANIAK_AI_PRODUCT_ID')) {
            return self::sanitizeProductId((string) INFOMANIAK_AI_PRODUCT_ID);
        }

        return self::sanitizeProductId((string) get_option('infomaniak_ai_product_id', ''));
    }

    /**
     * Returns the API key for the Infomaniak AI service.
     *
     * Reads from the connectors setting, temporarily unmasking it.
     *
     * @since 1.0.0
     *
     * @return string The API key, or empty string if not configured.
     */
    public static function getApiKey(): string
    {
        try {
            remove_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
            $apiKey = (string) get_option('connectors_ai_infomaniak_api_key', '');
        } finally {
            add_filter('option_connectors_ai_infomaniak_api_key', '_wp_connectors_mask_api_key');
        }

        return $apiKey;
    }

    /**
     * Sanitizes a product ID to prevent path injection.
     *
     * Only allows alphanumeric characters, dashes, and underscores.
     * Returns an empty string for invalid values.
     *
     * @since 1.0.0
     *
     * @param string $productId The raw product ID.
     * @return string The sanitized product ID, or empty string if invalid.
     */
    private static function sanitizeProductId(string $productId): string
    {
        if ($productId === '') {
            return '';
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $productId)) {
            return '';
        }

        return $productId;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        $capabilities = $modelMetadata->getSupportedCapabilities();
        foreach ($capabilities as $capability) {
            if ($capability->isImageGeneration()) {
                return new InfomaniakImageGenerationModel($modelMetadata, $providerMetadata);
            }
            if ($capability->isTextGeneration()) {
                return new InfomaniakTextGenerationModel($modelMetadata, $providerMetadata);
            }
        }

        throw new RuntimeException(
            sprintf(
                /* translators: %s: comma-separated list of capability names */
                __('Unsupported model capabilities: %s', 'infomaniak-ai-toolkit'),
                implode(', ', array_map('strval', $capabilities))
            )
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $providerMetadataArgs = [
            'infomaniak',
            'Infomaniak',
            ProviderTypeEnum::cloud(),
            'https://manager.infomaniak.com/v3/ng/products/cloud/ai-tools',
            RequestAuthenticationMethod::apiKey(),
        ];
        // Provider description support was added in 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            if (function_exists('__')) {
                $providerMetadataArgs[] = __(
                    'Text and image generation with open-source models. Supports async batch operations.',
                    'infomaniak-ai-toolkit'
                );
            } else {
                $providerMetadataArgs[] = 'Text and image generation with open-source models. Supports async batch operations.';
            }
        }
        return new ProviderMetadata(...$providerMetadataArgs);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new InfomaniakModelMetadataDirectory();
    }

    /**
     * {@inheritDoc}
     *
     * Returns the operations handler for managing async batch operations.
     *
     * @since 1.0.0
     */
    public static function operationsHandler(): ProviderOperationsHandlerInterface
    {
        if (self::$operationsHandlerInstance === null) {
            self::$operationsHandlerInstance = new InfomaniakOperationsHandler();
        }
        return self::$operationsHandlerInstance;
    }
}
