<?php
/**
 * General tab template — Product ID configuration.
 *
 * Variables available from $data:
 *   $data['productId']    string  Current product ID value.
 *   $data['isFromOption'] bool    Whether the value comes from the DB option.
 *   $data['source']       string  Source: 'option', 'constant', or 'filter'.
 *
 * @since 1.0.0
 *
 * @package WordPress\InfomaniakAiToolkit
 */

defined('ABSPATH') || exit;

$productId    = $data['productId'] ?? '';
$isFromOption = $data['isFromOption'] ?? true;
$source       = $data['source'] ?? 'option';
?>
<div class="ik-section ik-section--split">
	<div class="ik-section__aside">
		<h2 class="ik-section__header">
			<?php esc_html_e('Product ID', 'infomaniak-ai-toolkit'); ?>
			<?php if ($source === 'constant') : ?>
				<span class="ik-badge"><?php esc_html_e('Constant', 'infomaniak-ai-toolkit'); ?></span>
			<?php elseif ($source === 'filter') : ?>
				<span class="ik-badge"><?php esc_html_e('Filter', 'infomaniak-ai-toolkit'); ?></span>
			<?php endif; ?>
		</h2>
		<p class="ik-section__description">
			<?php esc_html_e('Your Infomaniak AI product identifier. Required to connect to the AI API.', 'infomaniak-ai-toolkit'); ?>
		</p>
	</div>

	<div class="ik-section__body">
		<div class="ik-field">
			<label class="ik-field__label" for="infomaniak_ai_product_id">
				<?php esc_html_e('Product ID', 'infomaniak-ai-toolkit'); ?>
			</label>
			<input
				type="text"
				id="infomaniak_ai_product_id"
				name="infomaniak_ai_product_id"
				value="<?php echo esc_attr($productId); ?>"
				class="ik-input"
				<?php echo $isFromOption ? '' : 'disabled'; ?>
			/>
			<span class="ik-field__hint">
				<?php if ($source === 'constant') : ?>
					<?php esc_html_e('Currently set via the INFOMANIAK_AI_PRODUCT_ID constant.', 'infomaniak-ai-toolkit'); ?>
				<?php elseif ($source === 'filter') : ?>
					<?php esc_html_e('Currently set via the infomaniak_ai_product_id filter.', 'infomaniak-ai-toolkit'); ?>
				<?php else : ?>
					<?php esc_html_e('Find it in your Infomaniak Manager under AI Tools.', 'infomaniak-ai-toolkit'); ?>
				<?php endif; ?>
			</span>
		</div>

		<div class="ik-field">
			<button type="button" id="ik-test-connection" class="ik-btn ik-btn--outline"
				data-nonce="<?php echo esc_attr(wp_create_nonce('infomaniak_ai_test_connection')); ?>"
				data-label="<?php esc_attr_e('Test Connection', 'infomaniak-ai-toolkit'); ?>"
				data-loading="<?php esc_attr_e('Testing...', 'infomaniak-ai-toolkit'); ?>">
				<?php esc_html_e('Test Connection', 'infomaniak-ai-toolkit'); ?>
			</button>
			<span id="ik-test-feedback" class="ik-feedback"></span>
		</div>
	</div>
</div>
