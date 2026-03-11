# Infomaniak AI Toolkit (Unofficial)

An unofficial AI Provider for [Infomaniak AI Tools](https://www.infomaniak.com/en/hosting/ai-tools) for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK.

> **Note:** This plugin is not officially maintained by Infomaniak. It is developed independently by a partner developer.

Provides access to open-source models hosted in Switzerland via Infomaniak's OpenAI-compatible API. Supports text generation, image generation, function calling, conversation memory with automatic compaction, and usage tracking with per-preset cost attribution.

## Requirements

- PHP 8.0 or higher
- WordPress 6.9 or higher
    - WordPress 6.9 requires the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package to be installed separately
    - WordPress 7.0+ includes the PHP AI Client natively
- An Infomaniak account with an AI Tools product

## Installation

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/infomaniak-ai-toolkit/`
3. Activate the plugin through the WordPress admin
4. Configure your API key in **Settings > Connectors**
5. Configure your Product ID in **Settings > Infomaniak AI**

## Configuration

### Product ID

The Infomaniak AI product ID can be configured in three ways (checked in this order):

1. **Filter**: Use the `infomaniak_ai_product_id` filter
2. **Constant**: Define `INFOMANIAK_AI_PRODUCT_ID` in `wp-config.php`
3. **Option**: Set it via **Settings > Infomaniak AI** in the WordPress admin

```php
// Via wp-config.php constant
define( 'INFOMANIAK_AI_PRODUCT_ID', '123456' );

// Via filter
add_filter( 'infomaniak_ai_product_id', function() {
    return '123456';
});
```

### API Key

The API key is managed via the WordPress Connectors system at **Settings > Connectors**. The key is stored as `connectors_ai_infomaniak_api_key`.

You can obtain your API key from the [Infomaniak Manager](https://manager.infomaniak.com/v3/ng/products/cloud/ai-tools).

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your credentials:

```php
use WordPress\AiClient\AiClient;

// Use the provider (auto-detected)
$result = AiClient::prompt( 'Hello, world!' )
    ->usingTemperature( 0.7 )
    ->generateText();

// Force the Infomaniak provider
$result = AiClient::prompt( 'Explain quantum computing' )
    ->usingProvider( 'infomaniak' )
    ->generateText();

// Use a specific model
$result = AiClient::prompt( 'Write a haiku' )
    ->usingProvider( 'infomaniak' )
    ->usingModelPreference( 'llama3' )
    ->generateText();

// Generate an image
$file = AiClient::prompt( 'A mountain landscape at sunset' )
    ->usingProvider( 'infomaniak' )
    ->generateImage();

$dataUri  = $file->getDataUri();   // data:image/png;base64,...
$mimeType = $file->getMimeType();  // image/png
```


## Markdown Commands

Markdown commands let site admins create AI commands without writing PHP. Each command is a `.md` file with YAML frontmatter (configuration) and a body (prompt template).

### Quick start

Create a file in `wp-content/ai-commands/translate.md`:

```markdown
---
description: Translates text to a target language.
max_tokens: 2000
temperature: 0.3
system: |
  You are a professional translator.
  Preserve formatting, tone, and meaning.
  Return only the translated text, no commentary.
---
Translate the following text to {{language}}:

{{content}}
```

That's it. The command is auto-discovered and registered as a WordPress Ability:
- **REST API**: `POST /wp-json/wp-abilities/v1/abilities/infomaniak/translate/run`
- **MCP tool**: automatically exposed to AI agents
- **Input**: `{"language": "English", "content": "Bonjour le monde."}`

### How it works

- **Frontmatter** defines the command configuration (description, temperature, system prompt, etc.)
- **Body** is the prompt template with `{{variable}}` placeholders
- **Variables** are auto-detected from `{{variable}}` patterns and used to generate the input JSON Schema
- **Name** is derived from the filename (e.g., `translate.md` becomes `translate`)
- Commands inherit all preset features: usage tracking, conversation memory, compaction

### Command directories

Files are scanned from these directories (first match wins on name conflicts):

1. Directories added via the `infomaniak_ai_commands_dirs` filter
2. Active plugins: `{plugin}/ai-commands/`
3. Active theme: `{theme}/ai-commands/`

```php
// Add a custom directory
add_filter( 'infomaniak_ai_commands_dirs', function ( array $dirs ): array {
    $dirs[] = WP_CONTENT_DIR . '/my-ai-commands';
    return $dirs;
});
```

### Frontmatter reference

| Field | Default | Description |
|---|---|---|
| `description` | **required** | What the command does |
| `label` | derived from filename | Human-readable label |
| `category` | `content` | Ability category slug |
| `permission` | `edit_posts` | Required WordPress capability |
| `temperature` | `0.7` | Generation temperature |
| `max_tokens` | `1000` | Maximum response tokens |
| `model` | `null` (SDK picks) | Preferred model ID |
| `model_type` | `llm` | `llm` or `image` |
| `system` | `null` | System instruction (supports multi-line with `\|`) |
| `conversational` | `false` | Enable conversation memory |
| `provider` | `infomaniak` | AI provider ID |

### Example commands

See the [`examples/commands/`](examples/commands/) directory for copy-paste-ready command files:

- **[summarize.md](examples/commands/summarize.md)** -- Summarizes content concisely
- **[translate.md](examples/commands/translate.md)** -- Translates text to a target language

These files are not auto-loaded. Copy them to your theme's `ai-commands/` directory or a custom directory registered via the `infomaniak_ai_commands_dirs` filter.

### Markdown commands vs PHP presets

| | Markdown Commands | PHP Presets |
|---|---|---|
| **Audience** | Site admins, content creators | Developers |
| **Syntax** | Markdown + `{{variables}}` | PHP classes + templates |
| **Complexity** | Simple text prompts | Full control (data fetching, validation, custom execution) |
| **Features** | Text generation, system prompts, conversation memory | Everything (image generation, JSON output, custom logic) |
| **Location** | `.md` files in `ai-commands/` | PHP classes in plugins |

Use markdown commands for simple, template-based AI prompts. Use PHP presets when you need data fetching, custom validation, structured output, or image generation.

## AI Presets

This plugin provides `BasePreset`, an abstract class for building reusable AI commands. Each preset is a self-contained unit combining a prompt template, system instruction, AI configuration, and input validation -- all auto-registered as a [WordPress Ability](https://developer.wordpress.org/abilities/) discoverable via REST API and MCP.

**Why presets?** Without presets, every AI feature requires writing the same boilerplate: build a prompt string, configure the AI client, handle errors, register an ability. With `BasePreset`, you declare *what* the AI should do, and the framework handles *how*.

### Creating a preset

1. Extend `BasePreset` in your own plugin:

```php
namespace MyPlugin\Presets;

use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

class SummarizePreset extends BasePreset
{
    public function name(): string { return 'summarize'; }
    public function label(): string { return 'Summarize Content'; }
    public function description(): string { return 'Generates a concise summary.'; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => ['type' => 'string', 'description' => 'Text to summarize.'],
                'max_sentences' => ['type' => 'integer', 'default' => 3],
            ],
            'required' => ['content'],
        ];
    }

    protected function templateName(): string { return 'summarize'; }
    protected function systemTemplateName(): ?string { return 'content-editor'; }
    protected function maxTokens(): int { return 500; }
}
```

2. Create a PHP template at `your-plugin/templates/presets/summarize.php`:

```php
Summarize the following content:

