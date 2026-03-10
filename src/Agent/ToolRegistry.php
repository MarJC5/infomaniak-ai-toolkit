<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Agent;

use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Registry of tools available to the agent loop.
 *
 * Stores Tool instances by name and provides lookup, execution,
 * and bulk conversion to FunctionDeclaration[] for the AI SDK.
 *
 * @since 1.0.0
 */
class ToolRegistry
{
    /**
     * Registered tools keyed by name.
     *
     * @var array<string, Tool>
     */
    private array $tools = [];

    /**
     * Registers a tool in the registry.
     *
     * @since 1.0.0
     *
     * @param Tool $tool The tool to register.
     * @return self
     */
    public function register(Tool $tool): self
    {
        $this->tools[$tool->name()] = $tool;
        return $this;
    }

    /**
     * Checks if a tool is registered.
     *
     * @since 1.0.0
     *
     * @param string $name Tool name.
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Returns a tool by name.
     *
     * @since 1.0.0
     *
     * @param string $name Tool name.
     * @return Tool
     *
     * @throws \InvalidArgumentException If the tool is not registered.
     */
    public function get(string $name): Tool
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(
                sprintf('Tool "%s" is not registered.', $name)
            );
        }

        return $this->tools[$name];
    }

    /**
     * Executes a tool by name with the given arguments.
     *
     * @since 1.0.0
     *
     * @param string $name Tool name.
     * @param mixed  $args Arguments from the model's function call.
     * @return mixed The tool execution result.
     *
     * @throws \InvalidArgumentException If the tool is not registered.
     */
    public function execute(string $name, mixed $args): mixed
    {
        return $this->get($name)->execute($args);
    }

    /**
     * Returns all tools as FunctionDeclaration[] for the AI SDK.
     *
     * @since 1.0.0
     *
     * @return FunctionDeclaration[]
     */
    public function getFunctionDeclarations(): array
    {
        return array_map(
            static fn(Tool $tool): FunctionDeclaration => $tool->toFunctionDeclaration(),
            array_values($this->tools)
        );
    }

    /**
     * Returns all registered tools.
     *
     * @since 1.0.0
     *
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Returns the number of registered tools.
     *
     * @since 1.0.0
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Registers WordPress Abilities as tools.
     *
     * When called without arguments, registers all available abilities.
     * Pass an array of ability IDs to register specific ones.
     *
     * @since 1.0.0
     *
     * @param string[]|null $abilityIds Specific ability IDs to register, or null for all.
     * @param string[]      $exclude    Ability IDs to exclude (only used when $abilityIds is null).
     * @return self
     */
    public function registerAbilities(?array $abilityIds = null, array $exclude = []): self
    {
        if (!function_exists('wp_get_abilities')) {
            return $this;
        }

        if ($abilityIds !== null) {
            foreach ($abilityIds as $id) {
                $this->register(Tool::fromAbility($id));
            }
            return $this;
        }

        $abilities = wp_get_abilities();

        foreach (array_keys($abilities) as $id) {
            if (in_array($id, $exclude, true)) {
                continue;
            }

            try {
                $this->register(Tool::fromAbility((string) $id));
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return $this;
    }
}
