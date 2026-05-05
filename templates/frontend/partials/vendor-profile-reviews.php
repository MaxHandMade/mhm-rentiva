<?php
/**
 * Vendor Profile — reviews partial.
 *
 * @var array<string,mixed>  $data
 * @var array<string,string> $atts
 */
if (!defined('ABSPATH')) {
    exit;
}

$reviews = $data['reviews'] ?? [];
?>
<div class="mhm-vendor-reviews">
	<div class="label"><?php esc_html_e('Recent Reviews', 'mhm-rentiva'); ?></div>
	<?php if (empty($reviews)) : ?>
		<div class="mhm-vendor-reviews-empty">
			<?php
			$message = (string) ( $atts['empty_reviews_message'] ?? '' );
			echo esc_html($message !== '' ? $message : __('No reviews yet — be the first to leave one.', 'mhm-rentiva'));
			?>
		</div>
	<?php else : ?>
		<ul class="mhm-vendor-review-list">
			<?php foreach ($reviews as $review) : ?>
				<li class="mhm-vendor-review">
					<?php if ($review['rating'] > 0) : ?>
						<div class="mhm-vendor-review-rating">
							<?php echo esc_html(str_repeat('★', (int) $review['rating'])); ?>
						</div>
					<?php endif; ?>
					<div class="mhm-vendor-review-meta">
						<strong><?php echo esc_html($review['author']); ?></strong>
						<span class="mhm-vendor-review-date">
							<?php
							$review_ts = strtotime( (string) $review['date']);
							if ($review_ts !== false) {
								echo esc_html(date_i18n(get_option('date_format'), $review_ts));
							} else {
								echo esc_html( (string) $review['date']);
							}
							?>
						</span>
					</div>
					<div class="mhm-vendor-review-content">
						<?php echo wp_kses_post($review['content']); ?>
					</div>
					<div class="mhm-vendor-review-source">
						<?php
						printf(
							/* translators: %s: HTML anchor link to the reviewed vehicle */
							esc_html__('→ %s', 'mhm-rentiva'),
							'<a href="' . esc_url($review['vehicle_url']) . '">' . esc_html($review['vehicle_title']) . '</a>'
						);
						?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
