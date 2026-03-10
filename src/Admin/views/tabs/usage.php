<?php
/**
 * Usage tab template — Dashboard with metrics and sparklines.
 *
 * Variables available from $data:
 *   $data['hasData']        bool    Whether any usage data exists.
 *   $data['dailyTotals']    array   Daily breakdown [{date, total_tokens, request_count}].
 *   $data['totalTokens']    int     Total tokens in the last 30 days.
 *   $data['totalRequests']  int     Total request count.
 *   $data['tokensToday']    int     Tokens used today.
 *   $data['requestsToday']  int     Requests made today.
 *   $data['topModels']      array   Top models [{model_name, total_tokens, request_count}].
 *   $data['topPresets']     array   Top presets [{preset_name, total_tokens, request_count}].
 *   $data['sparklineData']  int[]   Array of daily token values for sparkline.
 *
 * @since 1.1.0
 *
 * @package WordPress\InfomaniakAiProvider
 */

defined('ABSPATH') || exit;

use WordPress\InfomaniakAiProvider\Admin\SvgSparkline;

// Empty state.
if (empty($data['hasData'])) : ?>
<div class="ik-section">
	<div class="ik-empty-state">
		<div class="ik-empty-state__icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="16">
				<polyline points="48 208 48 136 96 136"/>
				<line x1="224" y1="208" x2="32" y2="208"/>
				<polyline points="96 208 96 88 152 88"/>
				<polyline points="152 208 152 40 208 40 208 208"/>
			</svg>
		</div>
		<h3 class="ik-empty-state__title">
			<?php esc_html_e('No usage data yet', 'ai-provider-for-infomaniak'); ?>
		</h3>
		<p class="ik-empty-state__description">
			<?php esc_html_e('Usage statistics will appear here once AI requests are made through the plugin.', 'ai-provider-for-infomaniak'); ?>
		</p>
	</div>
</div>
<?php return; endif; ?>

<div class="ik-section">
	<h2 class="ik-section__header">
		<?php esc_html_e('Overview', 'ai-provider-for-infomaniak'); ?>
	</h2>
	<p class="ik-section__description">
		<?php esc_html_e('AI usage for the last 30 days.', 'ai-provider-for-infomaniak'); ?>
	</p>

	<div class="ik-card-grid">
		<div class="ik-card">
			<span class="ik-card__label"><?php esc_html_e('Total Tokens', 'ai-provider-for-infomaniak'); ?></span>
			<div class="ik-card__row">
				<span class="ik-card__value"><?php echo esc_html(number_format_i18n($data['totalTokens'])); ?></span>
				<div class="ik-card__sparkline">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG generated internally
					echo SvgSparkline::render($data['sparklineData']);
					?>
				</div>
			</div>
			<span class="ik-card__subtitle"><?php esc_html_e('Last 30 days', 'ai-provider-for-infomaniak'); ?></span>
		</div>

		<div class="ik-card">
			<span class="ik-card__label"><?php esc_html_e('Requests', 'ai-provider-for-infomaniak'); ?></span>
			<div class="ik-card__row">
				<span class="ik-card__value"><?php echo esc_html(number_format_i18n($data['totalRequests'])); ?></span>
				<div class="ik-card__sparkline">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG generated internally
					echo SvgSparkline::render(array_column($data['dailyTotals'], 'request_count'));
					?>
				</div>
			</div>
			<span class="ik-card__subtitle"><?php esc_html_e('Last 30 days', 'ai-provider-for-infomaniak'); ?></span>
		</div>

		<div class="ik-card">
			<span class="ik-card__label"><?php esc_html_e('Tokens Today', 'ai-provider-for-infomaniak'); ?></span>
			<div class="ik-card__row">
				<span class="ik-card__value"><?php echo esc_html(number_format_i18n($data['tokensToday'])); ?></span>
			</div>
			<span class="ik-card__subtitle">
				<?php
				echo esc_html(sprintf(
					/* translators: %s: number of requests */
					_n('%s request', '%s requests', $data['requestsToday'], 'ai-provider-for-infomaniak'),
					number_format_i18n($data['requestsToday'])
				));
				?>
			</span>
		</div>
	</div>
</div>

<?php if (!empty($data['topModels'])) : ?>
<div class="ik-section">
	<h2 class="ik-section__header">
		<?php esc_html_e('Top Models', 'ai-provider-for-infomaniak'); ?>
	</h2>

	<div class="ik-table-wrap">
	<table class="ik-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Model', 'ai-provider-for-infomaniak'); ?></th>
				<th><?php esc_html_e('Tokens', 'ai-provider-for-infomaniak'); ?></th>
				<th><?php esc_html_e('Requests', 'ai-provider-for-infomaniak'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($data['topModels'] as $model) : ?>
				<tr>
					<td><?php echo esc_html($model['model_name'] ?: $model['model_id'] ?? '—'); ?></td>
					<td><?php echo esc_html(number_format_i18n($model['total_tokens'])); ?></td>
					<td><?php echo esc_html(number_format_i18n($model['request_count'])); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
</div>
<?php endif; ?>

<?php if (!empty($data['topPresets'])) : ?>
<div class="ik-section">
	<h2 class="ik-section__header">
		<?php esc_html_e('Top Presets', 'ai-provider-for-infomaniak'); ?>
	</h2>

	<div class="ik-table-wrap">
	<table class="ik-table">
		<thead>
			<tr>
				<th><?php esc_html_e('Preset', 'ai-provider-for-infomaniak'); ?></th>
				<th><?php esc_html_e('Tokens', 'ai-provider-for-infomaniak'); ?></th>
				<th><?php esc_html_e('Requests', 'ai-provider-for-infomaniak'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($data['topPresets'] as $preset) : ?>
				<tr>
					<td><?php echo esc_html($preset['preset_name']); ?></td>
					<td><?php echo esc_html(number_format_i18n($preset['total_tokens'])); ?></td>
					<td><?php echo esc_html(number_format_i18n($preset['request_count'])); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	</div>
</div>
<?php endif; ?>
