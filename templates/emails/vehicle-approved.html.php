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
		echo esc_html(sprintf(__('Your vehicle "%s" has been approved and is now live on the platform. Customers can now find and book it.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<?php if (! empty($data['vehicle']['url'])) : ?>
		<p style="text-align:center">
			<a class="cta-button" href="<?php echo esc_url( (string) ( $data['vehicle']['url'] ?? '' )); ?>" style="display:inline-block;background:#2e7d32;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
				<?php esc_html_e('View Your Vehicle', 'mhm-rentiva'); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
