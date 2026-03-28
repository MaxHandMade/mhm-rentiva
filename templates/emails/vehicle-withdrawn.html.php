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
		echo esc_html(sprintf(__('Your vehicle "%s" has been withdrawn from the platform.', 'mhm-rentiva'), (string) ( $data['vehicle']['title'] ?? '' )));
		?>
	</p>
	<?php if (! empty($data['lifecycle']['penalty']) && (float) $data['lifecycle']['penalty'] > 0) : ?>
		<p style="color:#dc3545;font-weight:600;">
			<?php
			/* translators: %s: formatted penalty amount */
			echo esc_html(sprintf(__('A withdrawal penalty of %s has been applied to your account.', 'mhm-rentiva'), (string) ( $data['lifecycle']['penalty_formatted'] ?? '' )));
			?>
		</p>
	<?php endif; ?>
	<p>
		<?php
		/* translators: %d: cooldown days */
		echo esc_html(sprintf(__('You can relist this vehicle after a %d-day cooldown period.', 'mhm-rentiva'), (int) ( $data['lifecycle']['cooldown_days'] ?? 7 )));
		?>
	</p>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['panel']['url'] ?? home_url('/panel/') )); ?>" style="display:inline-block;background:#6c757d;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Go to Dashboard', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
