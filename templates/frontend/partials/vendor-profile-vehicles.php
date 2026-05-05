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
<div class="mhm-vendor-vehicles" id="mhm-vendor-vehicles">
	<div class="mhm-vendor-section-label">
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
				<?php $is_compact = empty($vehicle['thumb']); ?>
				<a href="<?php echo esc_url($vehicle['url']); ?>" class="mhm-vendor-vehicle-card<?php echo $is_compact ? ' mhm-vendor-vehicle-card--compact' : ''; ?>">
					<?php if (!$is_compact) : ?>
						<img src="<?php echo esc_url($vehicle['thumb']); ?>" alt="<?php echo esc_attr($vehicle['title']); ?>" loading="lazy" />
					<?php endif; ?>
					<div class="mhm-vendor-vehicle-card-title"><?php echo esc_html($vehicle['title']); ?></div>
					<?php if ($vehicle['count'] > 0) : ?>
						<div class="mhm-vendor-vehicle-card-rating">
							<span class="mhm-vendor-vehicle-card-stars" aria-hidden="true">
								<?php echo esc_html(\MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile::stars_html( (float) $vehicle['rating'])); ?>
							</span>
							<span class="mhm-vendor-vehicle-card-rating-text">
								<?php echo esc_html(number_format_i18n($vehicle['rating'], 1)); ?>
								<span class="mhm-vendor-vehicle-card-rating-count">(<?php echo (int) $vehicle['count']; ?>)</span>
							</span>
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
