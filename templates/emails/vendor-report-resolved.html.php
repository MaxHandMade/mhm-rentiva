<?php
if (! defined('ABSPATH')) {
	exit;
}

$status_label = '';
$status_raw   = (string) ( $data['report']['status'] ?? '' );
if ($status_raw === 'resolved') {
	$status_label = __('Resolved (in your favor)', 'mhm-rentiva');
} elseif ($status_raw === 'rejected') {
	$status_label = __('Rejected', 'mhm-rentiva');
} else {
	$status_label = ucfirst($status_raw);
}
?>
<div class="content">
	<p>
		<?php
		/* translators: %s: vendor display name */
		echo esc_html(sprintf(__('Hello %s,', 'mhm-rentiva'), (string) ( $data['vendor']['name'] ?? '' )));
		?>
	</p>

	<p>
		<?php
		/* translators: %s: report title */
		echo esc_html(sprintf(__('The administrator has reviewed your report "%s".', 'mhm-rentiva'), (string) ( $data['report']['title'] ?? '' )));
		?>
	</p>

	<p>
		<strong><?php echo esc_html__('Status:', 'mhm-rentiva'); ?></strong>
		<?php echo esc_html($status_label); ?>
	</p>

	<?php if (! empty($data['report']['admin_note'])) : ?>
		<h3><?php echo esc_html__('Administrator Note', 'mhm-rentiva'); ?></h3>
		<div style="background:#fff7e6;padding:12px;border-radius:6px;border:1px solid #fde68a;white-space:pre-wrap;">
			<?php echo esc_html( (string) $data['report']['admin_note']); ?>
		</div>
	<?php endif; ?>

	<p style="text-align:center;margin-top:24px;">
		<a class="cta-button" href="<?php echo esc_url( (string) ( $data['report']['panel_url'] ?? home_url('/') )); ?>" style="display:inline-block;background:#1e6bf5;color:#fff;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:600;">
			<?php echo esc_html__('Go to your panel', 'mhm-rentiva'); ?>
		</a>
	</p>
</div>
