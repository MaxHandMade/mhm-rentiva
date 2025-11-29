<?php
/**
 * Vehicle Rating Form Template
 * 
 * @var int $vehicle_id
 * @var array $vehicle_rating
 * @var array|null $user_rating
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin textdomain
if (!function_exists('mhm_rentiva_load_textdomain')) {
    function mhm_rentiva_load_textdomain() {
        load_plugin_textdomain('mhm-rentiva', false, dirname(plugin_basename(__FILE__)) . '/../../languages/');
    }
    mhm_rentiva_load_textdomain();
}

// ⭐ Asset management removed - VehicleRatingForm Controller handles asset loading
// Assets are enqueued via VehicleRatingForm::enqueue_assets() method
// Localized data is provided via VehicleRatingForm::get_localized_strings() method

// Use the data array passed to template
$data = $data ?? [];

// Get data from vars array
$vars = $vars ?? [];
$vehicle_id = $data['vehicle_id'] ?? $vars['vehicle_id'] ?? get_the_ID();
$vehicle_rating = $data['vehicle_rating'] ?? $vars['vehicle_rating'] ?? [];
$user_rating = $data['user_rating'] ?? $vars['user_rating'] ?? null;
$is_logged_in = $data['is_logged_in'] ?? $vars['is_logged_in'] ?? is_user_logged_in();

// If vehicle_id still not available, get from global
if (!$vehicle_id || $vehicle_id <= 0) {
    global $post;
    $vehicle_id = $post->ID ?? 0;
}

// CRITICAL: If vehicle ID is not available, render the template
if (!$vehicle_id || $vehicle_id <= 0) {
    return '<div class="rv-rating-form-error">' . esc_html__('Vehicle ID not found', 'mhm-rentiva') . '</div>';
}

// Get comment settings
$require_login = $comments_settings['approval']['require_login'] ?? true;
$allow_guest_comments = $comments_settings['approval']['allow_guest_comments'] ?? false;

// Login check - according to settings
$is_logged_in = is_user_logged_in();
$can_comment = $is_logged_in || (!$require_login && $allow_guest_comments);
$current_user_rating = $user_rating ? floatval($user_rating['rating']) : 0;
$current_user_comment = $user_rating ? $user_rating['comment'] : '';

// Debug: Check user rating information
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo '<!-- Debug: User rating data: ' . esc_html(print_r($user_rating, true)) . ' -->';
}

?>

<div class="rv-rating-form" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>" data-debug-vehicle-id="<?php echo esc_attr($vehicle_id); ?>" data-debug-data="<?php echo esc_attr(json_encode($data)); ?>" data-render-time="<?php echo esc_attr(microtime(true)); ?>">
    
    <!-- Current Rating Display -->
    <div class="rv-rating-display">
        <div class="rv-rating-summary">
            <div class="rv-rating-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php 
                    $is_filled = $i <= floor($vehicle_rating['rating_average'] ?? 0);
                    $is_half = ($i == ceil($vehicle_rating['rating_average'] ?? 0)) && 
                               (($vehicle_rating['rating_average'] ?? 0) - floor($vehicle_rating['rating_average'] ?? 0) >= 0.5);
                    ?>
                    <span class="rv-star <?php echo $is_half ? 'rv-star-half' : ($is_filled ? 'rv-star-filled' : ''); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                                  fill="<?php echo $is_filled || $is_half ? '#fbbf24' : '#d1d5db'; ?>"/>
                        </svg>
                    </span>
                <?php endfor; ?>
            </div>
            <span class="rv-rating-average"><?php echo esc_html($vehicle_rating['rating_average'] ?? '0.0'); ?></span>
            <span class="rv-rating-count">(<?php echo esc_html($vehicle_rating['rating_count'] ?? 0); ?> <?php echo esc_html__('reviews', 'mhm-rentiva'); ?>)</span>
        </div>
    </div>

    <!-- Rating List - Show to everyone (TOP) -->
    <div class="rv-ratings-list" id="ratings-list-<?php echo esc_attr($vehicle_id); ?>">
        <?php
        // Get settings from comments settings
        $comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
        $display_settings = $comments_settings['display'] ?? [];
        
        // Get WordPress comments - only approved comments
        // Clear all cache completely  
        wp_cache_delete('comments_' . $vehicle_id, 'comments');
        wp_cache_delete('comment_count_' . $vehicle_id, 'comments');
        clean_post_cache($vehicle_id);
        
        // Clear object cache
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('comments');
        }
        
        // Clear all cache
        wp_cache_flush();
        
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'status' => ['approve', 'pending'], // Both approved and pending comments
            'number' => \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_comments_per_page(),
            'orderby' => 'comment_date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_comment_meta_cache' => false,
            'cache_results' => false
        ]);
        
        // Debug: Check comment count
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<!-- Debug: Found ' . esc_html(count($comments)) . ' approved comments for vehicle ' . esc_html($vehicle_id) . ' -->';
        }
        
        if (!empty($comments)) :
        ?>
            <div class="rv-reviews-section">
                <h4 class="rv-reviews-title"><?php echo esc_html__('Reviews', 'mhm-rentiva'); ?></h4>
                <div class="rv-reviews-list">
                    <?php foreach ($comments as $comment) : 
                        // Email check for guest users, user_id check for normal users
                        if (is_user_logged_in()) {
                            $is_current_user = $comment->user_id == get_current_user_id();
                        } else {
                            // Email check for guest users
                            $guest_email_cookie = $_COOKIE['guest_email'] ?? '';
                            $is_current_user = !empty($comment->comment_author_email) && 
                                              $comment->comment_author_email === $guest_email_cookie;
                        }
                        $rating = get_comment_meta($comment->comment_ID, 'mhm_rating', true);
                    ?>
                        <div class="rv-review-item" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                            <div class="rv-review-header">
                                <div class="rv-review-author">
                                    <?php if ($display_settings['show_avatars'] ?? true) : ?>
                                        <div class="rv-review-avatar">
                                            <?php echo get_avatar($comment->comment_author_email, 40); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="rv-review-author-info">
                                        <span class="rv-review-author-name"><?php echo esc_html($comment->comment_author); ?></span>
                                        <span class="rv-review-date"><?php echo esc_html(human_time_diff(strtotime($comment->comment_date)) . ' ' . esc_html__('ago', 'mhm-rentiva')); ?></span>
                                        <?php if (($display_settings['show_ratings'] ?? true) && $rating) : ?>
                                            <div class="rv-review-rating">
                                                <?php for ($i = 1; $i <= 5; $i++) : ?>
                                                    <span class="rv-star <?php echo $i <= $rating ? 'active' : ''; ?>">★</span>
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
                                                <span class="dashicons dashicons-edit"></span>
                                                <?php echo esc_html__('Edit', 'mhm-rentiva'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($display_settings['allow_deletion'] ?? true) : ?>
                                            <button class="rv-delete-comment-btn" data-comment-id="<?php echo esc_attr($comment->comment_ID); ?>">
                                                <span class="dashicons dashicons-trash"></span>
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
    <?php if ($can_comment): ?>
        <div class="rv-rating-form-container">
            <h4 class="rv-rating-form-title"><?php echo esc_html__('Rate This Vehicle', 'mhm-rentiva'); ?></h4>
            
            <form class="rv-rating-form-content" id="rating-form-<?php echo esc_attr($vehicle_id); ?>">
                <?php wp_nonce_field('mhm_rentiva_nonce', 'rating_nonce'); ?>
                <input type="hidden" name="vehicle_id" value="<?php echo esc_attr($vehicle_id); ?>">
                
                <?php if (!$is_logged_in && $allow_guest_comments): ?>
                    <!-- Name and email fields for guest users -->
                    <div class="rv-guest-fields">
                        <div class="rv-guest-name">
                            <label for="guest-name-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Name:', 'mhm-rentiva'); ?></label>
                            <input type="text" name="guest_name" id="guest-name-<?php echo esc_attr($vehicle_id); ?>" 
                                   class="rv-rating-input-field" 
                                   placeholder="<?php esc_attr_e('Enter your name', 'mhm-rentiva'); ?>" 
                                   required>
                        </div>
                        <div class="rv-guest-email">
                            <label for="guest-email-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Email:', 'mhm-rentiva'); ?></label>
                            <input type="email" name="guest_email" id="guest-email-<?php echo esc_attr($vehicle_id); ?>" 
                                   class="rv-rating-input-field" 
                                   placeholder="<?php esc_attr_e('Enter your email', 'mhm-rentiva'); ?>" 
                                   required>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Rating Selection -->
                <div class="rv-rating-input">
                    <label class="rv-rating-label"><?php echo esc_html__('Your Rating:', 'mhm-rentiva'); ?></label>
                    <div class="rv-rating-stars-input">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" 
                                   id="rating-<?php echo esc_attr($vehicle_id); ?>-<?php echo $i; ?>"
                                   <?php checked($current_user_rating, $i); ?>>
                            <label for="rating-<?php echo esc_attr($vehicle_id); ?>-<?php echo $i; ?>" 
                                   class="rv-star-input">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                                          fill="#d1d5db"/>
                                </svg>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Comment Area -->
                <div class="rv-rating-comment">
                    <label for="rating-comment-<?php echo esc_attr($vehicle_id); ?>" class="rv-rating-label"><?php echo esc_html__('Your Comment:', 'mhm-rentiva'); ?></label>
                    <textarea name="comment" id="rating-comment-<?php echo esc_attr($vehicle_id); ?>" 
                              class="rv-rating-textarea" 
                              placeholder="<?php esc_attr_e('Share your thoughts about the vehicle...', 'mhm-rentiva'); ?>" 
                              rows="4"><?php echo esc_textarea($current_user_comment); ?></textarea>
                </div>

                <!-- Nonce Field -->
                <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('mhm_rentiva_rating_nonce')); ?>">

                <!-- Submit Button -->
                <div class="rv-rating-submit">
                    <button type="submit" class="rv-btn rv-btn-primary">
                        <?php echo $user_rating ? esc_html__('Update Rating', 'mhm-rentiva') : esc_html__('Submit Rating', 'mhm-rentiva'); ?>
                    </button>
                    <?php if ($user_rating): ?>
                        <button type="button" class="rv-btn rv-btn-danger rv-delete-rating" data-vehicle-id="<?php echo esc_attr($vehicle_id); ?>">
                            <?php echo esc_html__('Delete Rating', 'mhm-rentiva'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="rv-rating-login-notice">
            <div class="rv-login-required">
                <div class="rv-login-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 9V7L15 6.5V7.5C15 8.1 14.6 8.6 14.1 8.9L12 10L9.9 8.9C9.4 8.6 9 8.1 9 7.5V6.5L3 7V9L9 8.5V9.5C9 10.1 9.4 10.6 9.9 10.9L12 12L14.1 10.9C14.6 10.6 15 10.1 15 9.5V8.5L21 9ZM12 13.5C11.2 13.5 10.5 14.2 10.5 15S11.2 16.5 12 16.5 13.5 15.8 13.5 15 12.8 13.5 12 13.5Z" fill="currentColor"/>
                    </svg>
                </div>
                <h4><?php echo esc_html__('Login Required', 'mhm-rentiva'); ?></h4>
                <p><?php echo esc_html__('You must be logged in to submit a rating and review.', 'mhm-rentiva'); ?></p>
                <div class="rv-login-actions">
                    <a href="#" class="rv-btn rv-btn-primary rv-show-login-form">
                        <?php echo esc_html__('Login', 'mhm-rentiva'); ?>
                    </a>
                    <?php if (get_option('users_can_register')): ?>
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
                        <?php echo do_shortcode('[rentiva_login_form]'); ?>
                    </div>
                </div>
                
                <!-- Register Form Modal -->
                <div class="rv-register-modal" style="display: none;">
                    <div class="rv-modal-content">
                        <span class="rv-modal-close">&times;</span>
                        <h3><?php echo esc_html__('Register', 'mhm-rentiva'); ?></h3>
                        <?php echo do_shortcode('[rentiva_register_form]'); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
