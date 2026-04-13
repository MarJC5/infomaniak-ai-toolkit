<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Streaming;

/**
 * HTTP streaming client for OpenAI-compatible SSE responses.
 *
 * Uses PHP's fopen() with an HTTP stream context to read
 * SSE chunks incrementally, bypassing the library's synchronous
 * HttpTransporter.
 *
 * @since 1.0.0
 */
class StreamingClient
{
    /**
     * Default stream read timeout in seconds.
     */
    private const DEFAULT_TIMEOUT = 120;

    /**
     * Streams a chat completion request and invokes the callback for each text chunk.
     *
     * @param string   $url       The API endpoint URL.
     * @param array    $params    The request body parameters (with stream: true).
     * @param array    $headers   HTTP headers (Authorization, Content-Type, etc.).
     * @param callable $onChunk   Callback invoked for each event:
     *                            ['type' => 'text', 'content' => string]
     *                            ['type' => 'tool_call', 'index' => int, 'id' => string, 'name' => string, 'arguments' => string]
     *                            ['type' => 'done', 'usage' => array]
     * @param float    $timeout   Stream read timeout in seconds.
     *
     * @return StreamingResult The complete result after the stream ends.
     *
     * @throws \RuntimeException If the stream cannot be opened or an HTTP error occurs.
     */
    public static function stream(
        string $url,
        array $params,
        array $headers,
        callable $onChunk,
        float $timeout = self::DEFAULT_TIMEOUT
    ): StreamingResult {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($params),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $stream = @fopen($url, 'r', false, $context);

        if ($stream === false) {
            throw new \RuntimeException('Failed to open streaming connection to API.');
        }

        // Check HTTP status from response headers.
        $meta = stream_get_meta_data($stream);
        $statusLine = $meta['wrapper_data'][0] ?? '';
        if (preg_match('/HTTP\/[\d.]+ (\d{3})/', $statusLine, $matches)) {
            $statusCode = (int) $matches[1];
            if ($statusCode >= 400) {
                $errorBody = stream_get_contents($stream);
                fclose($stream);
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? "HTTP {$statusCode} error";
                throw new \RuntimeException($errorMessage);
            }
        }

        stream_set_timeout($stream, (int) $timeout);

        $parser = new SseParser();
        $fullText = '';
        $finishReason = 'stop';
        $tokenUsage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        $toolCalls = [];

        try {
            while (!feof($stream) && !$parser->isDone()) {
                $line = fgets($stream);

                if ($line === false) {
                    // Check for timeout.
                    $info = stream_get_meta_data($stream);
                    if ($info['timed_out']) {
                        throw new \RuntimeException('Stream read timed out.');
                    }
                    break;
                }

                $chunk = $parser->feedLine($line);
                if ($chunk === null) {
                    continue;
                }

                // Extract usage from the final chunk (when stream_options.include_usage is set).
                if (isset($chunk['usage']) && is_array($chunk['usage'])) {
                    $tokenUsage = [
                        'prompt_tokens' => $chunk['usage']['prompt_tokens'] ?? 0,
                        'completion_tokens' => $chunk['usage']['completion_tokens'] ?? 0,
                        'total_tokens' => $chunk['usage']['total_tokens'] ?? 0,
                    ];
                }

                $choices = $chunk['choices'] ?? [];
                if (empty($choices)) {
                    continue;
                }

                $choice = $choices[0];
                $delta = $choice['delta'] ?? [];

                // Finish reason.
                if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                    $finishReason = $choice['finish_reason'];
                }

                // Text content delta.
                if (isset($delta['content']) && $delta['content'] !== '') {
                    $fullText .= $delta['content'];
                    $onChunk(['type' => 'text', 'content' => $delta['content']]);
                }

                // Tool call deltas.
                if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tc) {
                        $index = $tc['index'] ?? 0;

                        // Initialize tool call entry.
                        if (!isset($toolCalls[$index])) {
                            $toolCalls[$index] = [
                                'id' => $tc['id'] ?? '',
                                'type' => 'function',
                                'function' => [
                                    'name' => $tc['function']['name'] ?? '',
                                    'arguments' => '',
                                ],
                            ];

                            $onChunk([
                                'type' => 'tool_start',
                                'index' => $index,
                                'id' => $toolCalls[$index]['id'],
                                'name' => $toolCalls[$index]['function']['name'],
                            ]);
                        }

                        // Accumulate arguments.
                        if (isset($tc['function']['arguments'])) {
                            $toolCalls[$index]['function']['arguments'] .= $tc['function']['arguments'];
                        }
                    }
                }
            }
        } finally {
            fclose($stream);
        }

        $onChunk(['type' => 'done', 'usage' => $tokenUsage]);

        return new StreamingResult(
            $fullText,
            $tokenUsage,
            $finishReason,
            array_values($toolCalls)
        );
    }
}