<?= $content ?>

Requirements:
- Maximum <?= (int) $max_sentences ?> sentences.
- Focus on the main points.
```

3. Register it on `wp_abilities_api_init`:

```php
add_action( 'wp_abilities_api_init', function() {
    $preset = new \MyPlugin\Presets\SummarizePreset();
    $preset->registerAsAbility();
});
```

The preset is now available as:
- **REST API**: `POST /wp-json/wp-abilities/v1/abilities/infomaniak/summarize/run`
- **MCP tool**: automatically exposed to AI agents
- **PHP**: `$preset->execute(['content' => '...'])`

### How it works

- **PHP templates** -- Prompts are `.php` files rendered with `extract()`. Variables come from `templateData()`, which you can override to transform or enrich input.
- **System prompts** -- Optional `.php` files in a `system/` subdirectory set the AI persona (content editor, SEO expert, etc.).
- **Auto-detection** -- `BasePreset` finds templates relative to your plugin root automatically via `ReflectionClass`. No path configuration needed.
- **Structured output** -- Override `outputSchema()` to return a JSON Schema and the preset will use `asJsonResponse()` and decode the result automatically.
- **Image generation** -- Override `execute()` to call `generateImage()` instead of `generateText()`. Use `ModelConfig` to set orientation and other image options. Override `modelType()` to return `'image'`.
- **Model preference** -- Call `setModelPreference()` at runtime to override the model, or override `modelPreference()` for a default.
- **Provider override** -- Override `provider()` to use a different AI provider (defaults to `'infomaniak'`).
- **Agent mode** -- Override `tools()` to provide tools and `execute()` automatically uses the `AgentLoop` for function calling iteration.
- **Extensible** -- Use the `infomaniak_ai_presets` filter to add or remove presets from any plugin.

### Overridable methods

| Method | Default | Description |
|---|---|---|
| `temperature()` | `0.7` | Controls response randomness |
| `maxTokens()` | `1000` | Maximum response length |
| `requestTimeout()` | `60.0` | HTTP request timeout in seconds for AI API calls |
| `outputSchema()` | `null` | JSON Schema for structured output |
| `provider()` | `'infomaniak'` | AI provider ID |
| `category()` | `'content'` | Ability category slug |
| `permission()` | `'edit_posts'` | Required WordPress capability |
| `annotations()` | `readonly, non-destructive, idempotent` | MCP behavioral annotations |
| `templateData($input)` | passthrough | Transform input before rendering |
| `modelPreference()` | `null` | Preferred model ID (SDK picks if null) |
| `modelType()` | `'llm'` | Model type: `'llm'` or `'image'` |
| `tools()` | `[]` | Tools for agent mode (function calling) |
| `maxAgentIterations()` | `10` | Max tool-calling rounds |

### Examples

See the [`examples/presets/`](examples/presets/) directory for complete, copy-paste-ready presets:

- **[basic-preset.php](examples/presets/basic-preset.php)** -- Minimal preset with a prompt template and system instruction
- **[json-output-preset.php](examples/presets/json-output-preset.php)** -- Structured JSON output with `outputSchema()`
- **[post-aware-preset.php](examples/presets/post-aware-preset.php)** -- Fetches WordPress post data via `templateData()` and validates with a custom `execute()` override
- **[image-preset.php](examples/presets/image-preset.php)** -- Image generation with a custom `execute()` override using `generateImage()` and `ModelConfig`
- **[conversational-preset.php](examples/presets/conversational-preset.php)** -- Multi-turn chat with conversation memory and optional compaction
- **[agent-preset.php](examples/presets/agent-preset.php)** -- Agent with function calling tools that search and read WordPress content

## Agent Orchestrator

The `AgentLoop` class provides a function calling loop that lets AI models use tools autonomously. The model receives tool declarations, decides when to call them, and the loop handles execution and iteration until the model produces a final response.

### With a preset

Override `tools()` in your preset to enable agent mode automatically:

```php
use WordPress\InfomaniakAiToolkit\Agent\Tool;
use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

