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
		echo esc_html(sprintf(__('Your vehicle "%s" has been paused. It will not appear in search results while paused.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<p>
		<?php esc_html_e('You can resume your listing at any time from your vendor dashboard. Note: the listing timer continues while paused.', 'mhm-rentiva'); ?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#ffc107;color:#333;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Go to Dashboard', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
