<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Combines a function declaration (what the model sees) with a callable handler (what executes).
 *
 * A Tool is the bridge between the AI model's function calling and your PHP code.
 * The model receives the function declaration (name, description, JSON Schema parameters)
 * and when it decides to call the tool, the handler is executed with the provided arguments.
 *
 * @since 1.0.0
 */
class Tool
{
    /**
     * @var string Tool name (must match the function declaration name).
     */
    private string $name;

    /**
     * @var string Human-readable description of what the tool does.
     */
    private string $description;

    /**
     * @var array|null JSON Schema for the tool parameters.
     */
    private ?array $parameters;

    /**
     * @var \Closure The handler that executes when the tool is called.
     */
    private \Closure $handler;

    /**
     * @param string   $name        Tool name (alphanumeric and underscores).
     * @param string   $description What the tool does (shown to the model).
     * @param array|null $parameters JSON Schema for the tool's parameters.
     * @param callable $handler     Function that receives the arguments and returns a result.
     */
    public function __construct(string $name, string $description, ?array $parameters, callable $handler)
    {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->handler = \Closure::fromCallable($handler);
    }

    /**
     * Returns the tool name.
     *
     * @since 1.0.0
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the tool description.
     *
     * @since 1.0.0
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Converts this tool to a FunctionDeclaration for the AI SDK.
     *
     * @since 1.0.0
     */
    public function toFunctionDeclaration(): FunctionDeclaration
    {
        return new FunctionDeclaration($this->name, $this->description, $this->parameters);
    }

    /**
     * Executes the tool handler with the given arguments.
     *
     * @since 1.0.0
     *
     * @param mixed $args Arguments from the model's function call.
     * @return mixed The tool execution result.
     */
    public function execute(mixed $args): mixed
    {
        return ($this->handler)($args);
    }

    /**
     * Creates a Tool from a registered WordPress Ability.
     *
     * Maps the ability's name, description, input_schema, and execute_callback
     * directly to a Tool instance.
     *
     * @since 1.0.0
     *
     * @param string $abilityId The ability ID (e.g. 'infomaniak/summarize').
     * @return self
     *
     * @throws \InvalidArgumentException If the ability is not registered.
     */
    public static function fromAbility(string $abilityId): self
    {
        if (!function_exists('wp_get_ability')) {
            throw new \RuntimeException('WordPress Abilities API is not available.');
        }

        $ability = wp_get_ability($abilityId);

        if ($ability === null) {
            throw new \InvalidArgumentException(
                sprintf('Ability "%s" is not registered.', $abilityId)
            );
        }

        // Convert ability ID to a tool-friendly name (e.g. 'infomaniak/summarize' → 'summarize').
        $name = str_contains($abilityId, '/') ? substr($abilityId, strrpos($abilityId, '/') + 1) : $abilityId;

        return new self(
            $name,
            $ability['description'] ?? '',
            $ability['input_schema'] ?? null,
            $ability['execute_callback'],
        );
    }
}
