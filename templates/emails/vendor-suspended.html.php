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
	<p><?php esc_html_e('We are writing to inform you that your vendor account has been temporarily suspended on our platform.', 'mhm-rentiva'); ?></p>
	<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:4px;margin:16px 0">
		<strong><?php esc_html_e('Account Status:', 'mhm-rentiva'); ?></strong><br>
		<?php esc_html_e('Your vendor account and all active listings have been suspended. Your customers will not be able to make new bookings until the suspension is lifted.', 'mhm-rentiva'); ?>
	</div>
	<p><?php esc_html_e('If you believe this action was taken in error or would like to appeal this decision, please contact our support team.', 'mhm-rentiva'); ?></p>
	<p>
		<?php
		/* translators: %s: site name */
		echo esc_html(sprintf(__('Thank you for being part of %s.', 'mhm-rentiva'), (string) ( $data['site']['name'] ?? get_bloginfo('name') )));
		?>
	</p>
</div>
