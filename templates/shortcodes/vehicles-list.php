<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicles List Template - Standardized with Partial
 *
 * @var array $atts Shortcode attributes
 * @var array $vehicles Vehicle data array
 * @var int $total_vehicles Total vehicle count
 * @var bool $has_vehicles Whether vehicles exist
 * @var string $layout_class Layout CSS class
 * @var string $columns_class Columns CSS class
 * @var string $card_layout Card layout: list or grid
 * @var array $context Global settings context
 */

if (! defined('ABSPATH')) {
	exit;
}

// Data Preparation & Defaults
$atts          = $atts ?? array();
$vehicles      = $vehicles ?? array();
$has_vehicles  = $has_vehicles ?? false;
$layout_class  = $layout_class ?? 'rv-vehicles-list';
$columns_class = $columns_class ?? 'rv-vehicles-list--columns-1';
$wrapper_class = $wrapper_class ?? '';
$card_layout   = $card_layout ?? 'list';
?>

<?php
// The shortcode validated `columns` into $columns_class (1-4, else 1). One
// column of list cards is a list; more columns, or grid cards, a grid.
$rvs_columns = preg_match('/--columns-(\d)\b/', $columns_class, $rvs_m) ? (int) $rvs_m[1] : 1;
$rvs_wrap    = ( 'grid' === $card_layout || $rvs_columns > 1 ) ? 'rvs-card-grid' : 'rvs-card-list';
?>
<div class="rvs-listing <?php echo esc_attr($wrapper_class); ?>">

	<?php if ($has_vehicles) : ?>

		<div class="<?php echo esc_attr($rvs_wrap); ?>" style="--rvs-cols: <?php echo esc_attr( (string) $rvs_columns); ?>">
			<?php foreach ($vehicles as $vehicle) : ?>
				<?php
				// The partial escapes each value at its own output site.
				\MHMRentiva\Admin\Core\Utilities\Templates::render('partials/rvs-vehicle-card', array(
					'vehicle' => $vehicle,
					'layout'  => $card_layout,
					'atts'    => $atts,
				));
				?>
			<?php endforeach; ?>
		</div>

	<?php else : ?>

		<div class="rvs-empty">
			<p><?php echo esc_html__('No vehicles found yet.', 'mhm-rentiva'); ?></p>
		</div>

	<?php endif; ?>

</div>
