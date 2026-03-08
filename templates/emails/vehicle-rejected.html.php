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
		echo esc_html(sprintf(__('Your vehicle listing "%s" could not be approved at this time.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<?php if (! empty($data['rejection']['reason'])) : ?>
		<div style="background:#fef2f2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:4px;margin:16px 0">
			<strong><?php esc_html_e('Reason:', 'mhm-rentiva'); ?></strong><br>
			<?php echo esc_html( (string) ( $data['rejection']['reason'] ?? '' )); ?>
		</div>
	<?php endif; ?>
	<p><?php esc_html_e('Please update your listing based on the feedback above and resubmit for review.', 'mhm-rentiva'); ?></p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Go to My Dashboard', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
