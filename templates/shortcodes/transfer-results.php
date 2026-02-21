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
	if (function_exists('wc_price')) {
		return wc_price($price);
	}
	return $currency . number_format($price, 2);
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

$layout        = $layout ?? ( $atts['layout'] ?? 'grid' );
$columns       = $columns ?? ( $atts['columns'] ?? 2 );
$wrapper_class = 'mhm-transfer-results-page mhm-transfer-results rv-transfer-results rv-unified-search-results rv-transfer-results--' . esc_attr($layout);
?>

<div class="<?php echo esc_attr($wrapper_class); ?>" data-columns="<?php echo esc_attr( (string) $columns); ?>" style="--columns: <?php echo esc_attr( (string) $columns); ?>;">
	<?php if ($show_summary_route) : ?>
		<div class="mhm-transfer-results__summary">
			<div class="mhm-transfer-results__summary-info">
				<h2 class="mhm-transfer-results__summary-route">
					<?php echo esc_html($origin_name); ?>
					<span class="mhm-transfer-card__route-arrow">&rarr;</span>
					<?php echo esc_html($destination_name); ?>
				</h2>
				<?php if ($show_summary_date || $show_summary_pax) : ?>
					<div class="mhm-transfer-results__summary-date">
						<?php if ($show_summary_date) : ?>
							<span class="rv-info-item">
								<?php
                                Icons::render('calendar', [
									'width'  => '14',
									'height' => '14',
								]);
								?>
								<?php echo esc_html($criteria['date'] ?? ''); ?>
							</span>
							<span class="rv-info-item" style="margin-left: 15px;">
								<?php
                                Icons::render('clock', [
									'width'  => '14',
									'height' => '14',
								]);
								?>
								<?php echo esc_html($criteria['time'] ?? ''); ?>
							</span>
						<?php endif; ?>
						<?php if ($show_summary_pax) : ?>
							<span class="rv-info-item" style="margin-left: 15px;">
								<?php
                                Icons::render('users', [
									'width'  => '14',
									'height' => '14',
								]);
								?>
								<?php echo esc_html( (string) ( ( $criteria['adults'] ?? 0 ) + ( $criteria['children'] ?? 0 ) )); ?> <?php esc_html_e('Pax', 'mhm-rentiva'); ?>
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
