<?php
/**
 * Rate Limits tab template — Per-role rate limit configuration.
 *
 * Variables available from $data:
 *   $data['limits']       array   Role => ['limit' => int, 'window' => string].
 *   $data['windows']      array   Valid window values.
 *   $data['windowLabels'] array   Window => translated label.
 *   $data['optionName']   string  WordPress option name for form inputs.
 *
 * @since 1.0.0
 *
 * @package WordPress\InfomaniakAiToolkit
 */

defined('ABSPATH') || exit;

$limits       = $data['limits'] ?? [];
$windows      = $data['windows'] ?? [];
$windowLabels = $data['windowLabels'] ?? [];
$optionName   = $data['optionName'] ?? '';
?>
<div class="ik-section">
	<h2 class="ik-section__header">
		<?php esc_html_e('Rate Limits', 'infomaniak-ai-toolkit'); ?>
	</h2>
	<p class="ik-section__description">
		<?php esc_html_e('Configure the maximum number of AI requests per role within a time window. Set to 0 for unlimited.', 'infomaniak-ai-toolkit'); ?>
	</p>

	<div class="ik-table-wrap">
	<table class="ik-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Role', 'infomaniak-ai-toolkit'); ?></th>
				<th><?php esc_html_e('Limit', 'infomaniak-ai-toolkit'); ?></th>
				<th><?php esc_html_e('Window', 'infomaniak-ai-toolkit'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($limits as $role => $config) : ?>
				<tr>
					<td><?php echo esc_html(ucfirst($role)); ?></td>
					<td>
						<input
							type="number"
							name="<?php echo esc_attr("{$optionName}[{$role}][limit]"); ?>"
							value="<?php echo esc_attr((string) $config['limit']); ?>"
							min="0"
							step="1"
							class="ik-input ik-input--small"
						/>
					</td>
					<td>
						<select
							name="<?php echo esc_attr("{$optionName}[{$role}][window]"); ?>"
							class="ik-select"
						>
							<?php foreach ($windows as $w) : ?>
								<option
									value="<?php echo esc_attr($w); ?>"
									<?php selected($config['window'], $w); ?>
								><?php echo esc_html($windowLabels[$w] ?? $w); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>

	<p class="ik-field__hint" style="margin-top: var(--spacing-md);">
		<?php esc_html_e('Limits are enforced per user based on their primary role. Guest users are rate-limited by IP.', 'infomaniak-ai-toolkit'); ?>
	</p>
</div>
