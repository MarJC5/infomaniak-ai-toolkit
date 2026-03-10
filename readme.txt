=== Infomaniak AI Toolkit (Unofficial) ===
Contributors: custom
Tags: ai, infomaniak, llama, mistral, presets, image-generation, conversation-memory, markdown-commands, agent, function-calling, wp-cli, rate-limiting
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 8.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unofficial Infomaniak AI Toolkit AI Tools for the PHP AI Client SDK.

== Description ==

This plugin provides Infomaniak AI integration for the PHP AI Client SDK. It enables WordPress sites to use open-source models hosted in Switzerland via Infomaniak's OpenAI-compatible API.

**This plugin is not officially maintained by Infomaniak.** It is developed independently by a partner developer.

**Features:**

* Text generation with open-source models (Llama, Mistral, DeepSeek, Qwen)
* Image generation support
* Async batch operations (status polling + result download)
* JSON structured output support
* Function calling support
* Conversation memory with automatic compaction for long conversations
* Usage tracking with per-preset cost attribution
* Automatic provider registration
* Models hosted in Switzerland for data sovereignty
* Dynamic model discovery from the Infomaniak API (LLM and image models)
* Connector icon on the Settings > Connectors page
* **AI Presets SDK** -- abstract class for building reusable AI commands with PHP templates, auto-registered as WordPress Abilities (REST API + MCP)
* **Markdown Commands** -- create AI commands with simple markdown files, no PHP required
* **Agent Orchestrator** -- function calling loop that lets models use tools autonomously
* **Rate Limiting** -- configurable per-role rate limits with time windows (hour/day/month), extensible via filter
* **WP-CLI Commands** -- `wp infomaniak-ai` commands for usage stats, model listing, cache management, rate limits, and memory inspection

Available models are dynamically discovered from the Infomaniak API, including text generation models (Llama, Mistral, Mixtral, DeepSeek, Qwen) and image generation models.

**Markdown Commands:**

Create AI commands without writing PHP. Drop a `.md` file in `wp-content/ai-commands/` with a YAML frontmatter header and a prompt template using `{{variable}}` placeholders. The command is auto-discovered and registered as a WordPress Ability. Bundled commands include `summarize` and `translate`.

**AI Presets:**

This plugin includes `BasePreset`, an abstract class that lets any plugin create reusable AI commands without boilerplate. Each preset combines a prompt template, system instruction, AI settings, and input validation into a single class. Presets auto-register as WordPress Abilities, making them available via REST API and MCP for AI agents.

Create a preset by extending `BasePreset` in your plugin, add a PHP template for the prompt, and call `registerAsAbility()`. No need to manually build prompts, configure the AI client, or register REST endpoints -- the framework handles it all.