class ResearchPreset extends BasePreset
{
    protected function tools(): array
    {
        return [
            new Tool(
                'search_posts',
                'Search WordPress posts by keyword',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search keywords'],
                    ],
                    'required' => ['query'],
                ],
                function (array $args): array {
                    $posts = get_posts(['s' => $args['query'], 'posts_per_page' => 5]);
                    return array_map(fn($p) => [
                        'id' => $p->ID,
                        'title' => $p->post_title,
                        'excerpt' => wp_trim_words($p->post_content, 50),
                    ], $posts);
                }
            ),
        ];
    }

    protected function maxAgentIterations(): int { return 5; }
    // ...
}
```

When `tools()` returns tools, `execute()` uses `AgentLoop` instead of a single AI call.

### Standalone usage

```php
use WordPress\InfomaniakAiToolkit\Agent\AgentLoop;
use WordPress\InfomaniakAiToolkit\Agent\Tool;
use WordPress\InfomaniakAiToolkit\Agent\ToolRegistry;

$registry = new ToolRegistry();
$registry->register(new Tool(
    'get_weather',
    'Get current weather for a city',
    [
        'type' => 'object',
        'properties' => [
            'city' => ['type' => 'string'],
        ],
        'required' => ['city'],
    ],
    fn(array $args) => ['temperature' => 22, 'condition' => 'sunny']
));

