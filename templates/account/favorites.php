<?php
declare(strict_types=1);

// My Favorites page template (PR D, Task 2: the outer shell on the `rvs-` layer).

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

if (! defined('ABSPATH')) {
	exit;
}

$favorites  = $data['favorites'] ?? array();
$navigation = $data['navigation'] ?? array();
$columns    = $data['columns'] ?? 3;
$count      = count($favorites);
$title_id   = wp_unique_id('rvs-acc-');

$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
if (! $vehicles_url) {
	$vehicles_url = get_post_type_archive_link('mhmrentiva_vehicle');
}
if (! $vehicles_url) {
	$vehicles_url = home_url('/');
}
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public filter name kept for backward compatibility.
$vehicles_url = (string) apply_filters('mhmrentiva_vehicles_page_url', $vehicles_url);

// The account page's hook for my-account.js (clear all) stays on the root:
// `mhm-rentiva-account-page`.
?>
<?php // Not a shortcode render, so the front-end standard's shell is written here. ?>
<div class="rvs-shell rvs-account mhm-rentiva-account-page" data-rvs-shortcode="rentiva_my_favorites" data-rvs-favorites>
	<?php if (! empty($navigation)) : ?>
		<?php echo wp_kses_post(\MHMRentiva\Admin\Core\Utilities\Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>

	<section class="rvs-account__section rvs-account-favorites" aria-labelledby="<?php echo esc_attr($title_id); ?>">
		<div class="rvs-account__head">
			<h2 id="<?php echo esc_attr($title_id); ?>" class="rvs-account-favorites__title"><?php esc_html_e('My favorites', 'mhm-rentiva'); ?></h2>
			<?php if ($count > 0) : ?>
				<p class="rvs-account__sub" data-rvs-favorites-count>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: number of favorite vehicles */
							_n('%s vehicle in your favorites', '%s vehicles in your favorites', $count, 'mhm-rentiva'),
							number_format_i18n($count)
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="rvs-account-empty" data-rvs-favorites-empty data-testid="favorites-empty"<?php echo $count > 0 ? ' hidden' : ''; ?>>
			<h3><?php esc_html_e('No favorite vehicles yet', 'mhm-rentiva'); ?></h3>
			<p><?php esc_html_e('You can add vehicles to your favorites by clicking the heart icon on vehicles you like.', 'mhm-rentiva'); ?></p>
			<a class="rvs-btn rvs-btn--primary" href="<?php echo esc_url($vehicles_url); ?>"><?php esc_html_e('Browse cars', 'mhm-rentiva'); ?></a>
		</div>

		<?php if ($count > 0) : ?>
			<div class="rvs-account-favorites__list" data-rvs-favorites-list>
				<div class="mhm-my-favorites-container rv-my-favorites-wrapper rvs-listing" data-testid="favorites-grid">
					<div class="rvs-card-grid" style="--rvs-cols: <?php echo esc_attr( (string) max(1, min(6, (int) $columns))); ?>">
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
							$vehicle_data = \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::get_vehicle_data_for_shortcode( (int) $vehicle_id, $card_atts);
							if (! $vehicle_data) {
								continue;
							}

							// Override is_favorite to true since this is the favorites page
							$vehicle_data['is_favorite'] = true;

							// Rendered directly to output: the partial escapes each value at its
							// own output site. (wp_kses_post() over it would strip the inline SVG.)
							\MHMRentiva\Admin\Core\Utilities\Templates::render('partials/rvs-vehicle-card', array(
								'vehicle' => $vehicle_data,
								'layout'  => 'grid',
								'atts'    => $card_atts,
							));
						endforeach;
						?>
					</div>
				</div>

				<div class="rvs-account__actions">
					<button type="button" id="clear-all-favorites" class="rvs-btn rvs-btn--secondary" data-testid="clear-favorites-btn">
						<?php esc_html_e('Clear all favorites', 'mhm-rentiva'); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</section>
</div>
