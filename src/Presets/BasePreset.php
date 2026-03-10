<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\Presets;

use WordPress\AiClient\AiClient;
use WordPress\InfomaniakAiProvider\Agent\AgentLoop;
use WordPress\InfomaniakAiProvider\Agent\ToolRegistry;
use WordPress\InfomaniakAiProvider\Memory\CompactingStrategy;
use WordPress\InfomaniakAiProvider\Memory\MemoryStore;
use WordPress\InfomaniakAiProvider\Memory\MemoryStrategy;
use WordPress\InfomaniakAiProvider\Memory\SlidingWindowStrategy;
use WordPress\InfomaniakAiProvider\RateLimit\RateLimiter;
use WordPress\InfomaniakAiProvider\Usage\UsageTracker;

/**
 * Abstract base class for AI prompt presets.
 *
 * Each preset defines a reusable prompt template that can be executed
 * via the WordPress AI Client and auto-registered as a WordPress Ability
 * for REST API and MCP discoverability.
 *
 * @since 1.0.0
 */
abstract class BasePreset
{
    /**
     * Returns the preset identifier (e.g. 'summarize').
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Returns a human-readable label.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function label(): string;

    /**
     * Returns a description of what this preset does.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Returns the JSON Schema for the preset's input parameters.
     *
     * @since 1.0.0
     *
     * @return array
     */
    abstract public function inputSchema(): array;

    /**
     * Returns the template file name (without extension) in templates/presets/.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract protected function templateName(): string;

    /**
     * Prepares data to inject into the template.
     *
     * Override this to transform or enrich input data before rendering.
     *
     * @since 1.0.0
     *
     * @param array $input Raw input parameters.
     * @return array Data to extract into the template scope.
     */
    protected function templateData(array $input): array
    {
        return $input;
    }

    /**
     * Returns the system prompt template name, or null if none.
     *
     * @since 1.0.0
     *
     * @return string|null Template name in templates/presets/system/ (without extension).
     */
    protected function systemTemplateName(): ?string
    {
        return null;
    }

    /**
     * Returns the temperature for generation.
     *
     * @since 1.0.0
     *
     * @return float
     */
    protected function temperature(): float
    {
        return 0.7;
    }

    /**
     * Returns the max tokens for generation.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function maxTokens(): int
    {
        return 1000;
    }

    /**
     * Model preference override set at runtime.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private ?string $modelPreferenceOverride = null;

    /**
     * Sets a runtime model preference override.
     *
     * When set, this takes precedence over the value returned by modelPreference().
     * Pass null to clear the override.
     *
     * @since 1.0.0
     *
     * @param string|null $modelId The model ID to use, or null to clear.
     */
    public function setModelPreference(?string $modelId): void
    {
        $this->modelPreferenceOverride = $modelId;
    }

    /**
     * Returns the preferred model ID, or null to let the SDK pick.
     *
     * Override in child presets to target a specific model.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function modelPreference(): ?string
    {
        return $this->modelPreferenceOverride;
    }

    /**
     * Returns a JSON Schema for structured output, or null for plain text.
     *
     * @since 1.0.0
     *
     * @return array|null
     */
    protected function outputSchema(): ?array
    {
        return null;
    }

    /**
     * Returns an array describing the output for the Ability registration.
     *
     * @since 1.0.0
     *
     * @return array JSON Schema for ability output.
     */
    protected function outputAbilitySchema(): array
    {
        return [
            'type' => 'string',
            'description' => __('The generated text.', 'ai-provider-for-infomaniak'),
        ];
    }

    /**
     * Returns the model type this preset requires ('llm' or 'image').
     *
     * Override in child presets that use a different model type.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function modelType(): string
    {
        return 'llm';
    }

    /**
     * Returns the ability category slug.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function category(): string
    {
        return 'content';
    }

    /**
     * Returns the required WordPress capability.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function permission(): string
    {
        return 'edit_posts';
    }

    /**
     * Returns MCP annotations for the ability.
     *
     * @since 1.0.0
     *
     * @return array
     */
    protected function annotations(): array
    {
        return [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ];
    }

