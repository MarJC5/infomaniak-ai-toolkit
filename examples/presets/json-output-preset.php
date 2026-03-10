<?php

/**
 * Example: JSON Output Preset
 *
 * A preset that returns structured data (JSON) instead of plain text.
 * Uses outputSchema() to enforce a JSON Schema on the AI response
 * and automatically decodes the result into a PHP array.
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * Generates SEO metadata from content as a structured JSON object.
 */
class SeoMetaPreset extends BasePreset
{
    public function name(): string
    {
        return 'seo-meta';
    }

    public function label(): string
    {
        return __('Generate SEO Metadata', 'your-plugin');
    }

    public function description(): string
    {
        return __('Generates SEO-optimized meta title, description, and keywords.', 'your-plugin');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'string',
                    'description' => 'The text content to analyze.',
                ],
                'language' => [
                    'type' => 'string',
                    'description' => 'Target language for the metadata.',
                    'default' => 'English',
                ],
            ],
            'required' => ['content'],
        ];
    }

    protected function templateName(): string
    {
        return 'seo-meta';
    }

    protected function systemTemplateName(): ?string
    {
        return 'seo-expert';
    }

    protected function templateData(array $input): array
    {
        return [
            'content' => $input['content'] ?? '',
            'language' => $input['language'] ?? 'English',
        ];
    }

    /**
     * Define a JSON Schema for the AI output.
     *
     * When this returns a non-null array, BasePreset will:
     * 1. Call as_json_response($schema) on the prompt builder
     * 2. Automatically json_decode() the result
     * 3. Return a PHP array instead of a string
     */
    protected function outputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'SEO meta title (max 60 characters).',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'SEO meta description (max 160 characters).',
                ],
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'List of relevant SEO keywords.',
                ],
            ],
            'required' => ['title', 'description', 'keywords'],
        ];
    }

    /**
     * For ability registration, expose the same schema as output.
     */
    protected function outputAbilitySchema(): array
    {
        return $this->outputSchema();
    }

    protected function temperature(): float
    {
        return 0.5;
    }

    protected function maxTokens(): int
    {
        return 500;
    }
}

/*
 * --------------------------------------------------------------------------
 * Usage -- the result is a PHP array, not a string:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\SeoMetaPreset();
 * $meta = $preset->execute(['content' => 'Your article...']);
 *
 * if (!is_wp_error($meta)) {
 *     echo $meta['title'];       // "Best Swiss Hosting in 2026"
 *     echo $meta['description']; // "Discover the top hosting..."
 *     print_r($meta['keywords']); // ["hosting", "swiss", ...]
 * }
 */
