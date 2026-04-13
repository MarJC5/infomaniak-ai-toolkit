<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\Presets;

/**
 * Sequences preset executions, piping each output into the next.
 *
 * Each stage defines a preset and an optional key map that transforms
 * the output of the current stage into input for the next one.
 *
 * Usage:
 *   $chain = new PresetChain([
 *       ['preset' => new SearchPreset(),    'map' => ['result' => 'content']],
 *       ['preset' => new SummarizePreset(), 'map' => ['result' => 'text']],
 *       ['preset' => new TranslatePreset()],
 *   ]);
 *   $result = $chain->execute(['query' => 'WordPress security']);
 *
 * @since 1.0.0
 */
class PresetChain
{
    /**
     * Ordered list of stages.
     *
     * @var list<array{preset: BasePreset, map: array<string, string>}>
     */
    private array $stages;

    /**
     * @param list<array{preset: BasePreset, map?: array<string, string>}> $stages
     */
    public function __construct(array $stages)
    {
        $this->stages = array_map(static function (array $stage): array {
            return [
                'preset' => $stage['preset'],
                'map' => $stage['map'] ?? [],
            ];
        }, $stages);
    }

    /**
     * Executes the chain with the given initial input.
     *
     * Each stage runs its preset with the current input. The output
     * is transformed via the stage's key map before becoming input
     * for the next stage. If any stage returns a WP_Error, the chain
     * stops immediately and returns that error.
     *
     * @since 1.0.0
     *
     * @param array $input Initial input for the first stage.
     * @return string|array|\WP_Error Final output from the last stage.
     */
    public function execute(array $input)
    {
        $currentInput = $input;

        foreach ($this->stages as $index => $stage) {
            /** @var BasePreset $preset */
            $preset = $stage['preset'];
            $map = $stage['map'];

            $output = $preset->execute($currentInput);

            if ($output instanceof \WP_Error) {
                return $output;
            }

            // Last stage: return raw output, no mapping needed.
            if ($index === count($this->stages) - 1) {
                return $output;
            }

            // Normalize string output to array for mapping.
            if (is_string($output)) {
                $output = ['result' => $output];
            }

            // Apply key map: transform output keys into input keys for next stage.
            $currentInput = [];
            if (!empty($map)) {
                foreach ($map as $fromKey => $toKey) {
                    if (isset($output[$fromKey])) {
                        $currentInput[$toKey] = $output[$fromKey];
                    }
                }
            } else {
                // No map: pass output as-is.
                $currentInput = $output;
            }
        }

        return $currentInput;
    }
}
