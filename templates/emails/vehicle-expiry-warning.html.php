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
	<p>
		<?php
		/* translators: 1: vehicle title, 2: days remaining */
		echo esc_html(sprintf(__('Your listing for "%1$s" will expire in %2$d day(s).', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' ), (int) ( $data['lifecycle']['days_remaining'] ?? 0 )));
		?>
	</p>
	<p>
		<?php esc_html_e('After expiry, you will have a 7-day grace period to renew. If not renewed, the listing will be automatically withdrawn.', 'mhm-rentiva'); ?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#ffc107;color:#333;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Manage My Listings', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
