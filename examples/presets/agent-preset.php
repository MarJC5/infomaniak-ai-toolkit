<?php
/**
 * Example: Agent Preset with Tool Calling
 *
 * Demonstrates how to create a preset that uses function calling
 * to let the AI model autonomously search and read WordPress content.
 *
 * The model receives tool declarations and decides when to call them.
 * The AgentLoop handles the iteration: call → tool_calls → execute → respond → repeat.
 *
 * Usage:
 *   $preset = new ResearchPreset();
 *   $preset->registerAsAbility();
 *
 *   // Via REST API:
 *   // POST /wp-json/wp-abilities/v1/abilities/infomaniak/research/run
 *   // {"topic": "sustainable energy"}
 *
 *   // Via PHP:
 *   // $result = $preset->execute(['topic' => 'sustainable energy']);
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

namespace MyPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Agent\Tool;
use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

class ResearchPreset extends BasePreset
{
    public function name(): string
    {
        return 'research';
    }

    public function label(): string
    {
        return 'Research Topic';
    }

    public function description(): string
    {
        return 'Researches a topic using published WordPress content.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => [
                    'type' => 'string',
                    'description' => 'The topic to research.',
                ],
            ],
            'required' => ['topic'],
        ];
    }

    protected function templateName(): string
    {
        return 'research';
    }

    protected function systemTemplateName(): ?string
    {
        return 'content-editor';
    }

    protected function temperature(): float
    {
        return 0.3;
    }

    protected function maxTokens(): int
    {
        return 2000;
    }

    /**
     * Define the tools available to the agent.
     *
     * The model sees these as function declarations and decides
     * when to call them based on the prompt and conversation.
     *
     * @return Tool[]
     */
    protected function tools(): array
    {
        return [
            new Tool(
                'search_posts',
                'Search published WordPress posts by keyword. Returns titles and excerpts.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query keywords.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results (default 5).',
                        ],
                    ],
                    'required' => ['query'],
                ],
                function (array $args): array {
                    $posts = get_posts([
                        's' => sanitize_text_field($args['query']),
                        'posts_per_page' => min((int) ($args['limit'] ?? 5), 20),
                        'post_status' => 'publish',
                    ]);

                    return array_map(fn(\WP_Post $post): array => [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'excerpt' => wp_trim_words($post->post_content, 50),
                        'url' => get_permalink($post),
                    ], $posts);
                }
            ),

            new Tool(
                'get_post_content',
                'Get the full content of a WordPress post by its ID.',
                [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'The post ID.',
                        ],
                    ],
                    'required' => ['id'],
                ],
                function (array $args): array {
                    $post = get_post((int) $args['id']);

                    if (!$post || $post->post_status !== 'publish') {
                        return ['error' => 'Post not found or not published.'];
                    }

                    return [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'content' => wp_strip_all_tags($post->post_content),
                        'date' => $post->post_date,
                        'author' => get_the_author_meta('display_name', $post->post_author),
                        'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
                    ];
                }
            ),
        ];
    }

    /**
     * Limit agent iterations to 5 rounds for this preset.
     */
    protected function maxAgentIterations(): int
    {
        return 5;
    }
}