$loop = new AgentLoop($registry, [
    'provider'       => 'infomaniak',
    'system'         => 'You are a helpful assistant. Use tools when needed.',
    'max_iterations' => 5,
]);

$result = $loop->run('What is the weather in Zurich?');

echo $result->getText();           // Final text response
echo $result->getIterationCount(); // Number of tool-calling rounds
echo $result->hasToolCalls();      // true if tools were used
```

### How it works

```
User prompt + tool declarations → API
    ↓
Model response
    ├── finish_reason: stop → return final text
    └── finish_reason: tool_calls → extract calls
                                      ↓
                                Execute tools via ToolRegistry
                                      ↓
                                Send results back → API
                                      ↓
                                Model response → repeat (max N iterations)
```

### AgentLoop options

| Option | Default | Description |
|---|---|---|
| `provider` | `'infomaniak'` | AI provider ID |
| `model` | `null` | Preferred model ID |
| `temperature` | `0.7` | Generation temperature |
| `max_tokens` | `4096` | Maximum response tokens |
| `system` | `null` | System instruction |
| `max_iterations` | `10` | Maximum tool-calling rounds |
| `request_timeout` | `60.0` | HTTP request timeout in seconds |

### Hooks

- `infomaniak_ai_agent_tool_called` (action) -- fires after each tool execution, receives `($functionCall, $toolResult, $agentLoop)`
- `infomaniak_ai_agent_step` (action) -- fires after each iteration, receives `($steps, $messages, $agentLoop)`

## Conversation Memory

Presets that return `true` from `conversational()` automatically store and load conversation history. Messages are stored in a dedicated database table (`wp_ai_provider_toolkit_memory`) and injected into subsequent turns via the SDK's `withHistory()`.

### Basic conversational preset

```php
class ChatPreset extends BasePreset
{
    protected function conversational(): bool { return true; }
    protected function historySize(): int { return 30; }
    // ...
}
```

The preset returns `['conversation_id' => '...', 'result' => '...']`. Pass the `conversation_id` back on subsequent calls to continue the conversation.

### Memory compaction

For long conversations, the `CompactingStrategy` automatically summarizes old messages when token usage exceeds a threshold. Compaction runs in the background via the `shutdown` hook -- zero latency for the user.

```php
use WordPress\InfomaniakAiToolkit\Memory\CompactingStrategy;
use WordPress\InfomaniakAiToolkit\Memory\MemoryStrategy;

class ChatPreset extends BasePreset
{
    protected function conversational(): bool { return true; }

    protected function memoryStrategy(): MemoryStrategy
    {
        return new CompactingStrategy(
            tokenBudget: 16000,         // Max token budget for history
            compactionThreshold: 0.8,   // Compact at 80% of budget
            recentKeepCount: 6,         // Keep 6 recent messages intact
            summaryMaxTokens: 300,      // Max tokens for the summary
        );
    }
}
```

How it works:
1. After each turn, checks token usage via a single SQL `SUM` query
2. If over 80% of budget, schedules compaction via the `shutdown` hook
3. Compaction runs after the HTTP response is sent (zero user latency)
4. Old messages are summarized into a single `summary` record
5. Next turn loads: `[summary] + [recent messages]` -- fast, no AI call

Hooks:
- `infomaniak_ai_memory_before_compact` (filter) -- return `false` to cancel compaction
- `infomaniak_ai_memory_compacted` (action) -- fires after compaction completes

## WP-CLI Commands

The plugin registers commands under `wp infomaniak-ai` for debugging, ops, and maintenance.

### Usage statistics

```bash
# Show last 25 usage records
wp infomaniak-ai usage

# Filter by user and date range
wp infomaniak-ai usage --user=1 --from=2026-03-01

# Show aggregate summary
wp infomaniak-ai usage --summary

# Export as JSON
wp infomaniak-ai usage --format=json --limit=1000
```

Options: `--user=<id>`, `--model=<id>`, `--preset=<name>`, `--from=<date>`, `--to=<date>`, `--limit=<n>`, `--summary`, `--format=<table|json|csv|yaml|count>`

### Models

```bash
# List all available models
wp infomaniak-ai models

