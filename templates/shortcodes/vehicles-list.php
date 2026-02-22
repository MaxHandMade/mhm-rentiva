<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic HTML is rendered by internal template layer with localized escaping.

/**
 * Vehicles List Template - Standardized with Partial
 *
 * @var array $atts Shortcode attributes
 * @var array $vehicles Vehicle data array
 * @var int $total_vehicles Total vehicle count
 * @var bool $has_vehicles Whether vehicles exist
 * @var string $layout_class Layout CSS class
 * @var string $columns_class Columns CSS class
 * @var array $context Global settings context
 */

if (! defined('ABSPATH')) {
	exit;
}

// Data Preparation & Defaults
$atts           = $atts ?? array();
$vehicles       = $vehicles ?? array();
$has_vehicles   = $has_vehicles ?? false;
$layout_class   = $layout_class ?? 'rv-vehicles-list';
$columns_class  = $columns_class ?? 'rv-vehicles-list--columns-1';
$wrapper_class  = $wrapper_class ?? '';
?>

<div class="rv-vehicles-list-container <?php echo esc_attr($wrapper_class); ?>">

	<?php if ($has_vehicles) : ?>

		<div class="rv-vehicles-list__wrapper <?php echo esc_attr($layout_class . ' ' . $columns_class); ?>" data-columns="<?php echo esc_attr($atts['columns'] ?? '1'); ?>">

			<?php foreach ($vehicles as $vehicle) : ?>
				<?php
				// Use shared partial for vehicle card
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by trusted internal template with escaped dynamic attributes.
				echo \MHMRentiva\Admin\Core\Utilities\Templates::render('partials/vehicle-card', array(
					'vehicle' => $vehicle,
					'layout'  => 'list',
					'atts'    => $atts,
				), true);
				?>
			<?php endforeach; ?>
		</div>

	<?php else : ?>

		<div class="rv-vehicles-list__empty">
			<p><?php echo esc_html__('No vehicles found yet.', 'mhm-rentiva'); ?></p>
		</div>

	<?php endif; ?>

</div>