    /**
     * Whether this preset supports multi-turn conversation.
     *
     * Override to return true to enable automatic history loading and storage.
     * When true, execute() will accept an optional 'conversation_id' in the input,
     * load prior messages via withHistory(), and store the new turn after generation.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    protected function conversational(): bool
    {
        return false;
    }

    /**
     * Maximum number of history messages to include in the context.
     *
     * Only relevant when conversational() returns true.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function historySize(): int
    {
        return 20;
    }

    /**
     * Returns the memory strategy to use for loading history.
     *
     * Override to use a custom strategy (e.g., summarization).
     *
     * @since 1.0.0
     *
     * @return MemoryStrategy
     */
    protected function memoryStrategy(): MemoryStrategy
    {
        return new SlidingWindowStrategy();
    }

    /**
     * Returns the JSON Schema properties for conversational input fields.
     *
     * Child presets can merge this into their inputSchema() when conversational.
     *
     * @since 1.0.0
     *
     * @return array
     */
    protected function conversationalInputProperties(): array
    {
        return [
            'conversation_id' => [
                'type' => 'string',
                'description' => __('Conversation identifier. Auto-generated if omitted.', 'ai-provider-for-infomaniak'),
            ],
        ];
    }

    /**
     * Returns tools available to this preset for agent behavior.
     *
     * Override in subclasses to provide tools. When tools are defined,
     * execute() automatically uses the AgentLoop for function calling
     * instead of a single AI call.
     *
     * @since 1.0.0
     *
     * @return \WordPress\InfomaniakAiProvider\Agent\Tool[]
     */
    protected function tools(): array
    {
        return [];
    }

    /**
     * Maximum number of agent loop iterations (tool-calling rounds).
     *
     * Only relevant when tools() returns tools.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function maxAgentIterations(): int
    {
        return 10;
    }

    /**
     * Renders a PHP template file with the given data.
     *
     * Uses a static closure to isolate the template scope, preventing
     * variable collisions between extract()'d data and method parameters.
     *
     * @since 1.0.0
     *
     * @param string $template Path to the template file.
     * @param array  $data     Variables to extract into the template scope.
     * @return string Rendered template output, or empty string on failure.
     */
    protected function render(string $template, array $data): string
    {
        if (!file_exists($template)) {
            return '';
        }

        $__render = static function (string $__file, array $__data): string {
            ob_start();
            try {
                // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
                extract($__data, EXTR_SKIP);
                include $__file;
                return trim((string) ob_get_clean());
            } catch (\Throwable $__e) {
                ob_end_clean();
                return '';
            }
        };

        return $__render($template, $data);
    }

    /**
     * Returns the provider ID to use for prompt execution.
     *
     * Override this to use a different provider.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function provider(): string
    {
        return 'infomaniak';
    }

    /**
     * Cached template paths keyed by child class name.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    private static array $templatePathCache = [];

    /**
     * Returns the base path to the templates directory.
     *
     * Auto-detects the plugin directory of the concrete child class,
     * so presets in any plugin will find their own templates.
     * The result is cached per class to avoid repeated directory walks.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function templatesPath(): string
    {
        $classKey = static::class;

        if (isset(self::$templatePathCache[$classKey])) {
            return self::$templatePathCache[$classKey];
        }

        $reflector = new \ReflectionClass(static::class);
        $childFile = $reflector->getFileName();

        // Walk up from the child class file to find the plugin root
        // (the directory containing a PHP file with a "Plugin Name:" header).
        $dir = dirname($childFile);
        while ($dir !== dirname($dir)) {
            foreach (glob($dir . '/*.php') as $file) {
                // Quick check for plugin header without reading the full file.
                $header = file_get_contents($file, false, null, 0, 8192);
                if ($header !== false && str_contains($header, 'Plugin Name:')) {
                    $result = $dir . '/templates/presets';
                    self::$templatePathCache[$classKey] = $result;
                    return $result;
                }
            }
            $dir = dirname($dir);
        }

        // Fallback: relative to the child class, go up to src/../templates/presets.
        $result = dirname($childFile, 3) . '/templates/presets';
        self::$templatePathCache[$classKey] = $result;
        return $result;
    }

    /**
     * Sets the usage tracking context to the current preset.
     *
     * Call this before making AI calls in custom execute() overrides
     * to ensure usage is attributed to this preset.
     *
     * @since 1.0.0
     */
    protected function setTrackingContext(): void
    {
        if (class_exists(UsageTracker::class)) {
            UsageTracker::setCurrentPreset($this->name());
        }
    }

