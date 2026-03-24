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
	<p><?php esc_html_e('Thank you for applying to become a vendor on our platform. After reviewing your application, we are unable to approve it at this time.', 'mhm-rentiva'); ?></p>
	<?php if (! empty($data['rejection']['reason'])) : ?>
		<div style="background:#fef2f2;border-left:4px solid #ef4444;padding:14px 18px;border-radius:4px;margin:16px 0">
			<strong><?php esc_html_e('Reason:', 'mhm-rentiva'); ?></strong><br>
			<?php echo esc_html( (string) ( $data['rejection']['reason'] ?? '' )); ?>
		</div>
	<?php endif; ?>
	<p><?php esc_html_e('You are welcome to reapply once the noted issues have been resolved. If you believe this decision was made in error, please contact our support team.', 'mhm-rentiva'); ?></p>
</div>
