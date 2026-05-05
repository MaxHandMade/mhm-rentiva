<?php
/**
 * Vendor Profile — vehicles partial.
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}

$vehicles     = $data['vehicles'] ?? [];
$view_all_url = (string) apply_filters('mhm_rentiva_vendor_profile_view_all_url', '', $data['user_id']);
?>
<div class="mhm-vendor-vehicles">
	<div class="label">
		<?php
		/* translators: %d: vehicle count */
		printf(esc_html__('Vehicles (%d)', 'mhm-rentiva'), count($vehicles));
		?>
	</div>
	<?php if (empty($vehicles)) : ?>
		<div class="mhm-vendor-vehicles-empty">
			<?php
			$message = (string) ( $atts['empty_vehicles_message'] ?? '' );
			echo esc_html($message !== '' ? $message : __('This vendor is not currently listing any vehicles.', 'mhm-rentiva'));
			?>
		</div>
	<?php else : ?>
		<div class="mhm-vendor-vehicle-grid">
			<?php foreach ($vehicles as $vehicle) : ?>
				<a href="<?php echo esc_url($vehicle['url']); ?>" class="mhm-vendor-vehicle-card">
					<?php if (!empty($vehicle['thumb'])) : ?>
						<img src="<?php echo esc_url($vehicle['thumb']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" />
					<?php endif; ?>
					<div class="mhm-vendor-vehicle-card-title"><?php echo esc_html($vehicle['title']); ?></div>
					<?php if ($vehicle['count'] > 0) : ?>
						<div class="mhm-vendor-vehicle-card-rating">
							★ <?php echo esc_html(number_format_i18n($vehicle['rating'], 1)); ?>
							<span>(<?php echo (int) $vehicle['count']; ?>)</span>
						</div>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ($view_all_url !== '') : ?>
			<div class="mhm-vendor-vehicles-cta">
				<a href="<?php echo esc_url($view_all_url); ?>"><?php esc_html_e('View all vehicles →', 'mhm-rentiva'); ?></a>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
