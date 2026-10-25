<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicles Grid Template
 *
 * Special template for grid layout - supports only grid layout
 *
 * @var array $atts Shortcode attributes
 * @var array $vehicles Vehicle data array
 * @var int $total_vehicles Total vehicle count
 * @var bool $has_vehicles Whether vehicles exist
 * @var string $layout_class Layout CSS class
 * @var string $columns_class Columns CSS class
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\Templates;

// Get template data
$atts           = $atts ?? array();
$vehicles       = $vehicles ?? array();
$total_vehicles = $total_vehicles ?? 0;
$has_vehicles   = $has_vehicles ?? false;
$layout_class   = $layout_class ?? 'rv-vehicles-grid';
$columns_class  = $columns_class ?? 'rv-vehicles-grid--columns-3';
$wrapper_class  = $wrapper_class ?? '';
$booking_url    = $booking_url ?? '';
// Set by the favourites shortcode only (spec 3A Task 7b); the vehicles grid keeps
// grid cards and its own empty state.
$card_layout     = $card_layout ?? 'grid';
$no_results_text = $no_results_text ?? '';
?>

<?php
// The shortcode already validated the column settings into $columns_class
// (`<layout>--columns-N`, plus `--tablet-N` / `--mobile-N` only when set
// explicitly); carry them into the front-end standard's grid. `columns` caps
// the cards per row, the grid stays fluid below it.
$rvs_columns = preg_match('/--columns-(\d)\b/', $columns_class, $rvs_m) ? (int) $rvs_m[1] : 3;
$rvs_classes = array( 'list' === $card_layout ? 'rvs-card-list' : 'rvs-card-grid' );
if ('rv-vehicles-masonry' === $layout_class) {
	$rvs_classes[] = 'rvs-card-grid--masonry';
}
if (preg_match_all('/--(tablet|mobile)-(\d)\b/', $columns_class, $rvs_bp, PREG_SET_ORDER)) {
	foreach ($rvs_bp as $rvs_match) {
		$rvs_classes[] = 'rvs-card-grid--' . $rvs_match[1] . '-' . $rvs_match[2];
	}
}
?>
<div class="rvs-listing <?php echo esc_attr($wrapper_class); ?>">

	<?php if ($has_vehicles) : ?>

		<div class="<?php echo esc_attr(implode(' ', $rvs_classes)); ?>" style="--rvs-cols: <?php echo esc_attr( (string) $rvs_columns); ?>">
			<?php foreach ($vehicles as $vehicle) : ?>
				<?php
				// The partial escapes each value at its own output site.
				Templates::render('partials/rvs-vehicle-card', array(
					'vehicle' => $vehicle,
					'layout'  => $card_layout,
					'atts'    => $atts,
				));
				?>
			<?php endforeach; ?>
		</div>

	<?php else : ?>

		<div class="rvs-empty">
			<p><?php echo esc_html('' !== $no_results_text ? $no_results_text : __('No vehicles found yet.', 'mhm-rentiva')); ?></p>
		</div>

	<?php endif; ?>

	<?php if ( ! empty( $atts['view_all_url'] ) ) : ?>
		<div class="rvs-listing__footer">
			<a href="<?php echo esc_url( $atts['view_all_url'] ); ?>" class="rvs-btn rvs-btn--secondary">
				<?php echo esc_html( ! empty( $atts['view_all_text'] ) ? $atts['view_all_text'] : __( 'View All Vehicles', 'mhm-rentiva' ) ); ?>
			</a>
		</div>
	<?php endif; ?>

</div>
