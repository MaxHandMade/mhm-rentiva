<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * Vehicle Rating Form Template
 *
 * @var int $vehicle_id
 * @var array $vehicle_rating
 * @var array|null $user_rating
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Helpers\Icons;



// â­ Asset management removed - VehicleRatingForm Controller handles asset loading
// Assets are enqueued via VehicleRatingForm::enqueue_assets() method
// Localized data is provided via VehicleRatingForm::get_localized_strings() method

// Use the data array passed to template
$data = $data ?? array();

// Get data from vars array
$vars           = $vars ?? array();
$vehicle_id     = $data['vehicle_id'] ?? $vars['vehicle_id'] ?? get_the_ID();
$vehicle_rating = $data['vehicle_rating'] ?? $vars['vehicle_rating'] ?? array();
$user_rating    = $data['user_rating'] ?? $vars['user_rating'] ?? null;
$is_logged_in   = $data['is_logged_in'] ?? $vars['is_logged_in'] ?? is_user_logged_in();

// If vehicle_id still not available, get from global
if (! $vehicle_id || $vehicle_id <= 0) {
	global $post;
	$vehicle_id = $post->ID ?? 0;
}

// CRITICAL: If vehicle ID is not available, render the template
if (! $vehicle_id || $vehicle_id <= 0) {
	return '<div class="rv-rating-form-error">' . esc_html__('Vehicle ID not found', 'mhm-rentiva') . '</div>';
}

// Get comment settings
$require_login        = $comments_settings['approval']['require_login'] ?? true;
$allow_guest_comments = $comments_settings['approval']['allow_guest_comments'] ?? false;

// Login check - according to settings
$is_logged_in         = is_user_logged_in();
$can_comment          = $is_logged_in || (! $require_login && $allow_guest_comments);
$current_user_rating  = $user_rating ? floatval($user_rating['rating']) : 0;
$current_user_comment = $user_rating ? $user_rating['comment'] : '';

// Premium rating distribution (5 / 4 / 3) for summary bars.
$rating_distribution = array(5 => 0, 4 => 0, 3 => 0);
$rating_total        = max(0, intval($vehicle_rating['count'] ?? 0));
if ($vehicle_id > 0) {
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded aggregate query for one vehicle rating summary.
		$wpdb->prepare(
			"SELECT CAST(cm.meta_value AS UNSIGNED) AS rating_value, COUNT(*) AS total
			FROM {$wpdb->comments} c
			INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
			WHERE c.comment_post_ID = %d
				AND c.comment_approved = '1'
				AND c.comment_type = 'review'
				AND cm.meta_key = 'mhm_rating'
				AND cm.meta_value IN ('3','4','5')
			GROUP BY rating_value",
			$vehicle_id
		)
	);
	if (is_array($rows)) {
		foreach ($rows as $row) {
			$key = intval($row->rating_value ?? 0);
			if (isset($rating_distribution[$key])) {
				$rating_distribution[$key] = intval($row->total ?? 0);
			}
		}
	}
}

// Get settings for character limits
$full_comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
$display_settings = array_merge(
	$full_comments_settings['display'] ?? array(),
	$full_comments_settings['limits'] ?? array()
);

$allowed_rating_svg_tags = array(
	'span' => array(
		'class' => true,
	),
	'svg'  => array(
		'width'       => true,
		'height'      => true,
		'viewBox'     => true,
		'fill'        => true,
		'xmlns'       => true,
		'class'       => true,
		'aria-hidden' => true,
		'focusable'   => true,
		'role'        => true,
		'style'       => true,
	),
	'path' => array(
		'd'    => true,
		'fill' => true,
	),
);

// Debug: Check user rating information
if (defined('WP_DEBUG') && WP_DEBUG) {
	echo '<!-- Debug: User rating data: ' . esc_html((string) wp_json_encode($user_rating)) . ' -->';
}

?>

