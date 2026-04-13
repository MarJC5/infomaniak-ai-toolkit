<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Streaming;

/**
 * Stateful parser for Server-Sent Events (SSE) from OpenAI-compatible APIs.
 *
 * Handles the `data: {...}` line format and detects the `[DONE]` marker.
 *
 * @since 1.0.0
 */
class SseParser
{
    private bool $done = false;

    /**
     * Feeds a single line from the SSE stream.
     *
     * @param string $line Raw line from the stream (may include trailing newline).
     * @return array|null Parsed JSON data from the chunk, or null if not a data line.
     */
    public function feedLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        if (!str_starts_with($line, 'data: ')) {
            return null;
        }

        $data = substr($line, 6);

        if ($data === '[DONE]') {
            $this->done = true;
            return null;
        }

        $decoded = json_decode($data, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Whether the stream has received the [DONE] marker.
     */
    public function isDone(): bool
    {
        return $this->done;
    }
}
