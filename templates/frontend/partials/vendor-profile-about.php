<?php
/**
 * Vendor Profile — about partial.
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="mhm-vendor-about">
	<div class="mhm-vendor-section-label"><?php esc_html_e('About', 'mhm-rentiva'); ?></div>
	<p><?php echo wp_kses_post($data['bio']); ?></p>
</div>
