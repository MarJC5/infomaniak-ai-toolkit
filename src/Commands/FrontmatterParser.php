<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Commands;

/**
 * Minimal YAML-like frontmatter parser for markdown command files.
 *
 * Supports:
 * - Simple key-value pairs: `name: summarize`
 * - Numbers: `temperature: 0.7`, `max_tokens: 500`
 * - Booleans: `conversational: true`
 * - Multi-line strings with `|`: for system prompts
 * - No nested structures — not needed for command frontmatter
 *
 * No external dependencies (no Symfony YAML, no composer package).
 *
 * @since 1.0.0
 */
class FrontmatterParser
{
    /**
     * Parses a markdown file content into frontmatter and body.
     *
     * @since 1.0.0
     *
     * @param string $content Raw content of the .md file.
     * @return array{0: array<string, mixed>, 1: string} [frontmatter, body]
     */
    public static function parse(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);

        // Must start with `---`.
        if (!str_starts_with(trim($content), '---')) {
            return [[], trim($content)];
        }

        // Find the closing `---`.
        $firstDelimiter = strpos($content, '---');
        $secondDelimiter = strpos($content, "\n---", $firstDelimiter + 3);

        if ($secondDelimiter === false) {
            return [[], trim($content)];
        }

        $frontmatterRaw = substr($content, $firstDelimiter + 3, $secondDelimiter - $firstDelimiter - 3);
        $body = trim(substr($content, $secondDelimiter + 4));

        $frontmatter = self::parseFrontmatter(trim($frontmatterRaw));

        return [$frontmatter, $body];
    }

    /**
     * Parses the frontmatter block into an associative array.
     *
     * @since 1.0.0
     *
     * @param string $raw The raw frontmatter text (without `---` delimiters).
     * @return array<string, mixed>
     */
    private static function parseFrontmatter(string $raw): array
    {
        $result = [];
        $lines = explode("\n", $raw);
        $lineCount = count($lines);
        $i = 0;

        while ($i < $lineCount) {
            $line = $lines[$i];

            // Skip empty lines and comments.
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                $i++;
                continue;
            }

            // Must be a key: value pair.
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                $i++;
                continue;
            }

            $key = trim(substr($line, 0, $colonPos));
            $value = trim(substr($line, $colonPos + 1));

            // Multi-line string with `|`.
            if ($value === '|') {
                $multiLine = [];
                $i++;
                while ($i < $lineCount) {
                    $nextLine = $lines[$i];
                    // Multi-line block ends when we hit a non-indented, non-empty line.
                    if ($nextLine !== '' && $nextLine[0] !== ' ' && $nextLine[0] !== "\t") {
                        break;
                    }
                    // Preserve empty lines within the block.
                    $multiLine[] = $nextLine === '' ? '' : self::removeIndent($nextLine);
                    $i++;
                }
                $result[$key] = rtrim(implode("\n", $multiLine));
                continue;
            }

            $result[$key] = self::castValue($value);
            $i++;
        }

        return $result;
    }

    /**
     * Removes leading indentation from a multi-line block line.
     *
     * @since 1.0.0
     *
     * @param string $line The indented line.
     * @return string The line with leading whitespace removed.
     */
    private static function removeIndent(string $line): string
    {
        return ltrim($line, " \t");
    }

    /**
     * Casts a string value to the appropriate PHP type.
     *
     * @since 1.0.0
     *
     * @param string $value The raw string value.
     * @return string|int|float|bool|null The cast value.
     */
    private static function castValue(string $value): string|int|float|bool|null
    {
        // Empty value.
        if ($value === '') {
            return '';
        }

        $lower = strtolower($value);

        // Booleans (case-insensitive, YAML-compatible).
        if ($lower === 'true' || $lower === 'yes' || $lower === 'on') {
            return true;
        }
        if ($lower === 'false' || $lower === 'no' || $lower === 'off') {
            return false;
        }

        // Null.
        if ($lower === 'null' || $value === '~') {
            return null;
        }

        // Quoted strings — strip quotes.
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            return substr($value, 1, -1);
        }

        // Floats (must check before int — contains a dot).
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        // Integers.
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return $value;
    }
}
