<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Transfer Search Results Template
 *
 * @var array $data {
 *     @var array $results Search results from TransferSearchEngine.
 *     @var array $criteria Search criteria used.
 *     @var string $origin_name Name of the origin location.
 *     @var string $destination_name Name of the destination location.
 * }
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Core\CurrencyHelper;

// Variables are mapped into local scope from prepare_template_data() return array:
// $results, $criteria, $origin_name, $destination_name, $atts
// (Templates::render exposes template vars directly, no $data wrapper needed)
$results          = $results ?? array();
$criteria         = $criteria ?? array();
$origin_name      = $origin_name ?? '';
$destination_name = $destination_name ?? '';

/**
 * Local helper to format price
 */
$format_price = function (float $price, string $currency = '') {
	return CurrencyHelper::format_price($price, 0);
};

// Visibility Controls
$show_summary_route = $atts['show_summary_route'] ?? true;
$show_summary_date  = $atts['show_summary_date'] ?? true;
$show_summary_pax   = $atts['show_summary_pax'] ?? true;

$show_image           = $atts['show_image'] ?? true;
$show_category        = $atts['show_category'] ?? true;
$show_title           = $atts['show_title'] ?? true;
$show_price           = $atts['show_price'] ?? true;
$show_booking_button  = $atts['show_booking_button'] ?? ( $atts['show_booking_btn'] ?? true );
$show_vehicle_details = $atts['show_vehicle_details'] ?? true;
$show_luggage_info    = $atts['show_luggage_info'] ?? true;
$show_passenger_count = $atts['show_passenger_count'] ?? true;
$show_route_info      = $atts['show_route_info'] ?? true;

// v1.3.3 Visibility Bridges
$fav_val      = $atts['show_favorite_button'] ?? ( $atts['show_favorite_btn'] ?? true );
$show_fav     = ( $fav_val !== '0' && $fav_val !== 'false' && $fav_val !== false );
$comp_val     = $atts['show_compare_button'] ?? ( $atts['show_compare_btn'] ?? true );
$show_compare = ( $comp_val !== '0' && $comp_val !== 'false' && $comp_val !== false );

$active_layout  = $layout ?? ( $atts['layout'] ?? 'grid' );
$active_columns = $columns ?? ( $atts['columns'] ?? 2 );
$wrapper_class  = 'mhm-transfer-results-page mhm-transfer-results rv-transfer-results rv-unified-search-results rv-transfer-results--' . esc_attr($active_layout);
?>

<div class="<?php echo esc_attr($wrapper_class); ?>" data-columns="<?php echo esc_attr( (string) $active_columns); ?>" style="--columns: <?php echo esc_attr( (string) $active_columns); ?>;">
	<?php if ($show_summary_route) : ?>
		<div class="mhm-transfer-results__summary">
			<div class="mhm-transfer-results__summary-info">
				<h2 class="mhm-transfer-results__summary-route">
					<?php echo esc_html($origin_name); ?>
					<span class="mhm-transfer-card__route-arrow">&rarr;</span>
					<?php echo esc_html($destination_name); ?>
				</h2>
				<?php if ($show_summary_date || $show_summary_pax) : ?>
					<div class="mhm-transfer-summary__chips">
						<?php if ($show_summary_date) : ?>
							<span class="mhm-transfer-summary__chip">
								<span class="mhm-transfer-summary__chip-icon">
									<?php
                                    Icons::render('calendar', [
										'width'  => '18',
										'height' => '18',
									]);
									?>
								</span>
								<span class="mhm-transfer-summary__chip-body">
									<span class="mhm-transfer-summary__chip-label"><?php esc_html_e('Date', 'mhm-rentiva'); ?></span>
									<span class="mhm-transfer-summary__chip-value">
										<?php
										$raw_date = $criteria['date'] ?? '';
										if ($raw_date) {
											$ts = strtotime($raw_date);
											echo esc_html($ts ? date_i18n(get_option('date_format'), $ts) : $raw_date);
										}
										?>
									</span>
								</span>
							</span>
							<span class="mhm-transfer-summary__chip">
								<span class="mhm-transfer-summary__chip-icon">
									<?php
                                    Icons::render('clock', [
										'width'  => '18',
										'height' => '18',
									]);
									?>
								</span>
								<span class="mhm-transfer-summary__chip-body">
									<span class="mhm-transfer-summary__chip-label"><?php esc_html_e('Time', 'mhm-rentiva'); ?></span>
									<span class="mhm-transfer-summary__chip-value"><?php echo esc_html($criteria['time'] ?? ''); ?></span>
								</span>
							</span>
						<?php endif; ?>
						<?php if ($show_summary_pax) : ?>
							<span class="mhm-transfer-summary__chip">
								<span class="mhm-transfer-summary__chip-icon">
									<?php
                                    Icons::render('users', [
										'width'  => '18',
										'height' => '18',
									]);
									?>
								</span>
								<span class="mhm-transfer-summary__chip-body">
									<span class="mhm-transfer-summary__chip-label"><?php esc_html_e('Passengers', 'mhm-rentiva'); ?></span>
									<span class="mhm-transfer-summary__chip-value">
										<?php echo esc_html( (string) ( ( $criteria['adults'] ?? 0 ) + ( $criteria['children'] ?? 0 ) )); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?>
									</span>
								</span>
							</span>
						<?php endif; ?>
						<?php
						$luggage_big   = (int) ( $criteria['luggage_big'] ?? 0 );
						$luggage_small = (int) ( $criteria['luggage_small'] ?? 0 );
						if ($luggage_big > 0 || $luggage_small > 0) :
							$parts = array();
							if ($luggage_big > 0) {
								$parts[] = $luggage_big . ' ' . __('Big Bags', 'mhm-rentiva');
							}
							if ($luggage_small > 0) {
								$parts[] = $luggage_small . ' ' . __('Small Bags', 'mhm-rentiva');
							}
							?>
							<span class="mhm-transfer-summary__chip">
								<span class="mhm-transfer-summary__chip-icon">
									<?php
                                    Icons::render('luggage', [
										'width'  => '18',
										'height' => '18',
									]);
									?>
								</span>
								<span class="mhm-transfer-summary__chip-body">
									<span class="mhm-transfer-summary__chip-label"><?php esc_html_e('Luggage', 'mhm-rentiva'); ?></span>
									<span class="mhm-transfer-summary__chip-value"><?php echo esc_html(implode(' + ', $parts)); ?></span>
								</span>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if (empty($results)) : ?>
		<div class="mhm-transfer-results__empty">
			<div class="mhm-transfer-results__empty-icon"><?php Icons::render('info'); ?></div>
			<h3 class="mhm-transfer-results__empty-title"><?php esc_html_e('No transfers found', 'mhm-rentiva'); ?></h3>
			<p class="mhm-transfer-results__empty-text"><?php esc_html_e('No transfers found for the selected criteria. Please try different options.', 'mhm-rentiva'); ?></p>
		</div>
	<?php else : ?>
		<div class="mhm-transfer-results__grid">
			<?php
			foreach ($results as $item) :
				$transfer_card_html = Templates::render(
					'partials/transfer-card',
					array(
						'item'         => $item,
						'criteria'     => $criteria,
						'atts'         => $atts,
						'format_price' => $format_price,
					),
					true
				);
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output is escaped internally at field level.
				echo $transfer_card_html;
				?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
