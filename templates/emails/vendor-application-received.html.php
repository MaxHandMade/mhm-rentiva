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
	<p><?php esc_html_e('We have received your vendor application. Our team will review your documents and notify you by email once a decision has been made.', 'mhm-rentiva'); ?></p>
	<p><?php esc_html_e('This process typically takes 1-3 business days. Thank you for your patience.', 'mhm-rentiva'); ?></p>
</div>
