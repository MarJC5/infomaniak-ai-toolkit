<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\Commands;

use WordPress\InfomaniakAiProvider\Presets\BasePreset;

/**
 * A preset configured from a markdown file with YAML frontmatter.
 *
 * Allows end users and site admins to create AI commands without writing PHP.
 * A markdown command file consists of YAML-like frontmatter (configuration)
 * and a body (prompt template with {{variable}} interpolation).
 *
 * Example file (summarize.md):
 *
 *     ---
 *     description: Generates a concise summary.
 *     temperature: 0.5
 *     system: You are a professional editor.
 *     ---
 *     Summarize the following content:
 *
 *     {{content}}
 *
 * The command name is derived from the filename (without .md extension).
 * Variables are auto-detected from {{variable}} patterns in the template
 * and used to generate the input JSON Schema.
 *
 * @since 1.0.0
 */
class MarkdownCommand extends BasePreset
{
    /**
     * Parsed frontmatter configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * The prompt template body (with {{variable}} placeholders).
     *
     * @var string
     */
    private string $promptTemplate;

    /**
     * @param array<string, mixed> $config         Parsed frontmatter key-value pairs.
     * @param string               $promptTemplate The body of the markdown file.
     */
    public function __construct(array $config, string $promptTemplate)
    {
        $this->config = $config;
        $this->promptTemplate = $promptTemplate;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function name(): string
    {
        return (string) ($this->config['name'] ?? '');
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function label(): string
    {
        return (string) ($this->config['label'] ?? ucwords(str_replace('-', ' ', $this->name())));
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function description(): string
    {
        return (string) ($this->config['description'] ?? '');
    }

    /**
     * {@inheritDoc}
     *
     * Auto-detects {{variable}} patterns in the prompt template
     * and generates a JSON Schema with all variables as required strings.
     *
     * @since 1.0.0
     */
    public function inputSchema(): array
    {
        preg_match_all('/\{\{\s*(\w+)\s*\}\}/', $this->promptTemplate, $matches);

        $variables = array_unique($matches[1]);
        $properties = [];

        foreach ($variables as $var) {
            $properties[$var] = [
                'type' => 'string',
                'description' => $var,
            ];
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($properties)) {
            $schema['required'] = array_values(array_keys($properties));
        }

        // Add conversation_id for conversational commands.
        if ($this->conversational()) {
            $schema['properties'] = array_merge(
                $schema['properties'],
                $this->conversationalInputProperties()
            );
        }

        return $schema;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function temperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.7);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function maxTokens(): int
    {
        return (int) ($this->config['max_tokens'] ?? 1000);
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function modelPreference(): ?string
    {
        return $this->config['model'] ?? parent::modelPreference();
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public function modelType(): string
    {
        return (string) ($this->config['model_type'] ?? 'llm');
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function category(): string
    {
        return (string) ($this->config['category'] ?? 'content');
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function permission(): string
    {
        return (string) ($this->config['permission'] ?? 'edit_posts');
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function provider(): string
    {
        return (string) ($this->config['provider'] ?? 'infomaniak');
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function conversational(): bool
    {
        return (bool) ($this->config['conversational'] ?? false);
    }

    /**
     * Not used — prompt comes from the markdown body.
     *
     * @since 1.0.0
     */
    protected function templateName(): string
    {
        return '';
    }

    /**
     * Not used — system prompt comes from frontmatter.
     *
     * @since 1.0.0
     */
    protected function systemTemplateName(): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Renders the markdown body with {{variable}} interpolation.
     *
     * @since 1.0.0
     */
    protected function buildPromptText(array $data): string
    {
        return $this->interpolate($this->promptTemplate, $data);
    }

    /**
     * {@inheritDoc}
     *
     * Returns the system prompt from frontmatter configuration.
     *
     * @since 1.0.0
     */
    protected function buildSystemText(array $data): ?string
    {
        $system = $this->config['system'] ?? null;
        return is_string($system) && $system !== '' ? $system : null;
    }

    /**
     * Returns the raw frontmatter configuration array.
     *
     * Useful for CLI inspection and debugging.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Interpolates {{variable}} placeholders in a template string.
     *
     * @since 1.0.0
     *
     * @param string $template The template with {{variable}} placeholders.
     * @param array  $data     The data to interpolate.
     * @return string The interpolated string.
     */
    private function interpolate(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            static fn(array $m): string => (string) ($data[$m[1]] ?? ''),
            $template
        );
    }
}