    /**
     * Clears the usage tracking context.
     *
     * Call this in a finally block after AI calls in custom execute() overrides.
     *
     * @since 1.0.0
     */
    protected function clearTrackingContext(): void
    {
        if (class_exists(UsageTracker::class)) {
            UsageTracker::clearCurrentPreset();
        }
    }

    /**
     * Builds the user prompt text from templates and data.
     *
     * Override this in subclasses that use a different rendering mechanism
     * (e.g. markdown templates with {{variable}} interpolation).
     *
     * @since 1.0.0
     *
     * @param array $data Template data from templateData().
     * @return string The rendered prompt text.
     */
    protected function buildPromptText(array $data): string
    {
        return $this->render(
            $this->templatesPath() . '/' . $this->templateName() . '.php',
            $data
        );
    }

    /**
     * Builds the system instruction text from templates and data.
     *
     * Override this in subclasses that provide system prompts differently
     * (e.g. inline from frontmatter configuration).
     *
     * @since 1.0.0
     *
     * @param array $data Template data from templateData().
     * @return string|null The system instruction text, or null if none.
     */
    protected function buildSystemText(array $data): ?string
    {
        $systemTemplate = $this->systemTemplateName();
        if ($systemTemplate === null) {
            return null;
        }
        $text = $this->render(
            $this->templatesPath() . '/system/' . $systemTemplate . '.php',
            $data
        );
        return !empty($text) ? $text : null;
    }

