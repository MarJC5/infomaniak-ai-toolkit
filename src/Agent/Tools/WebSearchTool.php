<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent\Tools;

use WordPress\InfomaniakAiToolkit\Agent\Tool;

/**
 * Factory for a web search tool usable in the AgentLoop.
 *
 * Uses DuckDuckGo HTML search as the default backend. Developers can
 * replace the search implementation via the `infomaniak_ai_web_search_results` filter.
 *
 * Usage:
 *   protected function tools(): array {
 *       return [ WebSearchTool::create() ];
 *   }
 *
 * @since 1.0.0
 */
class WebSearchTool
{
    /**
     * Default maximum number of results to return.
     */
    private const DEFAULT_MAX_RESULTS = 5;

    /**
     * Maximum character length for each snippet.
     */
    private const MAX_SNIPPET_LENGTH = 300;

    /**
     * Creates a web search Tool instance.
     *
     * @since 1.0.0
     *
     * @param bool $requireConfirmation When true, the first call returns a preview
     *                                  and the model must call again with confirm_action=true
     *                                  to execute the search. Useful in chat contexts where
     *                                  the user should approve web searches.
     * @return Tool
     */
    public static function create(bool $requireConfirmation = false): Tool
    {
        $properties = [
            'query' => [
                'type' => 'string',
                'description' => 'The search query.',
            ],
            'max_results' => [
                'type' => 'integer',
                'description' => 'Maximum number of results to return (default 5, max 10).',
            ],
        ];

        if ($requireConfirmation) {
            $properties['confirm_action'] = [
                'type' => 'boolean',
                'description' => 'Set to true to execute the search. First call without this to preview and ask the user for permission.',
            ];
        }

        $handler = $requireConfirmation
            ? [self::class, 'handleWithConfirmation']
            : [self::class, 'handle'];

        return new Tool(
            'web_search',
            'Search the web for current information. Returns a list of results with titles, URLs, and text snippets.'
                . ($requireConfirmation ? ' First call without confirm_action to preview, then call with confirm_action=true after user approves.' : ''),
            [
                'type' => 'object',
                'properties' => $properties,
                'required' => ['query'],
            ],
            $handler
        );
    }

    /**
     * Handles the web search tool call with a two-step confirmation flow.
     *
     * @since 1.0.0
     *
     * @param array $args Tool arguments from the model.
     * @return array Preview, search results, or error.
     */
    public static function handleWithConfirmation(array $args): array
    {
        $query = sanitize_text_field($args['query'] ?? '');

        if (empty($query)) {
            return ['error' => 'A search query is required.'];
        }

        if (empty($args['confirm_action'])) {
            return [
                'status'  => 'pending_confirmation',
                'preview' => sprintf('Web search for: "%s"', $query),
                'message' => 'Present the preview to the user and include these EXACT lines in your response to show confirmation buttons:' . "\n"
                    . '<!-- ACTION:buttons -->' . "\n"
                    . '- **Yes** — Search the web' . "\n"
                    . '- **No** — Cancel' . "\n"
                    . '<!-- /ACTION -->' . "\n"
                    . 'Call again with confirm_action=true only after the user clicks Yes.',
            ];
        }

        return self::handle($args);
    }

    /**
     * Handles the web search tool call.
     *
     * @since 1.0.0
     *
     * @param array $args Tool arguments from the model.
     * @return array Search results or error.
     */
    public static function handle(array $args): array
    {
        $query = sanitize_text_field($args['query'] ?? '');

        if (empty($query)) {
            return ['error' => 'A search query is required.'];
        }

        $maxResults = min(max((int) ($args['max_results'] ?? self::DEFAULT_MAX_RESULTS), 1), 10);

        /**
         * Filters the web search results.
         *
         * Return a non-null array to bypass the default DuckDuckGo search
         * and provide results from a custom provider (Brave, Google, etc.).
         *
         * Expected format: array of ['title' => string, 'url' => string, 'snippet' => string]
         *
         * @since 1.0.0
         *
         * @param array|null $results    Null to use default, or array of results.
         * @param string     $query      The search query.
         * @param int        $maxResults Maximum number of results requested.
         */
        $results = apply_filters('infomaniak_ai_web_search_results', null, $query, $maxResults);

        if (is_array($results)) {
            return ['results' => array_slice($results, 0, $maxResults)];
        }

        $results = self::searchDuckDuckGo($query, $maxResults);

        if ($results === null) {
            return ['error' => 'Web search failed. The search engine may be temporarily unavailable.'];
        }

        return ['results' => $results];
    }

