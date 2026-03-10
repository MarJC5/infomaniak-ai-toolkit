<?php

/**
 * Example: Post-Aware Preset
 *
 * A preset that takes a post_id as input and fetches the post data
 * internally. Demonstrates how to use templateData() to enrich
 * input before rendering the template.
 *
 * Also shows how to override execute() for custom validation
 * before calling the parent.
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * Generates an excerpt for a WordPress post.
 */
class ExcerptPreset extends BasePreset
{
    public function name(): string
    {
        return 'excerpt';
    }

    public function label(): string
    {
        return __('Generate Excerpt', 'your-plugin');
    }

    public function description(): string
    {
        return __('Generates a concise excerpt for a WordPress post.', 'your-plugin');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'post_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the WordPress post.',
                ],
            ],
            'required' => ['post_id'],
        ];
    }

    protected function templateName(): string
    {
        return 'excerpt';
    }

    protected function systemTemplateName(): ?string
    {
        return 'content-editor';
    }

    /**
     * Transform raw input into template variables.
     *
     * The template receives $title and $content instead of $post_id.
     * This keeps templates focused on presentation, not data fetching.
     */
    protected function templateData(array $input): array
    {
        $post = get_post((int) ($input['post_id'] ?? 0));

        return [
            'title' => $post ? $post->post_title : '',
            'content' => $post ? wp_strip_all_tags($post->post_content) : '',
        ];
    }

    /**
     * Override execute() for custom validation.
     *
     * Check that the post exists and has content before calling the AI.
     * Always call parent::execute() at the end for the actual generation.
     */
    public function execute(array $input)
    {
        $post = get_post((int) ($input['post_id'] ?? 0));

        if (!$post) {
            return new \WP_Error('invalid_post', __('Post not found.', 'your-plugin'));
        }

        if (empty(trim(wp_strip_all_tags($post->post_content)))) {
            return new \WP_Error('empty_content', __('Post has no content.', 'your-plugin'));
        }

        return parent::execute($input);
    }

    protected function maxTokens(): int
    {
        return 200;
    }
}

/*
 * --------------------------------------------------------------------------
 * Template: templates/presets/excerpt.php
 * --------------------------------------------------------------------------
 *
 * Write a concise excerpt for the following article:
 *
 * Title: <?= esc_html($title) ?>
 *
 * <?= $content ?>
 *
 * Requirements:
 * - 1 to 2 sentences maximum.
 * - Capture the essence of the article.
 * - Be specific and engaging.
 *
 * --------------------------------------------------------------------------
 * Usage:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\ExcerptPreset();
 * $excerpt = $preset->execute(['post_id' => 42]);
 *
 * if (!is_wp_error($excerpt)) {
 *     wp_update_post(['ID' => 42, 'post_excerpt' => $excerpt]);
 * }
 */
