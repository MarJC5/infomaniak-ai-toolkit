<?php
/**
 * Commands list template.
 *
 * Variables available from $data:
 *   $data['commands'] array<string, array{command: MarkdownCommand, source: 'file'|'db'}>
 *
 * @since 1.2.0
 *
 * @package WordPress\InfomaniakAiProvider
 */

defined('ABSPATH') || exit;

$commands = $data['commands'] ?? [];
$baseUrl  = admin_url('options-general.php?page=infomaniak-ai&tab=commands');
?>
<div class="ik-section">
	<div class="ik-section__header-row">
		<h2 class="ik-section__header">
			<?php esc_html_e('Commands', 'ai-provider-for-infomaniak'); ?>
		</h2>
		<a href="<?php echo esc_url($baseUrl . '&action=new'); ?>" class="ik-btn ik-btn--outline">
			<?php esc_html_e('New Command', 'ai-provider-for-infomaniak'); ?>
		</a>
	</div>
	<p class="ik-section__description">
		<?php esc_html_e('Manage AI commands. File-based commands are read-only; custom commands can be edited and deleted.', 'ai-provider-for-infomaniak'); ?>
	</p>

	<?php if (empty($commands)) : ?>
		<div class="ik-empty-state">
			<div class="ik-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16">
					<polyline points="152 32 152 88 208 88"/>
					<polyline points="152 128 176 152 152 176"/>
					<polyline points="104 128 80 152 104 176"/>
					<path d="M200,224a8,8,0,0,0,8-8V88L152,32H56a8,8,0,0,0-8,8V216a8,8,0,0,0,8,8Z"/>
				</svg>
			</div>
			<h3 class="ik-empty-state__title">
				<?php esc_html_e('No commands yet', 'ai-provider-for-infomaniak'); ?>
			</h3>
			<p class="ik-empty-state__description">
				<?php esc_html_e('Create your first AI command or place .md files in an ai-commands/ directory within your plugin or theme.', 'ai-provider-for-infomaniak'); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="ik-table-wrap" id="ik-commands-list">
			<table class="ik-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Name', 'ai-provider-for-infomaniak'); ?></th>
						<th><?php esc_html_e('Description', 'ai-provider-for-infomaniak'); ?></th>
						<th><?php esc_html_e('Source', 'ai-provider-for-infomaniak'); ?></th>
						<th><?php esc_html_e('Variables', 'ai-provider-for-infomaniak'); ?></th>
						<th><?php esc_html_e('Actions', 'ai-provider-for-infomaniak'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($commands as $slug => $entry) :
						$command = $entry['command'];
						$source  = $entry['source'];
						$schema  = $command->inputSchema();
						$vars    = !empty($schema['properties']) ? array_keys($schema['properties']) : [];
						// Remove conversation_id from displayed variables.
						$vars = array_filter($vars, static fn($v) => $v !== 'conversation_id' && $v !== 'conversation_history');
					?>
						<tr data-slug="<?php echo esc_attr($slug); ?>">
							<td>
								<strong><?php echo esc_html($slug); ?></strong>
							</td>
							<td>
								<?php echo esc_html(wp_trim_words($command->description(), 10, '...')); ?>
							</td>
							<td>
								<?php if ($source === 'file') : ?>
									<span class="ik-badge ik-badge--file"><?php esc_html_e('File', 'ai-provider-for-infomaniak'); ?></span>
								<?php else : ?>
									<span class="ik-badge ik-badge--custom"><?php esc_html_e('Custom', 'ai-provider-for-infomaniak'); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($vars)) : ?>
									<code><?php echo esc_html(implode(', ', $vars)); ?></code>
								<?php else : ?>
									<span style="color: var(--color-text-secondary);">&mdash;</span>
								<?php endif; ?>
							</td>
							<td>
								<div class="ik-cmd-actions">
									<?php if ($source === 'db') : ?>
										<a href="<?php echo esc_url($baseUrl . '&action=edit&command=' . urlencode($slug)); ?>">
											<?php esc_html_e('Edit', 'ai-provider-for-infomaniak'); ?>
										</a>
										<button type="button"
											class="ik-cmd-delete"
											data-slug="<?php echo esc_attr($slug); ?>">
											<?php esc_html_e('Delete', 'ai-provider-for-infomaniak'); ?>
										</button>
									<?php else : ?>
										<span style="color: var(--color-text-secondary); font-size: var(--text-xs);">
											<?php esc_html_e('Read-only', 'ai-provider-for-infomaniak'); ?>
										</span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
