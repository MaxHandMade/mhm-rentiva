<?php
/**
 * Vendor Profile main template (Centered Hero layout).
 *
 * Available: $data (render-ready array from VendorProfileProvider), $atts (shortcode attributes).
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}

$wrapper_class = 'mhm-rentiva-vendor-profile';
if (!empty($atts['class'])) {
    foreach (preg_split('/\s+/', trim( (string) $atts['class'])) as $part) {
        if ($part !== '') {
            $wrapper_class .= ' ' . sanitize_html_class($part);
        }
    }
}
$partials = plugin_dir_path(MHM_RENTIVA_PLUGIN_FILE) . 'templates/frontend/partials/';
?>
<div class="<?php echo esc_attr($wrapper_class); ?>">
	<?php require $partials . 'vendor-profile-hero.php'; ?>
	<?php if ($atts['show_about'] === 'yes' && $data['bio'] !== '') : ?>
		<?php include $partials . 'vendor-profile-about.php'; ?>
	<?php endif; ?>
	<?php if ($atts['show_vehicles'] === 'yes') : ?>
		<?php include $partials . 'vendor-profile-vehicles.php'; ?>
	<?php endif; ?>
	<?php if ($atts['show_reviews'] === 'yes') : ?>
		<?php include $partials . 'vendor-profile-reviews.php'; ?>
	<?php endif; ?>
	<?php if ($atts['show_location'] === 'yes' && $data['city'] !== '') : ?>
		<?php include $partials . 'vendor-profile-location.php'; ?>
	<?php endif; ?>
</div>
