<?php
/**
 * Testimonials: grid, list or slider of the same card (PR F, drawing `Yorumlar`).
 *
 * The page's `.rvs-shell` wrapper comes from AbstractShortcode. Script hooks
 * (testimonials.js): [data-rvs-testimonials], [data-rvs-items], [data-rvs-card],
 * [data-rvs-load-more], [data-rvs-prev], [data-rvs-next], [data-rvs-dots].
 *
 * @var array<string, mixed>              $atts     Resolved attributes.
 * @var string                            $layout   grid, list or carousel.
 * @var int                               $columns  1 to 4.
 * @var array<int, array<string, mixed>>  $cards    ReviewsView::card() rows.
 * @var bool                              $has_more More reviews than the page shows.
 * @var array<string, string>             $load_more Order and filters Load more continues with.
 */

declare(strict_types=1);

use MHMRentiva\Admin\Core\Utilities\Templates;

if (! defined('ABSPATH')) {
	exit;
}

$mhmrentiva_t_atts     = (array) ( $atts ?? array() );
$mhmrentiva_t_cards    = (array) ( $cards ?? array() );
$mhmrentiva_t_layout   = (string) ( $layout ?? 'grid' );
$mhmrentiva_t_carousel = 'carousel' === $mhmrentiva_t_layout;
$mhmrentiva_t_columns  = (int) ( $columns ?? 3 );
$mhmrentiva_t_flag     = static fn (string $key): bool => '1' === (string) ( $mhmrentiva_t_atts[ $key ] ?? '1' );
$mhmrentiva_t_show     = array(
	'rating'   => $mhmrentiva_t_flag('show_rating'),
	'customer' => $mhmrentiva_t_flag('show_customer'),
	'avatar'   => $mhmrentiva_t_flag('show_author_avatar'),
	'vehicle'  => $mhmrentiva_t_flag('show_vehicle'),
	'date'     => $mhmrentiva_t_flag('show_date'),
	'quotes'   => $mhmrentiva_t_flag('show_quotes'),
);
$mhmrentiva_t_rotate   = '1' === (string) ( $mhmrentiva_t_atts['auto_rotate'] ?? '0' );
$mhmrentiva_t_count    = count($mhmrentiva_t_cards);
$mhmrentiva_t_classes  = trim(
	'rvs-testimonials rvs-testimonials--' . $mhmrentiva_t_layout
	. ' rvs-testimonials--cols-' . $mhmrentiva_t_columns
	. ' ' . implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', (string) ( $mhmrentiva_t_atts['class'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY) ?: array()))
);
?>
<?php if (empty($mhmrentiva_t_cards)) : ?>
	<div class="<?php echo esc_attr($mhmrentiva_t_classes); ?>">
		<div class="rvs-testimonials__empty">
			<p class="rvs-testimonials__empty-title"><?php esc_html_e('No reviews yet.', 'mhm-rentiva'); ?></p>
		</div>
	</div>
<?php else : ?>
	<div class="<?php echo esc_attr($mhmrentiva_t_classes); ?>" data-rvs-testimonials data-limit="<?php echo esc_attr( (string) (int) ( $mhmrentiva_t_atts['limit'] ?? 5 )); ?>" data-layout="<?php echo esc_attr($mhmrentiva_t_layout); ?>" data-auto-rotate="<?php echo $mhmrentiva_t_rotate ? '1' : '0'; ?>"
	<?php
	foreach ( (array) ( $load_more ?? array() ) as $mhmrentiva_t_name => $mhmrentiva_t_value) {
		echo ' data-' . esc_attr( (string) $mhmrentiva_t_name) . '="' . esc_attr( (string) $mhmrentiva_t_value) . '"';
	}
	?>
	<?php if ($mhmrentiva_t_carousel) : ?>
		role="region" aria-label="<?php esc_attr_e('Customer reviews', 'mhm-rentiva'); ?>"
	<?php endif; ?>>
		<div class="rvs-testimonials__items" data-rvs-items<?php echo $mhmrentiva_t_carousel ? ' tabindex="0" role="group" aria-label="' . esc_attr__('Reviews', 'mhm-rentiva') . '"' : ''; ?>>
			<?php foreach ($mhmrentiva_t_cards as $mhmrentiva_t_card) : ?>
				<?php
				Templates::render(
					'partials/rvs/testimonial-card',
					array(
						'card' => $mhmrentiva_t_card,
						'show' => $mhmrentiva_t_show,
					)
				);
				?>
			<?php endforeach; ?>
		</div>

		<?php if ($mhmrentiva_t_carousel && $mhmrentiva_t_count > 1) : ?>
			<div class="rvs-testimonials__controls">
				<button type="button" class="rvs-btn rvs-btn--secondary rvs-btn--icon" data-rvs-prev aria-label="<?php esc_attr_e('Previous review', 'mhm-rentiva'); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>
				</button>
				<div class="rvs-testimonials__dots" role="group" aria-label="<?php esc_attr_e('Choose a review', 'mhm-rentiva'); ?>" data-rvs-dots>
					<?php for ($mhmrentiva_t_i = 1; $mhmrentiva_t_i <= $mhmrentiva_t_count; $mhmrentiva_t_i++) : ?>
						<?php /* translators: 1: position of the review, 2: number of reviews. */ ?>
						<button type="button" class="rvs-testimonials__dot" aria-label="<?php echo esc_attr(sprintf(__('Review %1$s of %2$s', 'mhm-rentiva'), number_format_i18n($mhmrentiva_t_i), number_format_i18n($mhmrentiva_t_count))); ?>"<?php echo 1 === $mhmrentiva_t_i ? ' aria-current="true"' : ''; ?>><span></span></button>
					<?php endfor; ?>
				</div>
				<button type="button" class="rvs-btn rvs-btn--secondary rvs-btn--icon" data-rvs-next aria-label="<?php esc_attr_e('Next review', 'mhm-rentiva'); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6"/></svg>
				</button>
			</div>
		<?php endif; ?>

		<?php if (! empty($has_more)) : ?>
			<div class="rvs-testimonials__more">
				<button type="button" class="rvs-btn rvs-btn--secondary" data-rvs-load-more data-page="1"><?php esc_html_e('Load more reviews', 'mhm-rentiva'); ?></button>
				<p class="rvs-testimonials__error" role="alert" data-rvs-error hidden></p>
			</div>
			<div hidden data-rvs-card-template>
				<?php
				Templates::render(
					'partials/rvs/testimonial-card',
					array(
						'card' => array(
							'rating'      => 3,
							'stars_label' => '',
							'text'        => '',
							'initial'     => '',
							'name'        => '',
							'vehicle'     => '-',
							'date'        => '-',
						),
						'show' => $mhmrentiva_t_show,
					)
				);
				?>
			</div>
		<?php endif; ?>
		<span class="rvs-sr-only" role="status" data-rvs-live></span>
	</div>
<?php endif; ?>
