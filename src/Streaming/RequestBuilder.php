<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Streaming;

use WordPress\InfomaniakAiToolkit\Provider\InfomaniakProvider;

/**
 * Builds HTTP request parameters for streaming API calls.
 *
 * Replicates the request-building logic from the library's
 * AbstractOpenAiCompatibleTextGenerationModel::prepareGenerateTextParams()
 * but adds `stream: true` for SSE support.
 *
 * @since 1.0.0
 */
class RequestBuilder
{
    /**
     * Builds the streaming request URL, body, and headers.
     *
     * @param array $options Configuration:
     *   - model: string (required) — model ID
     *   - messages: array (required) — OpenAI-format messages
     *   - temperature: float|null
     *   - max_tokens: int|null
     *   - system: string|null — system instruction
     *   - tools: array|null — tool/function declarations
     *   - custom_options: array — extra params (tool_choice, etc.)
     *
     * @return array{url: string, params: array, headers: array}
     * @throws \RuntimeException If product ID or API key is missing.
     */
    public static function build(array $options): array
    {
        $productId = InfomaniakProvider::getProductId();
        if (empty($productId)) {
            throw new \RuntimeException('Infomaniak AI product ID is not configured.');
        }

        $apiKey = InfomaniakProvider::getApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('Infomaniak AI API key is not configured.');
        }

        $url = 'https://api.infomaniak.com/2/ai/' . $productId . '/openai/v1/chat/completions';

        // Build messages array.
        $messages = $options['messages'] ?? [];

        // Prepend system instruction as a system message.
        $system = $options['system'] ?? null;
        if (!empty($system)) {
            array_unshift($messages, [
                'role' => 'system',
                'content' => [['type' => 'text', 'text' => $system]],
            ]);
        }

        $params = [
            'model' => $options['model'],
            'messages' => $messages,
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ];

        if (isset($options['temperature'])) {
            $params['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $params['max_tokens'] = $options['max_tokens'];
        }

        if (!empty($options['tools'])) {
            $params['tools'] = $options['tools'];
        }

        // Merge custom options (tool_choice, etc.).
        foreach ($options['custom_options'] ?? [] as $key => $value) {
            if (isset($params[$key])) {
                continue;
            }

            // Don't send tool_choice when no tools are defined — APIs reject it.
            if ($key === 'tool_choice' && empty($params['tools'])) {
                continue;
            }

            $params[$key] = $value;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'text/event-stream',
        ];

        return [
            'url' => $url,
            'params' => $params,
            'headers' => $headers,
        ];
    }

    /**
     * Converts a user prompt string into OpenAI-format messages.
     *
     * @param string  $prompt  The user prompt text.
     * @param array   $history Prior conversation messages in OpenAI format.
     * @return array OpenAI-format messages array.
     */
    public static function buildMessages(string $prompt, array $history = []): array
    {
        $messages = $history;

        if (!empty($prompt)) {
            $messages[] = [
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => $prompt]],
            ];
        }

        return $messages;
    }
}
