<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
// phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores -- Slash-separated filter names are stable legacy hooks in this shortcode.
// phpcs:disable PSR12.Files.FileHeader.IncorrectOrder -- phpcs:disable directives must precede declare() in template files.

declare(strict_types=1);

/**
 * Testimonials Template — VIP Design
 *
 * Shows customer reviews and ratings with a premium card layout.
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;

// Get template variables
$atts             = $atts ?? array();
$testimonials     = $testimonials ?? array();
$total_count      = $total_count ?? 0;
$has_testimonials = $has_testimonials ?? false;

// Shortcode attributes
$limit         = intval($atts['limit'] ?? apply_filters('mhm_rentiva/testimonials/limit', 5));
$show_rating   = ( $atts['show_rating'] ?? '1' ) === '1';
$show_date     = ( $atts['show_date'] ?? '1' ) === '1';
$show_vehicle  = ( $atts['show_vehicle'] ?? '1' ) === '1';
$show_customer = ( $atts['show_customer'] ?? '1' ) === '1';
$layout        = $atts['layout'] ?? 'grid';
$columns       = intval($atts['columns'] ?? 3);
$auto_rotate   = ( $atts['auto_rotate'] ?? '0' ) === '1';
$class         = $atts['class'] ?? '';

// 'carousel', 'slider', and 'vip' all render as the slider/carousel layout.
$is_carousel = in_array($layout, array( 'carousel', 'slider', 'vip' ), true);

/**
 * Format a full name as "First L." (first name + last-name initial).
 * Example: "Mehmet Demir" → "Mehmet D."
 */
$format_name = static function (string $full_name): string {
	$parts = preg_split('/\s+/', trim($full_name), -1, PREG_SPLIT_NO_EMPTY);
	if (count($parts) < 2) {
		return $full_name;
	}
	$last_initial = mb_strtoupper(mb_substr($parts[ count($parts) - 1 ], 0, 1), 'UTF-8');
	return $parts[0] . ' ' . $last_initial . '.';
};

/**
 * Get Gravatar URL from email address.
 */
$get_avatar_url = static function (string $email, int $size = 80): string {
	if (empty($email)) {
		return '';
	}
	$hash = md5(strtolower(trim($email)));
	return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&d=mp';
};
?>