    /**
     * Executes the preset with the given input.
     *
     * Builds the prompt from templates, configures the AI client,
     * and returns the generated result. Sets tracking context so
     * usage is attributed to this preset.
     *
     * @since 1.0.0
     *
     * @param array $input Input parameters matching the inputSchema.
     * @return string|array|\WP_Error Generated text or structured data.
     */
    public function execute(array $input)
    {
        if (!class_exists(AiClient::class)) {
            return new \WP_Error(
                'ai_unavailable',
                __('AI Client is not available.', 'ai-provider-for-infomaniak')
            );
        }

        // Rate limit check — block before any processing.
        $rateLimitError = RateLimiter::check($this->name());
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $this->setTrackingContext();

        try {
            // Resolve conversation context.
            $isConversational = $this->conversational();
            $conversationId = null;

            if ($isConversational) {
                $conversationId = !empty($input['conversation_id'])
                    ? sanitize_text_field($input['conversation_id'])
                    : MemoryStore::generateId();
            }

            $data = $this->templateData($input);

            // Render user prompt template.
            $promptText = $this->buildPromptText($data);

            if (empty($promptText)) {
                return new \WP_Error(
                    'preset_template_error',
                    __('Failed to render prompt template.', 'ai-provider-for-infomaniak')
                );
            }

            // Add system instruction if defined.
            $systemText = $this->buildSystemText($data);

            // Inject conversation history.
            $historyMessages = [];
            if ($isConversational && $conversationId !== null) {
                $historyMessages = $this->memoryStrategy()->loadMessages(
                    $conversationId,
                    $this->historySize(),
                    get_current_user_id()
                );
            }

            // Check if this preset has tools for agent behavior.
            $presetTools = $this->tools();

            try {
                if (!empty($presetTools)) {
                    // Agent mode: use the function calling loop.
                    $registry = new ToolRegistry();
                    foreach ($presetTools as $tool) {
                        $registry->register($tool);
                    }

                    $loop = new AgentLoop($registry, [
                        'provider'       => $this->provider(),
                        'model'          => $this->modelPreference(),
                        'temperature'    => $this->temperature(),
                        'max_tokens'     => $this->maxTokens(),
                        'system'         => $systemText,
                        'max_iterations' => $this->maxAgentIterations(),
                    ]);

                    $agentResult = $loop->run($promptText, $historyMessages);
                    $result = $agentResult->getText();
                } else {
                    // Standard mode: single AI call.
                    $builder = AiClient::prompt($promptText)
                        ->usingProvider($this->provider())
                        ->usingTemperature($this->temperature())
                        ->usingMaxTokens($this->maxTokens());

                    $modelPref = $this->modelPreference();
                    if ($modelPref !== null) {
                        $builder->usingModelPreference($modelPref);
                    }

                    if (!empty($systemText)) {
                        $builder->usingSystemInstruction($systemText);
                    }

                    if (!empty($historyMessages)) {
                        $builder->withHistory(...$historyMessages);
                    }

                    // Configure JSON output if schema is defined.
                    $outputSchema = $this->outputSchema();
                    if ($outputSchema !== null) {
                        $builder->asJsonResponse($outputSchema);
                    }

                    $result = $builder->generateText();
                }
            } catch (\Throwable $e) {
                return new \WP_Error(
                    'ai_generation_error',
                    $e->getMessage()
                );
            }

            // Configure JSON output for response handling.
            if (!isset($outputSchema)) {
                $outputSchema = $this->outputSchema();
            }

            // Read real token usage from the SDK (populated by UsageTracker hook).
            $lastUsage = class_exists(UsageTracker::class)
                ? UsageTracker::getLastTokenUsage()
                : null;

            // Store conversation turn with real token counts.
            if ($isConversational && $conversationId !== null) {
                $memoryContext = [
                    'user_id' => get_current_user_id(),
                    'preset_name' => $this->name(),
                    'token_count' => $lastUsage['prompt_tokens'] ?? 0,
                ];

                MemoryStore::storeMessage($conversationId, 'user', $promptText, $memoryContext);

                $memoryContext['token_count'] = $lastUsage['completion_tokens'] ?? 0;
                MemoryStore::storeMessage($conversationId, 'model', (string) $result, $memoryContext);

                // Let strategy decide if compaction should be scheduled.
                $strategy = $this->memoryStrategy();
                if ($strategy instanceof CompactingStrategy) {
                    $strategy->maybeScheduleCompaction($conversationId, get_current_user_id());
                }
            }

            // Decode JSON responses.
            if ($outputSchema !== null) {
                $decoded = json_decode($result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new \WP_Error(
                        'json_decode_error',
                        sprintf('Failed to decode AI response as JSON: %s', json_last_error_msg())
                    );
                }
                if ($isConversational && $conversationId !== null) {
                    return ['conversation_id' => $conversationId, 'result' => $decoded];
                }
                return $decoded;
            }

            if ($isConversational && $conversationId !== null) {
                return ['conversation_id' => $conversationId, 'result' => $result];
            }

            return $result;
        } finally {
            $this->clearTrackingContext();
        }
    }

    /**
     * Registers this preset as a WordPress Ability.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerAsAbility(): void
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        $preset = $this;

        wp_register_ability(
            $this->provider() . '/' . $this->name(),
            [
                'label' => $this->label(),
                'description' => $this->description(),
                'category' => $this->category(),
                'execute_callback' => function ($input) use ($preset) {
                    return $preset->execute($input ?? []);
                },
                'permission_callback' => function () use ($preset) {
                    return current_user_can($preset->permission());
                },
                'input_schema' => $this->inputSchema(),
                'output_schema' => $this->outputAbilitySchema(),
                'meta' => [
                    'annotations' => $this->annotations(),
                    'show_in_rest' => true,
                ],
            ]
        );
    }
}
