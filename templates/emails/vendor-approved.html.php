<?php if (! defined('ABSPATH')) {
	exit; } ?>
<div class="content">
	<p>
		<?php
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
		/* translators: %s: vendor display name */
		echo esc_html(sprintf(__('Hello %s,', 'mhm-rentiva'), (string) ( $data['vendor']['name'] ?? '' )));
		?>
	</p>
	<p><?php esc_html_e('Great news! Your vendor application has been reviewed and approved. You can now log in and start adding your vehicles to the platform.', 'mhm-rentiva'); ?></p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Go to My Dashboard', 'mhm-rentiva'); ?>
		</a>
	</p>
	<p><?php esc_html_e('If you have any questions, please don\'t hesitate to contact us.', 'mhm-rentiva'); ?></p>
</div>