<div class="rv-testimonials rv-layout-<?php echo esc_attr($layout); ?> rv-columns-<?php echo esc_attr($columns); ?> <?php echo esc_attr($class); ?>"
	data-limit="<?php echo esc_attr($limit); ?>"
	data-layout="<?php echo esc_attr($layout); ?>"
	data-auto-rotate="<?php echo $auto_rotate ? '1' : '0'; ?>">

	<?php if ($has_testimonials) : ?>

		<!-- Testimonials Container -->
		<div class="rv-testimonials-container">

			<?php if ($is_carousel) : ?>
				<!-- Carousel / Slider Layout -->
				<div class="rv-testimonials-carousel">
					<div class="rv-carousel-wrapper">
						<div class="rv-carousel-track">
							<?php foreach ($testimonials as $testimonial) : ?>
								<div class="rv-testimonial-item rv-carousel-slide">
									<div class="rv-testimonial-card">

										<!-- Quote Icon -->
										<div class="rv-quote-icon" aria-hidden="true">
											<?php Icons::render('quote', array( 'width' => '32px', 'height' => '32px', 'class' => 'rv-icon rv-icon-quote-deco' )); ?>
										</div>

										<!-- Rating -->
										<?php if ($show_rating && $testimonial['rating'] > 0) : ?>
											<div class="rv-testimonial-rating">
												<?php for ($i = 1; $i <= 5; $i++) : ?>
													<span class="rv-star <?php echo $i <= $testimonial['rating'] ? 'filled' : 'empty'; ?>">
														<?php Icons::render('star', array( 'width' => '15px', 'height' => '15px' )); ?>
													</span>
												<?php endfor; ?>
											</div>
										<?php endif; ?>

										<!-- Review Text -->
										<div class="rv-testimonial-text">
											<p>&ldquo;<?php echo esc_html($testimonial['review']); ?>&rdquo;</p>
										</div>

										<!-- Customer Info -->
										<div class="rv-testimonial-author">
											<?php if ($show_customer && ! empty($testimonial['customer_name'])) : ?>
												<div class="rv-author-avatar">
													<?php
													$avatar_url = $get_avatar_url((string) ($testimonial['customer_email'] ?? ''));
													if (! empty($avatar_url)) :
														?>
														<img src="<?php echo esc_url($avatar_url); ?>"
															alt="<?php echo esc_attr($testimonial['customer_name']); ?>"
															width="40" height="40" loading="lazy" />
													<?php else : ?>
														<span class="rv-avatar-placeholder">
															<?php echo esc_html(mb_strtoupper(mb_substr((string) $testimonial['customer_name'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
														</span>
													<?php endif; ?>
												</div>
												<div class="rv-author-info">
													<span class="rv-author-name"><?php echo esc_html($format_name((string) $testimonial['customer_name'])); ?></span>
													<?php if ($show_vehicle && ! empty($testimonial['vehicle_name'])) : ?>
														<span class="rv-author-title"><?php echo esc_html($testimonial['vehicle_name']); ?></span>
													<?php endif; ?>
													<?php if ($show_date) : ?>
														<span class="rv-author-date"><?php echo esc_html(date_i18n(get_option('date_format', 'd.m.Y'), strtotime((string) $testimonial['date']))); ?></span>
													<?php endif; ?>
												</div>
											<?php endif; ?>
										</div>

									</div><!-- .rv-testimonial-card -->
								</div><!-- .rv-testimonial-item -->
							<?php endforeach; ?>
						</div><!-- .rv-carousel-track -->
					</div><!-- .rv-carousel-wrapper -->

					<!-- Carousel Controls -->
					<button class="rv-carousel-prev" aria-label="<?php echo esc_attr__('Previous', 'mhm-rentiva'); ?>">
						<?php Icons::render('chevron-left'); ?>
					</button>
					<button class="rv-carousel-next" aria-label="<?php echo esc_attr__('Next', 'mhm-rentiva'); ?>">
						<?php Icons::render('chevron-right'); ?>
					</button>

					<!-- Carousel Indicators -->
					<div class="rv-carousel-indicators">
						<?php
						$total_slides = count($testimonials);
						for ($i = 0; $i < $total_slides; $i++) :
							?>
							<button class="rv-carousel-indicator <?php echo $i === 0 ? 'active' : ''; ?>"
								data-slide="<?php echo esc_attr($i); ?>"></button>
						<?php endfor; ?>
					</div>
				</div><!-- .rv-testimonials-carousel -->

			<?php else : ?>
				<!-- Grid / List Layout -->
				<div class="rv-testimonials-<?php echo esc_attr($layout); ?>" data-columns="<?php echo esc_attr($columns); ?>">
					<?php foreach ($testimonials as $testimonial) : ?>
						<div class="rv-testimonial-item">
							<div class="rv-testimonial-card">

								<!-- Quote Icon -->
								<div class="rv-quote-icon" aria-hidden="true">
									<?php Icons::render('quote', array( 'width' => '32px', 'height' => '32px', 'class' => 'rv-icon rv-icon-quote-deco' )); ?>
								</div>

								<!-- Rating -->
								<?php if ($show_rating && $testimonial['rating'] > 0) : ?>
									<div class="rv-testimonial-rating">
										<?php for ($i = 1; $i <= 5; $i++) : ?>
											<span class="rv-star <?php echo $i <= $testimonial['rating'] ? 'filled' : 'empty'; ?>">
												<?php Icons::render('star', array( 'width' => '15px', 'height' => '15px' )); ?>
											</span>
										<?php endfor; ?>
									</div>
								<?php endif; ?>

								<!-- Review Text -->
								<div class="rv-testimonial-text">
									<p>&ldquo;<?php echo esc_html($testimonial['review']); ?>&rdquo;</p>
								</div>

								<!-- Customer Info -->
								<div class="rv-testimonial-author">
									<?php if ($show_customer && ! empty($testimonial['customer_name'])) : ?>
										<div class="rv-author-avatar">
											<?php
											$avatar_url = $get_avatar_url((string) ($testimonial['customer_email'] ?? ''));
											if (! empty($avatar_url)) :
												?>
												<img src="<?php echo esc_url($avatar_url); ?>"
													alt="<?php echo esc_attr($testimonial['customer_name']); ?>"
													width="40" height="40" loading="lazy" />
											<?php else : ?>
												<span class="rv-avatar-placeholder">
													<?php echo esc_html(mb_strtoupper(mb_substr((string) $testimonial['customer_name'], 0, 1, 'UTF-8'), 'UTF-8')); ?>
												</span>
											<?php endif; ?>
										</div>
										<div class="rv-author-info">
											<span class="rv-author-name"><?php echo esc_html($format_name((string) $testimonial['customer_name'])); ?></span>
											<?php if ($show_vehicle && ! empty($testimonial['vehicle_name'])) : ?>
												<span class="rv-author-title"><?php echo esc_html($testimonial['vehicle_name']); ?></span>
											<?php endif; ?>
											<?php if ($show_date) : ?>
												<span class="rv-author-date"><?php echo esc_html(date_i18n(get_option('date_format', 'd.m.Y'), strtotime((string) $testimonial['date']))); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>

							</div><!-- .rv-testimonial-card -->
						</div><!-- .rv-testimonial-item -->
					<?php endforeach; ?>
				</div><!-- .rv-testimonials-{layout} -->
			<?php endif; ?>

		</div><!-- .rv-testimonials-container -->

		<!-- Load More Button -->
		<?php if (count($testimonials) < $total_count) : ?>
			<div class="rv-testimonials-load-more">
				<button class="rv-load-more-btn" data-page="1">
					<?php echo esc_html__('Load More Reviews', 'mhm-rentiva'); ?>
					<span class="rv-loading-spinner" style="display: none;">
						<?php Icons::render('refresh'); ?>
					</span>
				</button>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<!-- No Testimonials -->
		<div class="rv-no-testimonials">
			<div class="rv-no-testimonials-icon">
				<?php Icons::render('quote', array( 'width' => '48px', 'height' => '48px' )); ?>
			</div>
			<h4><?php echo esc_html__('No Reviews Yet', 'mhm-rentiva'); ?></h4>
			<p><?php echo esc_html__('Be the first to leave a review!', 'mhm-rentiva'); ?></p>
		</div>
	<?php endif; ?>

</div><!-- .rv-testimonials -->
