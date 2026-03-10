<?php

/**
 * Example: Image Generation Preset
 *
 * A preset that generates images instead of text. Demonstrates how to
 * override execute() to use generateImage() with ModelConfig for
 * orientation control.
 *
 * Required file structure in your plugin:
 *
 *   your-plugin/
 *   ├── your-plugin.php
 *   ├── src/
 *   │   └── Presets/
 *   │       └── ImageGeneratePreset.php   <-- this class
 *   └── templates/
 *       └── presets/
 *           └── image-generate.php        <-- prompt template
 *
 * @package WordPress\InfomaniakAiToolkit\Examples
 */

declare(strict_types=1);

namespace YourPlugin\Presets;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\InfomaniakAiToolkit\Presets\BasePreset;

/**
 * Generates an image from a text description.
 */
class ImageGeneratePreset extends BasePreset
{
    public function name(): string
    {
        return 'image-generate';
    }

    public function label(): string
    {
        return __('Generate Image', 'your-plugin');
    }

    public function description(): string
    {
        return __('Generates an image from a text description.', 'your-plugin');
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'A detailed description of the image to generate.',
                ],
                'orientation' => [
                    'type' => 'string',
                    'description' => 'The image orientation: square, landscape, or portrait.',
                    'default' => 'square',
                ],
            ],
            'required' => ['prompt'],
        ];
    }

    protected function templateName(): string
    {
        return 'image-generate';
    }

    protected function templateData(array $input): array
    {
        return [
            'prompt' => $input['prompt'] ?? '',
        ];
    }

    /**
     * Override modelType() to indicate this preset uses image models.
     *
     * This is used by the admin UI to filter the model selector,
     * showing only image-capable models when this preset is selected.
     */
    public function modelType(): string
    {
        return 'image';
    }

    protected function category(): string
    {
        return 'media';
    }

    protected function outputAbilitySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data_uri' => [
                    'type' => 'string',
                    'description' => 'The generated image as a data URI.',
                ],
                'mime_type' => [
                    'type' => 'string',
                    'description' => 'The MIME type of the generated image.',
                ],
            ],
        ];
    }

    protected function annotations(): array
    {
        return [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => false,
        ];
    }

    /**
     * Override execute() to use generateImage() instead of generateText().
     *
     * Image generation requires:
     * 1. A ModelConfig to set orientation (square, landscape, portrait)
     * 2. Calling generateImage() instead of generateText()
     * 3. Extracting the data URI and MIME type from the returned File DTO
     *
     * The tracking context (setTrackingContext/clearTrackingContext) ensures
     * usage is attributed to this preset.
     */
    public function execute(array $input)
    {
        if (!class_exists(AiClient::class)) {
            return new \WP_Error(
                'ai_unavailable',
                __('AI Client is not available.', 'your-plugin')
            );
        }

        $this->setTrackingContext();

        try {
            $data = $this->templateData($input);

            $promptText = $this->render(
                $this->templatesPath() . '/' . $this->templateName() . '.php',
                $data
            );

            if (empty($promptText)) {
                return new \WP_Error(
                    'preset_template_error',
                    __('Failed to render prompt template.', 'your-plugin')
                );
            }

            // Map orientation string to enum.
            $orientation = $input['orientation'] ?? 'square';
            $orientationEnum = match ($orientation) {
                'landscape' => MediaOrientationEnum::landscape(),
                'portrait' => MediaOrientationEnum::portrait(),
                default => MediaOrientationEnum::square(),
            };

            $config = new ModelConfig();
            $config->setOutputMediaOrientation($orientationEnum);

            $builder = AiClient::prompt($promptText)
                ->usingProvider($this->provider())
                ->usingModelConfig($config);

            $modelPref = $this->modelPreference();
            if ($modelPref !== null) {
                $builder->usingModelPreference($modelPref);
            }

            try {
                $file = $builder->generateImage();
            } catch (\Throwable $e) {
                return new \WP_Error(
                    'ai_generation_error',
                    $e->getMessage()
                );
            }

            return [
                'data_uri' => $file->getDataUri(),
                'mime_type' => $file->getMimeType(),
            ];
        } finally {
            $this->clearTrackingContext();
        }
    }
}

/*
 * --------------------------------------------------------------------------
 * Template: templates/presets/image-generate.php
 * --------------------------------------------------------------------------
 *
 * <?= $prompt ?>
 *
 * --------------------------------------------------------------------------
 * Registration in your-plugin.php:
 * --------------------------------------------------------------------------
 *
 * add_action('wp_abilities_api_init', function() {
 *     if (!class_exists(\WordPress\InfomaniakAiToolkit\Presets\BasePreset::class)) {
 *         return;
 *     }
 *     $preset = new \YourPlugin\Presets\ImageGeneratePreset();
 *     $preset->registerAsAbility();
 * });
 *
 * --------------------------------------------------------------------------
 * Usage in PHP:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\ImageGeneratePreset();
 * $result = $preset->execute([
 *     'prompt' => 'A serene mountain landscape at sunset',
 *     'orientation' => 'landscape',
 * ]);
 *
 * if (!is_wp_error($result)) {
 *     echo '<img src="' . esc_attr($result['data_uri']) . '" />';
 * }
 *
 * --------------------------------------------------------------------------
 * Runtime model override:
 * --------------------------------------------------------------------------
 *
 * $preset = new \YourPlugin\Presets\ImageGeneratePreset();
 * $preset->setModelPreference('your-preferred-model');
 * $result = $preset->execute(['prompt' => 'A red cat']);
 */
