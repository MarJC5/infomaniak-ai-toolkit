<?php

/**
 * Example: Basic AI Preset
 *
 * The simplest possible preset -- summarize content with a PHP template.
 * Copy this file into your own plugin and adapt it.
 *
 * Required file structure in your plugin:
 *
 *   your-plugin/
 *   ├── your-plugin.php
 *   ├── src/
 *   │   └── Presets/
 *   │       └── SummarizePreset.php   <-- this class
 *   └── templates/
 *       └── presets/
 *           ├── summarize.php         <-- prompt template
 *           └── system/
 *               └── content-editor.php  <-- system prompt (optional)
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * A simple preset that summarizes text content.
 */
class SummarizePreset extends BasePreset
{
    public function name(): string
    {
        return 'summarize';
    }

    public function label(): string
    {
        return __('Summarize Content', 'your-plugin');
    }

    public function description(): string
    {
        return __('Generates a concise summary of the provided content.', 'your-plugin');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => [
                    'type' => 'string',
                    'description' => 'The text content to summarize.',
                ],
                'max_sentences' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of sentences.',
                    'default' => 3,
                ],
            ],
            'required' => ['content'],
        ];
    }

    // Points to templates/presets/summarize.php in your plugin.
    protected function templateName(): string
    {
        return 'summarize';
    }

    // Points to templates/presets/system/content-editor.php in your plugin.
    protected function systemTemplateName(): ?string
    {
        return 'content-editor';
    }

    // Set defaults for optional input fields.
    protected function templateData(array $input): array
    {
        return [
            'content' => $input['content'] ?? '',
            'max_sentences' => $input['max_sentences'] ?? 3,
        ];
    }

    protected function maxTokens(): int
    {
        return 500;
    }
}

/*
 * --------------------------------------------------------------------------
 * Prompt template: templates/presets/summarize.php
 * --------------------------------------------------------------------------
 *
 * Summarize the following content:
 *
 * <?= $content ?>
 *
 * Requirements:
 * - Maximum <?= (int) $max_sentences ?> sentences.
 * - Focus on the main points and key takeaways.
 *
 * --------------------------------------------------------------------------
 * System prompt: templates/presets/system/content-editor.php
 * --------------------------------------------------------------------------
 *
 * You are a professional content editor. Write clear, concise text.
 * Preserve the original meaning. Return only the requested output.
 *
 * --------------------------------------------------------------------------
 * Registration in your-plugin.php:
 * --------------------------------------------------------------------------
 *
 * add_action('wp_abilities_api_init', function() {
 *     if (!class_exists(\WordPress\InfomaniakAiToolkit\Presets\BasePreset::class)) {
 *         return;
 *     }
 *     $preset = new \YourPlugin\Presets\SummarizePreset();
 *     $preset->registerAsAbility();
 * });
 *
 * --------------------------------------------------------------------------
 * Usage in PHP:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\SummarizePreset();
 * $result = $preset->execute([
 *     'content' => 'Your long article text here...',
 *     'max_sentences' => 2,
 * ]);
 *
 * --------------------------------------------------------------------------
 * Usage via REST API:
 * --------------------------------------------------------------------------
 *
 * POST /wp-json/wp-abilities/v1/abilities/infomaniak/summarize/run
 * Content-Type: application/json
 *
 * { "content": "Your long article text here...", "max_sentences": 2 }
 */
