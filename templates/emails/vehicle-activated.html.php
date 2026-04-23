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
		/* translators: 1: vehicle title, 2: listing duration days */
		echo esc_html(sprintf(__('Your vehicle "%1$s" is now live on the platform! Your listing is active for %2$d days.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' ), (int) ( $data['lifecycle']['duration_days'] ?? 90 )));
		?>
	</p>
	<?php if (! empty($data['lifecycle']['expires_at'])) : ?>
		<p>
			<?php
			/* translators: %s: expiry date */
			echo esc_html(sprintf(__('Listing expires on: %s', 'mhm-rentiva'), wp_date(get_option('date_format'), strtotime( (string) $data['lifecycle']['expires_at']))));
			?>
		</p>
	<?php endif; ?>
	<?php if (! empty($data['vehicle']['url'])) : ?>
		<p style="text-align:center">
			<a class="cta-button" href="<?php echo esc_url( (string) ( $data['vehicle']['url'] ?? '' )); ?>" style="display:inline-block;background:#2e7d32;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
				<?php esc_html_e('View Your Listing', 'mhm-rentiva'); ?>
			</a>
		</p>
	<?php endif; ?>
</div>
