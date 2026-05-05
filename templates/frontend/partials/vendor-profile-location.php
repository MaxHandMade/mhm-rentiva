<?php
/**
 * Vendor Profile — location partial.
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mhm-vendor-location">
	<div class="mhm-vendor-section-label"><?php esc_html_e('Location', 'mhm-rentiva'); ?></div>
	<p>📍 <?php echo esc_html($data['city']); ?></p>
</div>