# List only LLM models
wp infomaniak-ai models --type=llm

# Force refresh from Infomaniak API
wp infomaniak-ai models --refresh
```

### Cache management

```bash
# Show cache status
wp infomaniak-ai cache status

# Clear all caches (models + commands)
wp infomaniak-ai cache clear

# Clear only the models cache
wp infomaniak-ai cache clear --type=models
```

### Markdown commands

```bash
# List all discovered commands
wp infomaniak-ai commands

# Show full details (category, temperature, model, etc.)
wp infomaniak-ai commands --verbose
```

### Rate limits

```bash
# Show rate limit configuration per role
wp infomaniak-ai rate-limits

# Output as JSON
wp infomaniak-ai rate-limits --format=json
```

### Conversation memory

```bash
# List recent memory records
wp infomaniak-ai memory

# View a specific conversation
wp infomaniak-ai memory --conversation=abc-123

# Show summary (message count + tokens)
wp infomaniak-ai memory --summary

# Clear old conversations
wp infomaniak-ai memory-clear --before=2026-02-08

# Clear a specific conversation (non-interactive)
wp infomaniak-ai memory-clear --conversation=abc-123 --yes
```

## Rate Limiting

The plugin enforces per-role rate limits on all AI preset executions (PHP presets and markdown commands). Limits are configurable via **Settings > Infomaniak AI** or programmatically.

### Default limits

| Role | Limit | Window |
|---|---|---|
| Administrator | Unlimited | — |
| Editor | 100 | Hour |
| Author | 50 | Hour |
| Contributor | 20 | Hour |
| Subscriber | 10 | Hour |
| Guest | 5 | Hour |

Guest (unauthenticated) users are rate-limited by IP address using WordPress transients. This enables frontend usage without requiring authentication while preventing abuse.

**Privacy:** Guest IP addresses are never stored in plain text. The plugin uses HMAC-SHA256 with the site's secret salt (`AUTH_SALT`) to produce a pseudonymized hash. The hash is stored in a WordPress transient that expires automatically at the end of the time window (1 hour, 1 day, or 30 days). No persistent data is retained for guests.

### Configuration

Limits are stored in the `infomaniak_ai_rate_limits` WordPress option. Configure them via the admin UI or directly:

```php
use WordPress\InfomaniakAiToolkit\RateLimit\RateLimitConfig;

RateLimitConfig::save([
    'editor'     => ['limit' => 200, 'window' => 'day'],
    'subscriber' => ['limit' => 5,   'window' => 'hour'],
]);
```

Available windows: `hour`, `day`, `month`. Set `limit` to `0` for unlimited.

### Hook

Use the `infomaniak_ai_rate_limit_check` filter to customize rate limiting behavior:

```php
// Allow specific users to bypass rate limits
add_filter('infomaniak_ai_rate_limit_check', function ($result, $userId, $presetName, $context) {
    // VIP users bypass all limits
    if (get_user_meta($userId, 'vip_user', true)) {
        return null; // Allow
    }
    return $result;
}, 10, 4);
```

Parameters:
- `$result` — `null` (allowed) or `WP_Error` (blocked)
- `$userId` — WordPress user ID
- `$presetName` — The preset being executed
- `$context` — `['role', 'limit', 'window', 'count']`

### Programmatic check

```php
use WordPress\InfomaniakAiToolkit\RateLimit\RateLimiter;

// Check if current user can make a request
$error = RateLimiter::check('my-preset');
if ($error !== null) {
    // User is rate-limited, $error is a WP_Error with code 429
}

// Get remaining requests for current user
$remaining = RateLimiter::getRemainingForCurrentUser();
// ['limit' => 100, 'remaining' => 42, 'window' => 'hour', 'reset' => 3600]
```

## Supported Models

Available models are dynamically discovered from the Infomaniak API. The provider supports two model types:

- **Text generation (LLM)** -- Open-source models such as Llama (Meta), Mistral / Mixtral (Mistral AI), DeepSeek, Qwen (Alibaba)
- **Image generation** -- Models available through Infomaniak's image generation API

All models are hosted in Switzerland by Infomaniak. See the [Infomaniak AI Tools documentation](https://www.infomaniak.com/en/hosting/ai-tools) for the full list of available models.

## License

GPL-2.0-or-later