    /**
     * Searches DuckDuckGo HTML and parses the results.
     *
     * @since 1.0.0
     *
     * @param string $query      The search query.
     * @param int    $maxResults Maximum number of results.
     * @return array|null Parsed results or null on failure.
     */
    private static function searchDuckDuckGo(string $query, int $maxResults): ?array
    {
        $url = 'https://html.duckduckgo.com/html/?' . http_build_query(['q' => $query]);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return null;
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return null;
        }

        return self::parseDuckDuckGoHtml($html, $maxResults);
    }

    /**
     * Parses DuckDuckGo HTML search results page.
     *
     * @since 1.0.0
     *
     * @param string $html       The HTML content.
     * @param int    $maxResults Maximum number of results to extract.
     * @return array Parsed search results.
     */
    private static function parseDuckDuckGoHtml(string $html, int $maxResults): array
    {
        $results = [];

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);

        $xpath = new \DOMXPath($doc);

        // DuckDuckGo HTML results are in divs with class "result".
        $resultNodes = $xpath->query('//div[contains(@class, "result__body")]');

        if ($resultNodes === false || $resultNodes->length === 0) {
            return $results;
        }

        foreach ($resultNodes as $node) {
            if (count($results) >= $maxResults) {
                break;
            }

            // Extract title and URL from the result link.
            $linkNodes = $xpath->query('.//a[contains(@class, "result__a")]', $node);
            if ($linkNodes === false || $linkNodes->length === 0) {
                continue;
            }

            $linkNode = $linkNodes->item(0);
            $title = trim($linkNode->textContent);
            $href = $linkNode->getAttribute('href');

            // DuckDuckGo wraps URLs in a redirect; extract the actual URL.
            $url = self::extractDuckDuckGoUrl($href);
            if (empty($url) || empty($title)) {
                continue;
            }

            // Extract snippet.
            $snippetNodes = $xpath->query('.//a[contains(@class, "result__snippet")]', $node);
            $snippet = '';
            if ($snippetNodes !== false && $snippetNodes->length > 0) {
                $snippet = trim($snippetNodes->item(0)->textContent);
            }

            if (mb_strlen($snippet) > self::MAX_SNIPPET_LENGTH) {
                $snippet = mb_substr($snippet, 0, self::MAX_SNIPPET_LENGTH) . '...';
            }

            $results[] = [
                'title' => $title,
                'url' => $url,
                'snippet' => $snippet,
            ];
        }

        return $results;
    }

    /**
     * Extracts the actual URL from a DuckDuckGo redirect URL.
     *
     * DuckDuckGo HTML wraps result URLs in redirects like:
     * //duckduckgo.com/l/?uddg=https%3A%2F%2Fexample.com&rut=...
     *
     * @since 1.0.0
     *
     * @param string $href The href attribute from the result link.
     * @return string The extracted URL, or the original href if extraction fails.
     */
    private static function extractDuckDuckGoUrl(string $href): string
    {
        // Parse the redirect URL to extract the 'uddg' parameter.
        $parsed = parse_url($href);
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            if (isset($queryParams['uddg'])) {
                return $queryParams['uddg'];
            }
        }

        // If the href is already a direct URL, return it.
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        return '';
    }
}