See the [GitHub README](https://github.com/MarJC5/infomaniak-ai-toolkit) for a full guide with code examples.

**Agent Orchestrator:**

The `AgentLoop` class provides a function calling loop for agentic AI behavior. Define tools with a name, description, JSON Schema parameters, and a PHP handler. The model decides when to call tools, the loop executes them and feeds results back, repeating until the model produces a final response. Presets can enable agent mode by overriding the `tools()` method. The loop can also be used standalone without presets.

**Requirements:**

* PHP 8.0 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* An Infomaniak account with an AI Tools product
* Infomaniak API key

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/infomaniak-ai-toolkit/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Infomaniak API key via **Settings > Connectors**
4. Configure your Product ID via **Settings > Infomaniak AI**

== Frequently Asked Questions ==

= How do I get an Infomaniak API key? =

Visit the [Infomaniak Manager](https://manager.infomaniak.com/v3/ng/products/cloud/ai-tools) to create an AI Tools product and generate an API key.

= Where do I find my Product ID? =

Your Product ID is available in the Infomaniak Manager under AI Tools. It can be set via **Settings > Infomaniak AI**, the `INFOMANIAK_AI_PRODUCT_ID` constant, or the `infomaniak_ai_product_id` filter.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the Infomaniak-specific implementation that the PHP AI Client uses.

= What models are available? =

Models are dynamically discovered from the Infomaniak API. Text generation models typically include Llama 3, Mistral, Mixtral, DeepSeek, and Qwen. Image generation models are also available. All models are hosted in Switzerland.

= Where is my data processed? =

All AI processing happens on Infomaniak's infrastructure in Switzerland, ensuring data sovereignty and compliance with European data protection standards.

= What are Markdown Commands? =

Markdown commands are AI commands defined as simple `.md` files. Each file has a YAML frontmatter section for configuration (description, temperature, system prompt, etc.) and a body with `{{variable}}` placeholders for the prompt template. Place them in `wp-content/ai-commands/` or your theme's `ai-commands/` directory. They are auto-discovered and registered as WordPress Abilities.

= What are AI Presets? =

Presets are reusable AI commands built on the `BasePreset` abstract class. Instead of writing prompt strings, configuring the AI client, and registering REST endpoints manually for each AI feature, you extend `BasePreset`, create a PHP template, and the framework handles execution and ability registration. Each preset becomes a WordPress Ability, discoverable via REST API and MCP.

= How do rate limits work? =

Rate limits are enforced per user based on their primary WordPress role. Administrators are unlimited by default. Other roles have configurable limits per time window (hour, day, or month). Guest (unauthenticated) users are rate-limited by IP using a pseudonymized HMAC hash stored in an auto-expiring transient -- no plain IP is ever persisted. Configure limits via **Settings > Infomaniak AI** or use the `infomaniak_ai_rate_limit_check` filter for custom logic.

= Can I create my own presets in another plugin? =

Yes. Extend `WordPress\InfomaniakAiToolkit\Presets\BasePreset` from any plugin. The base class auto-detects your plugin's directory to find templates. Place your PHP templates in `your-plugin/templates/presets/` and system prompts in `your-plugin/templates/presets/system/`. Use the `infomaniak_ai_presets` filter to register them.

== Changelog ==

= 1.0.0 =

* Initial release
* Text generation with OpenAI-compatible Chat Completions API
* Image generation support
* Async batch operations with status polling and result download
* Dynamic model discovery from the Infomaniak API (LLM and image models)
* Product ID configuration via option, constant, or filter
* API key management via WordPress Connectors
* Connector icon on the Settings > Connectors page
* Models cache with automatic refresh
* AI Presets SDK: `BasePreset` abstract class for building reusable AI commands
* PHP template engine for prompt rendering
* System prompt templates (content editor, SEO expert, accessibility)
* Auto-detection of plugin root for cross-plugin preset support
* Auto-registration as WordPress Abilities (REST API + MCP)
* Conversation memory system with per-user, per-conversation history
* Memory compaction via `CompactingStrategy` (background summarization, zero latency)
* Usage tracking with real token counts from the SDK
* Extensible via `infomaniak_ai_presets` filter
* Markdown Commands: create AI commands with `.md` files, no PHP required
* Auto-discovery of command files from theme, plugin, and custom directories
* YAML-like frontmatter parser (no external dependencies)
* `{{variable}}` interpolation for prompt templates
* Auto-generated input JSON Schema from template variables
* Example commands: `summarize` and `translate`
* `infomaniak_ai_commands_dirs` filter for custom command directories
* Agent Orchestrator: `AgentLoop` class for function calling loops
* `Tool` and `ToolRegistry` classes for defining and managing tools
* Automatic agent mode in presets via `tools()` override
* `infomaniak_ai_agent_tool_called` and `infomaniak_ai_agent_step` hooks
* WP-CLI commands: `wp infomaniak-ai usage`, `models`, `cache`, `commands`, `rate-limits`, `memory`, `memory-clear`
* Rate limiting: per-role configurable limits with time windows (hour/day/month)
* Admin settings UI for rate limit configuration
* `infomaniak_ai_rate_limit_check` filter for custom rate limiting logic