<div class="rv-rating-form" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>" data-debug-vehicle-id="<?php echo esc_attr($vehicle_id); ?>" data-debug-data="<?php echo esc_attr(wp_json_encode($data)); ?>" data-render-time="<?php echo esc_attr((string) microtime(true)); ?>">

	<!-- Current Rating Display -->
	<div class="rv-rating-display">
		<div class="rv-rating-summary">
			<div class="rv-rating-summary-left">
				<div class="rv-rating-average"><?php echo esc_html(number_format((float) ($vehicle_rating['average'] ?? 0), 1)); ?></div>
				<div class="rv-rating-stars">
					<?php echo wp_kses((string) ($vehicle_rating['stars'] ?? ''), $allowed_rating_svg_tags); ?>
				</div>
				<div class="rv-rating-total-label">
					<?php
					$count = intval($vehicle_rating['count'] ?? 0);
					printf(
						esc_html(_n('%d review', '%d reviews', $count, 'mhm-rentiva')),
						$count
					);
					?>
				</div>
			</div>
			<div class="rv-rating-summary-right">
				<?php foreach (array(5, 4, 3) as $score) : ?>
					<?php
					$score_count = intval($rating_distribution[$score] ?? 0);
					$percent = $rating_total > 0 ? (int) round(($score_count / $rating_total) * 100) : 0;
					?>
					<div class="rv-rating-dist-row">
						<span class="rv-rating-dist-label"><?php echo esc_html((string) $score); ?></span>
						<div class="rv-rating-dist-track" aria-hidden="true">
							<span class="rv-rating-dist-fill" style="width: <?php echo esc_attr((string) $percent); ?>%;"></span>
						</div>
						<span class="rv-rating-dist-percent"><?php echo esc_html((string) $percent); ?>%</span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Rating List - Show to everyone (TOP) -->
	<div class="rv-ratings-list" id="ratings-list-<?php echo esc_attr($vehicle_id); ?>">
		<?php
		// Get settings from comments settings
		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$display_settings  = $comments_settings['display'] ?? array();

		// Get WordPress comments - only approved comments
		$comments = get_comments(
			array(
				'post_id'                   => $vehicle_id,
				// 'type'                      => 'review', // REMOVED: Allow all comment types (reviews + standard comments)
				'status'                    => array('approve', 'pending'), // Both approved and pending comments
				'number'                    => \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_comments_per_page(),
				'orderby'                   => 'comment_date',
				'order'                     => 'DESC',
				'no_found_rows'             => true,
				'update_comment_meta_cache' => false,
				'cache_results'             => false,
			)
		);

		// Debug: Check comment count
		if (defined('WP_DEBUG') && WP_DEBUG) {
			echo '<!-- Debug: Found ' . esc_html((string) count($comments)) . ' approved comments for vehicle ' . esc_html((string) $vehicle_id) . ' -->';
		}

		if (! empty($comments)) :
			// Batch fetch verified review IDs (single query, no N+1)
			$verified_ids = \MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper::get_verified_comment_ids_for_vehicle((int) $vehicle_id);
		?>
			<div class="rv-reviews-section">
				<h4 class="rv-reviews-title"><?php echo esc_html__('Reviews', 'mhm-rentiva'); ?></h4>
				<div class="rv-reviews-list">
					<?php
					foreach ($comments as $comment) :
						// Email check for guest users, user_id check for normal users
						if (is_user_logged_in()) {
							$is_current_user = $comment->user_id == get_current_user_id();
						} else {
							// Email check for guest users
							$guest_email_cookie = isset($_COOKIE['guest_email']) ? sanitize_email(wp_unslash($_COOKIE['guest_email'])) : '';
							$is_current_user    = ! empty($comment->comment_author_email) &&
								$comment->comment_author_email === $guest_email_cookie;
						}
						$rating = get_comment_meta($comment->comment_ID, 'mhm_rating', true);

						// Get author name with fallback
						$full_name = $comment->comment_author;
						if (empty($full_name) && $comment->user_id) {
							$user = get_userdata($comment->user_id);
							$full_name = $user ? $user->display_name : '';
						}
						if (empty($full_name)) {
							$full_name = __('Anonymous', 'mhm-rentiva');
						}

						// Privacy: Mask the author name (e.g., "John Doe" -> "John D.")
						$name_parts = explode(' ', trim($full_name));
						if (count($name_parts) > 1) {
							$first_name = $name_parts[0];
							$last_initial = mb_substr(end($name_parts), 0, 1, 'UTF-8') . '.';
							$masked_name = $first_name . ' ' . $last_initial;
						} else {
							$masked_name = $full_name;
						}
					?>
						<div class="rv-review-item" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
							<div class="rv-review-header">
								<div class="rv-review-author">
									<?php if ($display_settings['show_avatars'] ?? true) : ?>
										<div class="rv-review-avatar">
											<img src="<?php echo esc_url(get_avatar_url($comment->comment_author_email, ['size' => 48])); ?>" alt="<?php echo esc_attr($masked_name); ?>" width="48" height="48" loading="lazy" />
										</div>
									<?php endif; ?>
									<div class="rv-review-author-info">
										<span class="rv-review-author-name"><?php echo esc_html($masked_name); ?></span>
										<?php if (in_array((int) $comment->comment_ID, $verified_ids, true)) : ?>
											<span class="mhm-review-badge mhm-review-badge--verified">
												<?php Icons::render('check', ['width' => '12', 'height' => '12']); ?>
												<?php echo esc_html__('Verified Rental', 'mhm-rentiva'); ?>
											</span>
										<?php endif; ?>
										<span class="rv-review-date"><?php echo esc_html(human_time_diff(strtotime($comment->comment_date)) . ' ' . esc_html__('ago', 'mhm-rentiva')); ?></span>
										<?php if (($display_settings['show_ratings'] ?? true) && $rating) : ?>
											<div class="rv-review-rating">
												<?php for ($i = 1; $i <= 5; $i++) :
													$star_fill = $i <= (int) $rating ? '#fbbf24' : '#d1d5db';
												?>
													<span class="rv-star <?php echo $i <= (int) $rating ? 'active' : ''; ?>">
														<?php
														$star_color = $i <= (int) $rating ? '#fbbf24' : '#d1d5db';
														Icons::render('star', ['width' => '14', 'height' => '14', 'style' => "fill: $star_color; color: $star_color;"]);
														?>
													</span>
												<?php endfor; ?>
											</div>
										<?php endif; ?>
									</div>
								</div>
								<?php
								$show_actions = $is_current_user && (($display_settings['allow_editing'] ?? true) || ($display_settings['allow_deletion'] ?? true));
								?>
								<?php if ($show_actions) : ?>
									<div class="rv-review-actions">
										<?php if ($display_settings['allow_editing'] ?? true) : ?>
											<button class="rv-edit-comment-btn" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>" data-rating="<?php echo esc_attr($rating); ?>" data-comment="<?php echo esc_attr($comment->comment_content); ?>">
												<?php Icons::render('edit'); ?>
												<?php echo esc_html__('Edit', 'mhm-rentiva'); ?>
											</button>
										<?php endif; ?>
										<?php if ($display_settings['allow_deletion'] ?? true) : ?>
											<button class="rv-delete-comment-btn" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
												<?php Icons::render('trash'); ?>
												<?php echo esc_html__('Delete', 'mhm-rentiva'); ?>
											</button>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
							<div class="rv-review-content">
								<?php echo wp_kses_post($comment->comment_content); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php else : ?>
			<div class="rv-no-reviews">
				<p><?php echo esc_html__('No reviews yet.', 'mhm-rentiva'); ?></p>
			</div>
		<?php endif; ?>
	</div>

	<!-- Rating Form (BOTTOM) -->
	<?php if ($can_comment) : ?>
		<div class="rv-rating-form-container">
			<h4 class="rv-rating-form-title"><?php echo esc_html__('Rate This Vehicle', 'mhm-rentiva'); ?></h4>

			<form class="rv-rating-form-content" id="rating-form-<?php echo esc_attr($vehicle_id); ?>">
				<?php wp_nonce_field('mhm_rentiva_nonce', 'rating_nonce'); ?>
				<input type="hidden" name="vehicle_id" value="<?php echo esc_attr($vehicle_id); ?>">

				<?php if (! $is_logged_in && $allow_guest_comments) : ?>
					<!-- Name and email fields for guest users -->
					<div class="rv-guest-fields">
						<div class="rv-guest-name">
							<label for="guest-name-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Name:', 'mhm-rentiva'); ?></label>
							<input type="text" name="guest_name" id="guest-name-<?php echo esc_attr($vehicle_id); ?>"
								class="rv-rating-input-field"
								placeholder="<?php echo esc_attr__('Enter your name', 'mhm-rentiva'); ?>"
								required>
						</div>
						<div class="rv-guest-email">
							<label for="guest-email-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Email:', 'mhm-rentiva'); ?></label>
							<input type="email" name="guest_email" id="guest-email-<?php echo esc_attr($vehicle_id); ?>"
								class="rv-rating-input-field"
								placeholder="<?php echo esc_attr__('Enter your email', 'mhm-rentiva'); ?>"
								required>
						</div>
					</div>
				<?php endif; ?>

				<!-- Rating Selection -->
				<div class="rv-rating-input">
					<label class="rv-rating-label"><?php echo esc_html__('Your Rating:', 'mhm-rentiva'); ?></label>
					<div class="rv-rating-stars-input">
						<?php for ($i = 5; $i >= 1; $i--) : ?>
							<input type="radio" name="rating" value="<?php echo (int) $i; ?>"
								id="rating-<?php echo esc_attr($vehicle_id); ?>-<?php echo (int) $i; ?>"
								<?php checked($current_user_rating, $i); ?>>
							<label for="rating-<?php echo esc_attr($vehicle_id); ?>-<?php echo (int) $i; ?>"
								class="rv-star-input">
								<?php Icons::render('star', ['width' => '24', 'height' => '24', 'class' => 'rv-star-icon']); ?>
							</label>
						<?php endfor; ?>
					</div>
				</div>

				<!-- Comment Area -->
				<div class="rv-rating-comment">
					<label for="rating-comment-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Comment:', 'mhm-rentiva'); ?></label>
					<textarea name="comment" id="rating-comment-<?php echo esc_attr($vehicle_id); ?>"
						class="rv-rating-textarea"
						placeholder="<?php echo esc_attr__('Share your thoughts about the vehicle...', 'mhm-rentiva'); ?>"
						rows="4"
						data-min-length="<?php echo esc_attr($display_settings['comment_length_min'] ?? 5); ?>"
						data-max-length="<?php echo esc_attr($display_settings['comment_length_max'] ?? 1000); ?>"><?php echo esc_textarea($current_user_comment); ?></textarea>
					<div class="rv-char-counter">
						<span class="rv-char-current">0</span> / <span class="rv-char-max"><?php echo esc_html($display_settings['comment_length_max'] ?? 1000); ?></span>
						<span class="rv-char-min-notice">(<?php
															/* translators: %d: Minimum character length */
															echo esc_html(sprintf(__('min %d', 'mhm-rentiva'), $display_settings['comment_length_min'] ?? 5));
															?>)</span>
					</div>
				</div>

				<!-- Nonce Field -->
				<input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_rating_nonce')); ?>">

				<!-- Submit Button -->
				<div class="rv-rating-submit">
					<button type="submit" class="rv-btn rv-btn-primary">
						<?php echo $user_rating ? esc_html__('Update Rating', 'mhm-rentiva') : esc_html__('Submit Rating', 'mhm-rentiva'); ?>
					</button>
					<?php if ($user_rating) : ?>
						<button type="button" class="rv-btn rv-btn-danger rv-delete-rating" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
							<?php echo esc_html__('Delete Rating', 'mhm-rentiva'); ?>
						</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
	<?php else : ?>
		<div class="rv-rating-login-notice">
			<div class="rv-login-required">
				<div class="rv-login-icon">
					<?php Icons::render('users', ['width' => '24', 'height' => '24']); ?>
				</div>
				<h4><?php echo esc_html__('Login Required', 'mhm-rentiva'); ?></h4>
				<p><?php echo esc_html__('You must be logged in to submit a rating and review.', 'mhm-rentiva'); ?></p>
				<div class="rv-login-actions">
					<a href="#" class="rv-btn rv-btn-primary rv-show-login-form">
						<?php echo esc_html__('Login', 'mhm-rentiva'); ?>
					</a>
					<?php if (get_option('users_can_register')) : ?>
						<a href="#" class="rv-btn rv-btn-secondary rv-show-register-form">
							<?php echo esc_html__('Register', 'mhm-rentiva'); ?>
						</a>
					<?php endif; ?>
				</div>

				<!-- Login Form Modal -->
				<div class="rv-login-modal" style="display: none;">
					<div class="rv-modal-content">
						<span class="rv-modal-close">&times;</span>
						<h3><?php echo esc_html__('Login', 'mhm-rentiva'); ?></h3>
						<?php echo wp_kses_post(do_shortcode('[rentiva_login_form]')); ?>
					</div>
				</div>

				<!-- Register Form Modal -->
				<div class="rv-register-modal" style="display: none;">
					<div class="rv-modal-content">
						<span class="rv-modal-close">&times;</span>
						<h3><?php echo esc_html__('Register', 'mhm-rentiva'); ?></h3>
						<?php echo wp_kses_post(do_shortcode('[rentiva_register_form]')); ?>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

</div>
