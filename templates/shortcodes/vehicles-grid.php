<?php

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
?>

<div class="rv-vehicles-grid-container <?php echo esc_attr($wrapper_class); ?>">

	<?php if ($has_vehicles) : ?>

		<!-- Vehicles Grid -->
		<div class="rv-vehicles-grid <?php echo esc_attr($layout_class . ' ' . $columns_class); ?>">

			<?php foreach ($vehicles as $vehicle) : ?>
				<?php
				// Use shared partial for vehicle card
				// Note: We're using the standard core 'partials/vehicle-card'
				// The vehicle data from VehiclesGrid.php has been standardized to match the partial's expectations.
				echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
					'vehicle' => $vehicle,
					'layout'  => 'grid',
					'atts'    => $atts,
				));
				?>
			<?php endforeach; ?>
		</div>

	<?php else : ?>

		<div class="rv-vehicles-grid__empty">
			<p><?php echo esc_html__('No vehicles found yet.', 'mhm-rentiva'); ?></p>
		</div>

	<?php endif; ?>

</div>