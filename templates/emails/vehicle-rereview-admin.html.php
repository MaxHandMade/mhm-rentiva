<?php if (! defined('ABSPATH')) {
	exit; } ?>
<div class="content">
	<p><?php esc_html_e('A vendor has edited a published vehicle with critical field changes. The vehicle has been moved to pending review.', 'mhm-rentiva'); ?></p>
	<table style="width:100%;border-collapse:collapse;margin:12px 0">
		<tr>
			<td style="padding:8px;border:1px solid #e5e7eb;font-weight:600;width:140px"><?php esc_html_e('Vendor', 'mhm-rentiva'); ?></td>
			<td style="padding:8px;border:1px solid #e5e7eb">
				<?php
				// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
				echo esc_html( (string) ( $data['vendor']['name'] ?? '' ));
				?>
			</td>
		</tr>
		<tr>
			<td style="padding:8px;border:1px solid #e5e7eb;font-weight:600"><?php esc_html_e('Vehicle', 'mhm-rentiva'); ?></td>
			<td style="padding:8px;border:1px solid #e5e7eb"><?php echo esc_html( (string) ( $data['vehicle']['title'] ?? '' )); ?></td>
		</tr>
	</table>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['vehicle']['admin_url'] ?? admin_url('edit.php?post_type=vehicle') )); ?>" style="display:inline-block;background:#d97706;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Review Changes', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
