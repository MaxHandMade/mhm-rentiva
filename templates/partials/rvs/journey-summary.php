<?php
/**
 * The checkout and thank-you journey's booking summary: vehicle, window,
 * breakdown and, on the thank-you page, a heading and the My bookings link.
 *
 * The outer element is also the order-review fragment WooCommerce replaces
 * after update_checkout (CheckoutJourney::order_review_fragments()), so it is
 * one element with nothing around it.
 *
 * @var array<string, mixed> $summary CheckoutJourney view: heading, title, image_id, dates, breakdown, link.
 *
 * @package MHMRentiva
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_js       = (array) ( $summary ?? array() );
$mhmrentiva_js_head  = (string) ( $mhmrentiva_js['heading'] ?? '' );
$mhmrentiva_js_title = (string) ( $mhmrentiva_js['title'] ?? '' );
$mhmrentiva_js_image = (int) ( $mhmrentiva_js['image_id'] ?? 0 );
$mhmrentiva_js_dates = (string) ( $mhmrentiva_js['dates'] ?? '' );
$mhmrentiva_js_link  = (string) ( $mhmrentiva_js['link'] ?? '' );
?>
<div class="rvs-journey__summary">
	<?php if ('' !== $mhmrentiva_js_head) : ?>
		<h2 class="rvs-journey__title"><?php echo esc_html($mhmrentiva_js_head); ?></h2>
	<?php endif; ?>
	<div class="rvs-journey__vehicle">
		<?php if ($mhmrentiva_js_image > 0) : ?>
			<div class="rvs-journey__media">
				<?php
				echo wp_get_attachment_image(
					$mhmrentiva_js_image,
					'medium',
					false,
					array(
						'class' => 'rvs-journey__img',
						'alt'   => '',
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div class="rvs-journey__about">
			<?php if ('' !== $mhmrentiva_js_title) : ?>
				<h3 class="rvs-journey__vehicle-title"><?php echo esc_html($mhmrentiva_js_title); ?></h3>
			<?php endif; ?>
			<?php if ('' !== $mhmrentiva_js_dates) : ?>
				<p class="rvs-journey__dates"><?php echo esc_html($mhmrentiva_js_dates); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<div class="rvs-journey__breakdown">
		<?php
		Templates::render(
			'partials/rvs/breakdown',
			array(
				'breakdown'   => (array) ( $mhmrentiva_js['breakdown'] ?? array() ),
				'collapsible' => false,
			)
		);
		?>
	</div>
	<?php if ('' !== $mhmrentiva_js_link) : ?>
		<a class="rvs-journey__link" href="<?php echo esc_url($mhmrentiva_js_link); ?>"><?php esc_html_e('My bookings', 'mhm-rentiva'); ?></a>
	<?php endif; ?>
</div>
