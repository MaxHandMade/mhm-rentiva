<?php if (! defined('ABSPATH')) {
	exit;
} ?>
<div class="content">
	<p>
		<?php
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
		/* translators: %s: vendor display name */
		echo esc_html(sprintf(__('Hello %s,', 'mhm-rentiva'), (string) ( $data['vendor']['name'] ?? '' )));
		?>
	</p>
	<p>
		<?php
		echo esc_html(sprintf(
			/* translators: %s: commission amount formatted */
			__('A commission of %s has cleared and is now part of your available balance.', 'mhm-rentiva'),
			(string) ( $data['commission']['amount_formatted'] ?? '' )
		));
		?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>?tab=ledger" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('View Ledger & Payouts', 'mhm-rentiva'); ?>
		</a>
	</p>
	<p><?php esc_html_e('If you have any questions, please don\'t hesitate to contact us.', 'mhm-rentiva'); ?></p>
</div>
