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
		echo esc_html(sprintf(__('Your vehicle "%s" has been submitted for review. Our team will review it shortly and notify you once it is approved.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#6c757d;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Go to Dashboard', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
