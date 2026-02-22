<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic HTML is rendered by internal template layer with localized escaping.

/**
 * My Favorites Page Template
 *
 * Displays user's favorite vehicles
 */

if (! defined('ABSPATH')) {
	exit;
}



$user_id   = get_current_user_id();
$favorites = $data['favorites'] ?? array();
$navigation = $data['navigation'] ?? array();
$columns    = $data['columns'] ?? 3;

$wrapper_class = 'mhm-rentiva-account-page';
if (empty($navigation)) {
	$wrapper_class .= ' mhm-integrated';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
	<?php if (! empty($navigation)) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array('navigation' => $navigation), true)); ?>
	<?php endif; ?>

	<div class="mhm-account-content">
		<div class="section-header">
			<h2><?php esc_html_e('My Favorite Vehicles', 'mhm-rentiva'); ?></h2>
			<span class="view-all-link">
				<?php
				/* translators: %d: favorite vehicles count. */
				printf(esc_html__('%d vehicles in your favorites', 'mhm-rentiva'), count($favorites));
				?>
			</span>
		</div>

		<?php if (empty($favorites)) : ?>
			<!-- No favorites -->
			<div class="empty-state">
				<div class="empty-icon">
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
					</svg>
				</div>
				<h3><?php esc_html_e('No favorite vehicles yet', 'mhm-rentiva'); ?></h3>
				<p><?php esc_html_e('You can add vehicles to your favorites by clicking the heart icon on vehicles you like.', 'mhm-rentiva'); ?></p>
				<a href="
				<?php
				$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
				if (! $vehicles_url) {
					$vehicles_url = get_post_type_archive_link('vehicle');
					if (! $vehicles_url) {
						$vehicles_url = home_url('/');
					}
				}
				echo esc_url($vehicles_url);
				?>
				" class="btn btn-primary">
					<?php esc_html_e('View Vehicles', 'mhm-rentiva'); ?>
				</a>
			</div>
		<?php else : ?>
			<!-- Favorite vehicles (STANDARDIZED) -->
			<div class="account-section">

				<div class="mhm-my-favorites-container rv-my-favorites-wrapper rv-vehicles-grid-container">
					<div class="rv-vehicles-grid rv-vehicles-grid--columns-<?php echo esc_attr((string) (isset($columns) ? (int) $columns : 3)); ?>">
						<?php
						// Default atts for the standardized card
						$card_atts = array(
							'show_image'        => true,
							'show_category'     => true,
							'show_features'     => true,
							'show_price'        => true,
							'show_rating'       => true,
							'show_booking_btn'  => true,
							'show_favorite_btn' => true,
							'show_badges'       => true,
							'booking_btn_text'  => esc_html__('Book Now', 'mhm-rentiva'),
							'image_size'        => 'medium_large',
							'max_features'      => 4,
							'price_format'      => 'daily',
						);

						foreach ($favorites as $vehicle_id) :
							// Use the standardized data method from VehiclesList
							$vehicle_data = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode((int) $vehicle_id, $card_atts);
							if (! $vehicle_data) {
								continue;
							}

							// Override is_favorite to true since this is the favorites page
							$vehicle_data['is_favorite'] = true;

							// Render standardized vehicle card as-is.
							// Do not pass through wp_kses_post() because it strips SVG icons.
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output escapes dynamic values internally.
							echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
								'vehicle' => $vehicle_data,
								'layout'  => 'grid',
								'atts'    => $card_atts,
							), true);
						endforeach;
						?>
					</div>
				</div>


				<!-- Clear Button -->
				<div class="form-actions">
					<button type="button" id="clear-all-favorites" class="btn btn-secondary">
						<?php esc_html_e('Clear All Favorites', 'mhm-rentiva'); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
