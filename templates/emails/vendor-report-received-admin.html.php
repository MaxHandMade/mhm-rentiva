<?php
if (! defined('ABSPATH')) {
	exit;
}
?>
<div class="content">
	<p><?php echo esc_html__('A vendor has submitted a new report.', 'mhm-rentiva'); ?></p>

	<table style="border-collapse:collapse;margin:16px 0;width:100%;">
		<tr>
			<th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb;width:140px;"><?php echo esc_html__('Vendor', 'mhm-rentiva'); ?></th>
			<td style="padding:8px;border:1px solid #e5e7eb;"><?php echo esc_html( (string) ( $data['vendor']['name'] ?? '—' )); ?></td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><?php echo esc_html__('Title', 'mhm-rentiva'); ?></th>
			<td style="padding:8px;border:1px solid #e5e7eb;"><?php echo esc_html( (string) ( $data['report']['title'] ?? '—' )); ?></td>
		</tr>
		<tr>
			<th style="text-align:left;padding:8px;border:1px solid #e5e7eb;background:#f9fafb;"><?php echo esc_html__('Context', 'mhm-rentiva'); ?></th>
			<td style="padding:8px;border:1px solid #e5e7eb;"><?php echo esc_html( (string) ( $data['report']['context_type'] ?? '—' )); ?></td>
		</tr>
	</table>

	<h3><?php echo esc_html__('Description', 'mhm-rentiva'); ?></h3>
	<div style="background:#f9fafb;padding:12px;border-radius:6px;white-space:pre-wrap;"><?php echo esc_html( (string) ( $data['report']['description'] ?? '' )); ?></div>

	<p style="text-align:center;margin-top:24px;">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['report']['admin_url'] ?? '' )); ?>" style="display:inline-block;background:#1e6bf5;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php echo esc_html__('Open report in admin', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
