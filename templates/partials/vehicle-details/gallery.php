<?php
/**
 * Vehicle page gallery: a large photo and four small ones, the last cell
 * opening the full-screen viewer ("All photos (N)"). On a phone the photos
 * scroll sideways with an "n / N" counter. The viewer is a shared rvs dialog;
 * rvs-vehicle.js fills it from data-rvs-gallery.
 *
 * @var array<string, mixed> $view VehicleDetailsView::build().
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_view   = (array) ( $view ?? array() );
$mhmrentiva_images = array_values( (array) $mhmrentiva_view['gallery']);
$mhmrentiva_uid    = (string) $mhmrentiva_view['uid'];
$mhmrentiva_count  = count($mhmrentiva_images);
$mhmrentiva_data   = array_map(
	static fn(array $image): array => array(
		'src' => (string) ( '' !== $image['full'] ? $image['full'] : $image['large'] ),
		'alt' => (string) $image['alt'],
	),
	$mhmrentiva_images
);
?>
<section class="rvs-vd__gallery<?php echo $mhmrentiva_count < 5 ? ' rvs-vd__gallery--' . esc_attr( (string) $mhmrentiva_count) : ''; ?>" aria-label="<?php esc_attr_e('Photos', 'mhm-rentiva'); ?>" data-rvs-gallery="<?php echo esc_attr( (string) wp_json_encode($mhmrentiva_data)); ?>">
	<div class="rvs-vd__strip" data-rvs-gallery-strip>
	<?php foreach (array_slice($mhmrentiva_images, 0, 5) as $mhmrentiva_i => $mhmrentiva_image) : ?>
		<?php if (4 === $mhmrentiva_i && $mhmrentiva_count > 5) : ?>
			<button type="button" class="rvs-vd__photo rvs-vd__photo--more" data-rvs-gallery-open="4">
				<img src="<?php echo esc_url( (string) $mhmrentiva_image['thumb']); ?>" alt="" loading="lazy" decoding="async">
				<span>
					<?php
					/* translators: %s: number of photos. */
					echo esc_html(sprintf(__('All photos (%s)', 'mhm-rentiva'), number_format_i18n($mhmrentiva_count)));
					?>
				</span>
			</button>
		<?php else : ?>
			<?php
			/* translators: 1: photo number, 2: number of photos. */
			$mhmrentiva_open = sprintf(__('Open photo %1$s of %2$s', 'mhm-rentiva'), number_format_i18n($mhmrentiva_i + 1), number_format_i18n($mhmrentiva_count));
			?>
			<button type="button" class="rvs-vd__photo<?php echo 0 === $mhmrentiva_i ? ' rvs-vd__photo--main' : ''; ?>" data-rvs-gallery-open="<?php echo esc_attr( (string) $mhmrentiva_i); ?>" aria-label="<?php echo esc_attr($mhmrentiva_open); ?>">
				<img src="<?php echo esc_url( (string) ( 0 === $mhmrentiva_i ? $mhmrentiva_image['large'] : $mhmrentiva_image['thumb'] )); ?>" alt="<?php echo esc_attr( (string) $mhmrentiva_image['alt']); ?>"<?php echo 0 === $mhmrentiva_i ? ' fetchpriority="high"' : ' loading="lazy"'; ?> decoding="async">
			</button>
		<?php endif; ?>
	<?php endforeach; ?>
	</div>
	<span class="rvs-vd__counter" data-rvs-gallery-counter aria-hidden="true">1 / <?php echo esc_html(number_format_i18n($mhmrentiva_count)); ?></span>

	<div class="rvs-dialog rvs-vd__viewer" data-rvs-gallery-viewer hidden>
		<div class="rvs-dialog__panel">
			<div class="rvs-dialog__head">
				<h2 class="rvs-dialog__title" id="<?php echo esc_attr($mhmrentiva_uid); ?>-viewer-title"><?php echo esc_html( (string) $mhmrentiva_view['title']); ?> <span class="rvs-vd__viewer-count" data-rvs-gallery-count></span></h2>
				<button type="button" class="rvs-btn rvs-btn--icon rvs-dialog__close" data-rvs-dialog-close aria-label="<?php esc_attr_e('Close', 'mhm-rentiva'); ?>"><span aria-hidden="true">×</span></button>
			</div>
			<div class="rvs-dialog__body rvs-vd__viewer-body">
				<button type="button" class="rvs-btn rvs-btn--icon rvs-vd__viewer-nav" data-rvs-gallery-step="-1" aria-label="<?php esc_attr_e('Previous photo', 'mhm-rentiva'); ?>"><span aria-hidden="true">‹</span></button>
				<img class="rvs-vd__viewer-img" data-rvs-gallery-img src="<?php echo esc_url( (string) $mhmrentiva_data[0]['src']); ?>" alt="<?php echo esc_attr( (string) $mhmrentiva_data[0]['alt']); ?>" loading="lazy" decoding="async">
				<button type="button" class="rvs-btn rvs-btn--icon rvs-vd__viewer-nav" data-rvs-gallery-step="1" aria-label="<?php esc_attr_e('Next photo', 'mhm-rentiva'); ?>"><span aria-hidden="true">›</span></button>
			</div>
		</div>
	</div>
</section>
