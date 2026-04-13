<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent\Tools;

use WordPress\InfomaniakAiToolkit\Agent\Tool;

/**
 * Factory for a URL content fetching tool usable in the AgentLoop.
 *
 * Fetches a web page and extracts its text content, stripping HTML tags.
 * Useful in combination with WebSearchTool to read full page content
 * from search results.
 *
 * Usage:
 *   protected function tools(): array {
 *       return [ WebSearchTool::create(), WebFetchTool::create() ];
 *   }
 *
 * @since 1.0.0
 */
class WebFetchTool
{
    /**
     * Maximum content length in characters returned to the model.
     */
    private const MAX_CONTENT_LENGTH = 4000;

    /**
     * Maximum response body size to download (1 MB).
     */
    private const MAX_DOWNLOAD_SIZE = 1048576;

    /**
     * Creates a URL fetch Tool instance.
     *
     * @since 1.0.0
     *
     * @return Tool
     */
    public static function create(): Tool
    {
        return new Tool(
            'fetch_url',
            'Fetch and extract the text content of a web page. Use this after web_search to read the full content of a result.',
            [
                'type' => 'object',
                'properties' => [
                    'url' => [
                        'type' => 'string',
                        'description' => 'The URL of the web page to fetch.',
                    ],
                ],
                'required' => ['url'],
            ],
            [self::class, 'handle']
        );
    }

    /**
     * Handles the fetch URL tool call.
     *
     * @since 1.0.0
     *
     * @param array $args Tool arguments from the model.
     * @return array Extracted content or error.
     */
    public static function handle(array $args): array
    {
        $url = esc_url_raw($args['url'] ?? '');

        if (empty($url)) {
            return ['error' => 'A valid URL is required.'];
        }

        // Only allow HTTP(S) URLs.
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return ['error' => 'Only HTTP and HTTPS URLs are supported.'];
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            return ['error' => 'Failed to fetch URL: ' . $response->get_error_message()];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            return ['error' => sprintf('HTTP %d error when fetching URL.', $statusCode)];
        }

        $contentType = wp_remote_retrieve_header($response, 'content-type');
        if (!empty($contentType) && !str_contains($contentType, 'text/') && !str_contains($contentType, 'html') && !str_contains($contentType, 'xml')) {
            return ['error' => 'URL does not point to a text/HTML document.'];
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return ['error' => 'The page returned empty content.'];
        }

        // Limit body size before processing.
        if (strlen($body) > self::MAX_DOWNLOAD_SIZE) {
            $body = substr($body, 0, self::MAX_DOWNLOAD_SIZE);
        }

        $text = self::extractText($body);

        /**
         * Filters the extracted text content from a fetched URL.
         *
         * @since 1.0.0
         *
         * @param string $text The extracted text content.
         * @param string $url  The URL that was fetched.
         * @param string $body The raw HTML body.
         */
        $text = apply_filters('infomaniak_ai_web_fetch_content', $text, $url, $body);

        if (empty($text)) {
            return ['error' => 'Could not extract meaningful text from the page.'];
        }

        return [
            'url' => $url,
            'content' => $text,
        ];
    }

    /**
     * Extracts readable text from HTML content.
     *
     * Removes scripts, styles, navigation, and other non-content elements,
     * then strips remaining tags and normalizes whitespace.
     *
     * @since 1.0.0
     *
     * @param string $html The raw HTML content.
     * @return string The extracted plain text, truncated to MAX_CONTENT_LENGTH.
     */
    private static function extractText(string $html): string
    {
        // Remove script and style elements entirely.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html);

        // Remove nav, header, footer, aside elements (common non-content).
        $html = preg_replace('/<(nav|header|footer|aside)\b[^>]*>.*?<\/\1>/is', '', $html);

        // Try to extract main content area if available.
        $mainContent = '';
        if (preg_match('/<(main|article)\b[^>]*>(.*?)<\/\1>/is', $html, $matches)) {
            $mainContent = $matches[2];
        }

        $source = !empty($mainContent) ? $mainContent : $html;

        // Convert block elements to newlines for readability.
        $source = preg_replace('/<\/(p|div|h[1-6]|li|tr|br\s*\/?)>/i', "\n", $source);
        $source = preg_replace('/<br\s*\/?>/i', "\n", $source);

        // Strip remaining HTML tags.
        $text = wp_strip_all_tags($source);

        // Decode HTML entities.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace: collapse spaces on each line, collapse blank lines.
        $text = preg_replace('/[^\S\n]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        // Truncate to maximum length.
        if (mb_strlen($text) > self::MAX_CONTENT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_CONTENT_LENGTH) . "\n\n[Content truncated]";
        }

        return $text;
    }
}
