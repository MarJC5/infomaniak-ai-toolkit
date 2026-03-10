<?php
/**
 * Settings page layout template.
 *
 * Variables available:
 *   $currentTab string  Active tab slug.
 *   $tabs       array   Tab slug => translated label.
 *   $data       array   Data for the current tab.
 *
 * @since 1.0.0
 *
 * @package WordPress\InfomaniakAiToolkit
 */

defined('ABSPATH') || exit;
?>
<div class="wrap infomaniak-ai-settings">
	<div class="ik-layout">
		<nav class="ik-sidebar">
			<div class="ik-sidebar__header">
				<h1 class="ik-sidebar__title"><?php esc_html_e('Infomaniak AI', 'infomaniak-ai-toolkit'); ?></h1>
				<p class="ik-sidebar__description">
					<?php esc_html_e('Configure your AI provider settings.', 'infomaniak-ai-toolkit'); ?>
				</p>
			</div>

			<ul class="ik-nav">
				<?php foreach ($tabs as $slug => $label) : ?>
					<li>
						<a href="<?php echo esc_url(admin_url('options-general.php?page=infomaniak-ai&tab=' . $slug)); ?>"
						   class="ik-nav__item <?php echo $currentTab === $slug ? 'ik-nav__item--active' : ''; ?>">
							<?php echo esc_html($label); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div class="ik-content">
			<?php $tabFile = __DIR__ . '/tabs/' . $currentTab . '.php'; ?>
			<?php if ($currentTab === 'usage' || $currentTab === 'commands') : ?>
				<?php
				if (file_exists($tabFile)) {
					include $tabFile;
				}
				?>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php settings_fields('infomaniak_ai'); ?>
					<?php
					if (file_exists($tabFile)) {
						include $tabFile;
					}
					?>
					<button type="submit" class="ik-btn">
						<?php esc_html_e('Save Changes', 'infomaniak-ai-toolkit'); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
</div>
