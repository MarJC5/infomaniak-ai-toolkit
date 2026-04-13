<?php

/**
 * Example: Web Search Preset with Agent Tools
 *
 * Demonstrates how to create a preset that can search the web and fetch
 * page content using the built-in WebSearchTool and WebFetchTool.
 *
 * The AI model decides autonomously when to search, which results to
 * read in detail, and how to synthesize the information.
 *
 * No templates directory is needed — this preset uses inline prompts.
 *
 * Usage:
 *   $preset = new WebResearchPreset();
 *   $preset->registerAsAbility();
 *
 *   // Via REST API:
 *   // POST /wp-json/wp-abilities/v1/abilities/infomaniak/web-research/run
 *   // {"topic": "latest WordPress 6.8 features", "language": "fr"}
 *
 *   // Via PHP:
 *   // $result = $preset->execute(['topic' => 'latest WordPress 6.8 features']);
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Agent\Tools\WebFetchTool;
use WordPress\InfomaniakAiToolkit\Agent\Tools\WebSearchTool;
use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * A preset that researches a topic using web search.
 */
class WebResearchPreset extends BasePreset
{
    public function name(): string
    {
        return 'web-research';
    }

    public function label(): string
    {
        return __('Web Research', 'your-plugin');
    }

    public function description(): string
    {
        return __('Researches a topic using web search and provides a synthesis with sources.', 'your-plugin');
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
                'language' => [
                    'type' => 'string',
                    'description' => 'Language for the response (default: en).',
                ],
            ],
            'required' => ['topic'],
        ];
    }

    protected function templateName(): string
    {
        return '';
    }

    /**
     * Build the prompt directly without a template file.
     */
    protected function buildPromptText(array $data): string
    {
        $topic = $data['topic'] ?? '';
        $language = $data['language'] ?? 'en';

        return sprintf(
            "Research the following topic and provide a comprehensive summary:\n\n"
            . "Topic: %s\n\n"
            . "Instructions:\n"
            . "1. Use the web_search tool to find relevant and recent information.\n"
            . "2. Use the fetch_url tool to read the most relevant pages in detail.\n"
            . "3. Synthesize the information into a clear, well-structured summary.\n"
            . "4. Include sources (URLs) for key facts.\n"
            . "5. Respond in %s.",
            $topic,
            $language
        );
    }

    protected function buildSystemText(array $data): ?string
    {
        return 'You are a research assistant. Search the web methodically: '
            . 'start with a broad search, then fetch the most relevant pages for details. '
            . 'Cross-reference facts across sources. Always cite your sources.';
    }

    protected function temperature(): float
    {
        return 0.3;
    }

    protected function maxTokens(): int
    {
        return 2000;
    }

    protected function maxAgentIterations(): int
    {
        return 8;
    }

    /**
     * Provide web search and URL fetch tools to the agent.
     *
     * @return \WordPress\InfomaniakAiToolkit\Agent\Tool[]
     */
    protected function tools(): array
    {
        return [
            WebSearchTool::create(),
            WebFetchTool::create(),
        ];
    }

    protected function category(): string
    {
        return 'research';
    }

    protected function annotations(): array
    {
        return [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => false,
        ];
    }
}

/*
 * --------------------------------------------------------------------------
 * Registration in your-plugin.php:
 * --------------------------------------------------------------------------
 *
 * add_action('wp_abilities_api_init', function() {
 *     if (!class_exists(\WordPress\InfomaniakAiToolkit\Presets\BasePreset::class)) {
 *         return;
 *     }
 *     $preset = new \YourPlugin\Presets\WebResearchPreset();
 *     $preset->registerAsAbility();
 * });
 *
 * --------------------------------------------------------------------------
 * Custom search provider (optional):
 * --------------------------------------------------------------------------
 *
 * Replace DuckDuckGo with Brave Search:
 *
 * add_filter('infomaniak_ai_web_search_results', function($results, $query, $maxResults) {
 *     $response = wp_remote_get('https://api.search.brave.com/res/v1/web/search?' . http_build_query([
 *         'q' => $query,
 *         'count' => $maxResults,
 *     ]), [
 *         'headers' => [
 *             'X-Subscription-Token' => BRAVE_SEARCH_API_KEY,
 *             'Accept' => 'application/json',
 *         ],
 *     ]);
 *
 *     if (is_wp_error($response)) {
 *         return null; // Fall back to default.
 *     }
 *
 *     $data = json_decode(wp_remote_retrieve_body($response), true);
 *     $webResults = $data['web']['results'] ?? [];
 *
 *     return array_map(fn($r) => [
 *         'title' => $r['title'],
 *         'url' => $r['url'],
 *         'snippet' => $r['description'] ?? '',
 *     ], array_slice($webResults, 0, $maxResults));
 * }, 10, 3);
 */
