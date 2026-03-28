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
		/* translators: %s: vehicle title */
		echo esc_html(sprintf(__('Your listing for "%s" has expired.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<p>
		<?php esc_html_e('You can renew your listing from your vendor dashboard to keep it active for another 90 days. If you do not renew within the grace period, the listing will be automatically withdrawn.', 'mhm-rentiva'); ?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#fd7e14;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Renew My Listing', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
