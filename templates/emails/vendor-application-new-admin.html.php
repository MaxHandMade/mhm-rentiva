<?php if (! defined('ABSPATH')) {
	exit; } ?>
<div class="content">
	<p><?php esc_html_e('A new vendor application has been submitted and is awaiting review.', 'mhm-rentiva'); ?></p>
	<table style="width:100%;border-collapse:collapse;margin:12px 0">
		<tr>
			<td style="padding:8px;border:1px solid #e5e7eb;font-weight:600;width:140px"><?php esc_html_e('Name', 'mhm-rentiva'); ?></td>
			<td style="padding:8px;border:1px solid #e5e7eb">
				<?php
				// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
				echo esc_html( (string) ( $data['vendor']['name'] ?? '' ));
				?>
			</td>
		</tr>
		<tr>
			<td style="padding:8px;border:1px solid #e5e7eb;font-weight:600"><?php esc_html_e('Email', 'mhm-rentiva'); ?></td>
			<td style="padding:8px;border:1px solid #e5e7eb"><?php echo esc_html( (string) ( $data['vendor']['email'] ?? '' )); ?></td>
		</tr>
	</table>
	<p style="text-align:center">
		<a class="cta-button" href="<?php echo esc_url(admin_url('admin.php?page=mhm-rentiva-vendors&tab=pending')); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php esc_html_e('Review Application', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
