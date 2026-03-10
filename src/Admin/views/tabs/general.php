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
 * @package WordPress\InfomaniakAiProvider
 */

defined('ABSPATH') || exit;

$productId    = $data['productId'] ?? '';
$isFromOption = $data['isFromOption'] ?? true;
$source       = $data['source'] ?? 'option';
?>
<div class="ik-section ik-section--split">
	<div class="ik-section__aside">
		<h2 class="ik-section__header">
			<?php esc_html_e('Product ID', 'ai-provider-for-infomaniak'); ?>
			<?php if ($source === 'constant') : ?>
				<span class="ik-badge"><?php esc_html_e('Constant', 'ai-provider-for-infomaniak'); ?></span>
			<?php elseif ($source === 'filter') : ?>
				<span class="ik-badge"><?php esc_html_e('Filter', 'ai-provider-for-infomaniak'); ?></span>
			<?php endif; ?>
		</h2>
		<p class="ik-section__description">
			<?php esc_html_e('Your Infomaniak AI product identifier. Required to connect to the AI API.', 'ai-provider-for-infomaniak'); ?>
		</p>
	</div>

	<div class="ik-section__body">
		<div class="ik-field">
			<label class="ik-field__label" for="infomaniak_ai_product_id">
				<?php esc_html_e('Product ID', 'ai-provider-for-infomaniak'); ?>
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
					<?php esc_html_e('Currently set via the INFOMANIAK_AI_PRODUCT_ID constant.', 'ai-provider-for-infomaniak'); ?>
				<?php elseif ($source === 'filter') : ?>
					<?php esc_html_e('Currently set via the infomaniak_ai_product_id filter.', 'ai-provider-for-infomaniak'); ?>
				<?php else : ?>
					<?php esc_html_e('Find it in your Infomaniak Manager under AI Tools.', 'ai-provider-for-infomaniak'); ?>
				<?php endif; ?>
			</span>
		</div>

		<div class="ik-field">
			<button type="button" id="ik-test-connection" class="ik-btn ik-btn--outline"
				data-nonce="<?php echo esc_attr(wp_create_nonce('infomaniak_ai_test_connection')); ?>"
				data-label="<?php esc_attr_e('Test Connection', 'ai-provider-for-infomaniak'); ?>"
				data-loading="<?php esc_attr_e('Testing...', 'ai-provider-for-infomaniak'); ?>">
				<?php esc_html_e('Test Connection', 'ai-provider-for-infomaniak'); ?>
			</button>
			<span id="ik-test-feedback" class="ik-feedback"></span>
		</div>
	</div>
</div>
