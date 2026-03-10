<?php
/**
 * Commands create/edit form template.
 *
 * Variables available from $data:
 *   $data['view']    string  'new' or 'edit'.
 *   $data['slug']    string  Command slug (edit only).
 *   $data['command'] array   Command data from DB (edit only).
 *   $data['models']  array   Available models list.
 *
 * @since 1.2.0
 *
 * @package WordPress\InfomaniakAiProvider
 */

defined('ABSPATH') || exit;

$isNew   = ($data['view'] ?? 'new') === 'new';
$slug    = $data['slug'] ?? '';
$command = $data['command'] ?? [];
$models  = $data['models'] ?? [];
$baseUrl = admin_url('options-general.php?page=infomaniak-ai&tab=commands');

// Extract values for pre-filling.
$label          = $command['label'] ?? '';
$description    = $command['description'] ?? '';
$promptTemplate = $command['prompt_template'] ?? '';
$systemPrompt   = $command['system_prompt'] ?? '';
$temperature    = $command['temperature'] ?? '0.7';
$maxTokens      = $command['max_tokens'] ?? '1000';
$model          = $command['model'] ?? '';
$modelType      = $command['model_type'] ?? 'llm';
$category       = $command['category'] ?? 'content';
$permission     = $command['permission'] ?? 'edit_posts';
$conversational = !empty($command['conversational']);
$provider       = $command['provider'] ?? 'infomaniak';
?>
<div id="ik-command-form">
	<div class="ik-section">
		<div class="ik-section__header-row">
			<h2 class="ik-section__header">
				<?php $isNew
					? esc_html_e('New Command', 'ai-provider-for-infomaniak')
					: esc_html_e('Edit Command', 'ai-provider-for-infomaniak');
				?>
			</h2>
			<a href="<?php echo esc_url($baseUrl); ?>" class="ik-btn ik-btn--outline">
				<?php esc_html_e('Back to list', 'ai-provider-for-infomaniak'); ?>
			</a>
		</div>
		<p class="ik-section__description">
			<?php $isNew
				? esc_html_e('Create a new AI command. Use {{variable}} placeholders in the prompt template.', 'ai-provider-for-infomaniak')
				: esc_html_e('Edit your AI command. Changes take effect immediately.', 'ai-provider-for-infomaniak');
			?>
		</p>
	</div>

	<input type="hidden" id="ik-cmd-is-new" value="<?php echo $isNew ? '1' : '0'; ?>" />

	<!-- Section 1: Identity -->
	<div class="ik-section">
		<h3 class="ik-section__header"><?php esc_html_e('Identity', 'ai-provider-for-infomaniak'); ?></h3>

		<div class="ik-field-grid">
			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-slug">
					<?php esc_html_e('Name (slug)', 'ai-provider-for-infomaniak'); ?>
				</label>
				<input
					type="text"
					id="ik-cmd-slug"
					class="ik-input"
					value="<?php echo esc_attr($slug); ?>"
					placeholder="my-command"
					pattern="[a-z0-9-]+"
					<?php echo !$isNew ? 'disabled' : ''; ?>
				/>
				<span class="ik-field__hint">
					<?php esc_html_e('Lowercase letters, numbers, and hyphens only.', 'ai-provider-for-infomaniak'); ?>
				</span>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-label">
					<?php esc_html_e('Label', 'ai-provider-for-infomaniak'); ?>
				</label>
				<input
					type="text"
					id="ik-cmd-label"
					class="ik-input"
					value="<?php echo esc_attr($label); ?>"
					placeholder="My Command"
				/>
				<span class="ik-field__hint">
					<?php esc_html_e('Optional display name. Auto-generated from slug if empty.', 'ai-provider-for-infomaniak'); ?>
				</span>
			</div>
		</div>

		<div class="ik-field" style="margin-top: var(--spacing-md);">
			<label class="ik-field__label" for="ik-cmd-description">
				<?php esc_html_e('Description', 'ai-provider-for-infomaniak'); ?>
			</label>
			<input
				type="text"
				id="ik-cmd-description"
				class="ik-input"
				style="max-width: 100%;"
				value="<?php echo esc_attr($description); ?>"
				placeholder="<?php esc_attr_e('Briefly describe what this command does.', 'ai-provider-for-infomaniak'); ?>"
				required
			/>
		</div>
	</div>

	<!-- Section 2: Prompt -->
	<div class="ik-section">
		<h3 class="ik-section__header"><?php esc_html_e('Prompt', 'ai-provider-for-infomaniak'); ?></h3>

		<div class="ik-field">
			<label class="ik-field__label" for="ik-cmd-prompt">
				<?php esc_html_e('Prompt Template', 'ai-provider-for-infomaniak'); ?>
			</label>
			<textarea
				id="ik-cmd-prompt"
				class="ik-editor"
				rows="12"
				placeholder="<?php esc_attr_e("Summarize the following content:\n\n{{content}}", 'ai-provider-for-infomaniak'); ?>"
				required
			><?php echo esc_textarea($promptTemplate); ?></textarea>
			<span class="ik-field__hint">
				<?php esc_html_e('Use {{variable}} for dynamic placeholders. They become required input fields.', 'ai-provider-for-infomaniak'); ?>
			</span>
		</div>

		<div class="ik-field">
			<label class="ik-field__label" for="ik-cmd-system">
				<?php esc_html_e('System Prompt', 'ai-provider-for-infomaniak'); ?>
			</label>
			<textarea
				id="ik-cmd-system"
				class="ik-editor ik-editor--small"
				rows="4"
				placeholder="<?php esc_attr_e('Optional. Sets the AI behavior and persona.', 'ai-provider-for-infomaniak'); ?>"
			><?php echo esc_textarea($systemPrompt); ?></textarea>
		</div>
	</div>

	<!-- Section 3: Settings -->
	<div class="ik-section">
		<h3 class="ik-section__header"><?php esc_html_e('Settings', 'ai-provider-for-infomaniak'); ?></h3>

		<div class="ik-field-grid">
			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-temperature">
					<?php esc_html_e('Temperature', 'ai-provider-for-infomaniak'); ?>
				</label>
				<input
					type="number"
					id="ik-cmd-temperature"
					class="ik-input"
					value="<?php echo esc_attr($temperature); ?>"
					min="0"
					max="2"
					step="0.1"
				/>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-max-tokens">
					<?php esc_html_e('Max Tokens', 'ai-provider-for-infomaniak'); ?>
				</label>
				<input
					type="number"
					id="ik-cmd-max-tokens"
					class="ik-input"
					value="<?php echo esc_attr($maxTokens); ?>"
					min="1"
					max="100000"
					step="1"
				/>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-category">
					<?php esc_html_e('Category', 'ai-provider-for-infomaniak'); ?>
				</label>
				<select id="ik-cmd-category" class="ik-input">
					<option value="content" <?php selected($category, 'content'); ?>><?php esc_html_e('Content', 'ai-provider-for-infomaniak'); ?></option>
					<option value="analysis" <?php selected($category, 'analysis'); ?>><?php esc_html_e('Analysis', 'ai-provider-for-infomaniak'); ?></option>
					<option value="translation" <?php selected($category, 'translation'); ?>><?php esc_html_e('Translation', 'ai-provider-for-infomaniak'); ?></option>
					<option value="coding" <?php selected($category, 'coding'); ?>><?php esc_html_e('Coding', 'ai-provider-for-infomaniak'); ?></option>
					<option value="image" <?php selected($category, 'image'); ?>><?php esc_html_e('Image', 'ai-provider-for-infomaniak'); ?></option>
				</select>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-permission">
					<?php esc_html_e('Permission', 'ai-provider-for-infomaniak'); ?>
				</label>
				<select id="ik-cmd-permission" class="ik-input">
					<option value="edit_posts" <?php selected($permission, 'edit_posts'); ?>><?php esc_html_e('edit_posts', 'ai-provider-for-infomaniak'); ?></option>
					<option value="publish_posts" <?php selected($permission, 'publish_posts'); ?>><?php esc_html_e('publish_posts', 'ai-provider-for-infomaniak'); ?></option>
					<option value="manage_options" <?php selected($permission, 'manage_options'); ?>><?php esc_html_e('manage_options', 'ai-provider-for-infomaniak'); ?></option>
				</select>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-model-type">
					<?php esc_html_e('Model Type', 'ai-provider-for-infomaniak'); ?>
				</label>
				<select id="ik-cmd-model-type" class="ik-input">
					<option value="llm" <?php selected($modelType, 'llm'); ?>><?php esc_html_e('LLM (Text)', 'ai-provider-for-infomaniak'); ?></option>
					<option value="image" <?php selected($modelType, 'image'); ?>><?php esc_html_e('Image', 'ai-provider-for-infomaniak'); ?></option>
				</select>
			</div>

			<div class="ik-field">
				<label class="ik-field__label" for="ik-cmd-model">
					<?php esc_html_e('Model', 'ai-provider-for-infomaniak'); ?>
				</label>
				<select id="ik-cmd-model" class="ik-input">
					<option value=""><?php esc_html_e('Default', 'ai-provider-for-infomaniak'); ?></option>
					<?php foreach ($models as $m) : ?>
						<option value="<?php echo esc_attr($m['id']); ?>" <?php selected($model, $m['id']); ?>>
							<?php echo esc_html($m['name']); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="ik-field" style="margin-top: var(--spacing-md);">
			<label class="ik-checkbox">
				<input
					type="checkbox"
					id="ik-cmd-conversational"
					<?php checked($conversational); ?>
				/>
				<?php esc_html_e('Conversational (multi-turn)', 'ai-provider-for-infomaniak'); ?>
			</label>
		</div>
	</div>

	<!-- Section 4: Preview -->
	<div class="ik-section">
		<h3 class="ik-section__header"><?php esc_html_e('Preview', 'ai-provider-for-infomaniak'); ?></h3>

		<div class="ik-field-grid">
			<div class="ik-preview">
				<span class="ik-preview__label"><?php esc_html_e('Detected Variables', 'ai-provider-for-infomaniak'); ?></span>
				<div class="ik-preview__content" id="ik-preview-vars">
					<?php esc_html_e('Type in the prompt template to detect variables...', 'ai-provider-for-infomaniak'); ?>
				</div>
			</div>

			<div class="ik-preview">
				<span class="ik-preview__label"><?php esc_html_e('Generated Input Schema', 'ai-provider-for-infomaniak'); ?></span>
				<div class="ik-preview__content" id="ik-preview-schema">
					<?php esc_html_e('Schema will appear here...', 'ai-provider-for-infomaniak'); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Actions -->
	<div class="ik-section">
		<button type="button" id="ik-cmd-save" class="ik-btn"
			data-saving="<?php esc_attr_e('Saving...', 'ai-provider-for-infomaniak'); ?>">
			<?php $isNew
				? esc_html_e('Create Command', 'ai-provider-for-infomaniak')
				: esc_html_e('Save Command', 'ai-provider-for-infomaniak');
			?>
		</button>
		<span id="ik-cmd-feedback" class="ik-feedback"></span>
	</div>
</div>
