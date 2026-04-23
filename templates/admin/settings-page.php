<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Settings Page Template
 *
 * @var array $args Template arguments passed from SettingsView.
 *            - $args['current_tab'] string
 *            - $args['tabs']        array
 *            - $args['renderer']    \MHMRentiva\Admin\Settings\View\TabRendererInterface|null
 */

if (! defined('ABSPATH')) {
	exit;
}

$current_tab = $args['current_tab'] ?? 'general';
$tabs        = $args['tabs'] ?? array();
$renderer    = $args['renderer'] ?? null;
?>
<div class="wrap mhm-settings-page">
	<div class="mhm-settings-header">
		<?php
		if (isset($args['header_html'])) {
			echo $args['header_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
		}
		?>
	</div>

	<div class="mhm-settings-notices">
		<?php
		// Display Pro feature notices if available
		if (class_exists('\MHMRentiva\Admin\Core\ProFeatureNotice')) {
			\MHMRentiva\Admin\Core\ProFeatureNotice::displayPageProNotice('settings');
		}

		// Standard WordPress settings messages
		settings_errors();
		?>
	</div>

	<div class="mhm-settings-layout">
		<!-- Sidebar Navigation -->
		<div class="mhm-settings-sidebar">
			<nav class="mhm-settings-nav">
				<?php foreach ($tabs as $tab_key => $tab_label) : ?>
					<a href="<?php echo esc_url(add_query_arg('tab', $tab_key)); ?>"
						class="mhm-settings-nav-item <?php echo $current_tab === $tab_key ? 'active' : ''; ?>">
						<?php echo esc_html($tab_label); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		</div>

		<!-- Main Content Area -->
		<div class="mhm-settings-content">
			<?php if (null !== $renderer) : ?>
				<div class="mhm-settings-tab-container">
					<?php
					// Delegate form wrapping decision to the renderer itself
					if ($renderer->should_wrap_with_form()) :
						?>
						<div class="mhm-settings-form-container">
							<form method="post" action="options.php" class="mhm-settings-form" id="mhm-settings-main-form">
								<?php
								ob_start();
								settings_fields(\MHMRentiva\Admin\Settings\Core\SettingsCore::PAGE);

								// Track active tab for specific sanitization logic
								echo '<input type="hidden" name="mhm_rentiva_settings[current_active_tab]" value="' . esc_attr($renderer->get_slug()) . '">';

								$renderer->render();

								$form_content = ob_get_clean();
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal fields are escaped, we must allow form tags here.
								echo \MHMRentiva\Admin\Settings\View\SettingsViewHelper::remove_nested_forms( (string) $form_content);
								?>

								<div class="submit-section">
									<?php submit_button(__('Save Changes', 'mhm-rentiva')); ?>
								</div>
							</form>
						</div>
					<?php else : ?>
						<?php $renderer->render(); ?>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('Custom or unknown tab detected. Attempting legacy hook dispatch.', 'mhm-rentiva'); ?></p>
					<?php
					$handled = false;
					do_action_ref_array('mhm_rentiva_render_settings_tab', array( &$current_tab, &$handled ));
					if (! $handled) {
						echo '<p>' . esc_html__('No content available for this tab.', 'mhm-rentiva') . '</p>';
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>