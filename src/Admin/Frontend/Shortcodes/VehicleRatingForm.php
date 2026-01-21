<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Vehicle Rating Form Shortcode
 * 
 * Vehicle rating form
 * 
 * Usage: [rentiva_vehicle_rating_form vehicle_id="123"]
 * 
 * @since 4.0.0
 */
final class VehicleRatingForm extends AbstractShortcode
{
    public const SHORTCODE = 'rentiva_vehicle_rating_form';

    public static function register(): void
    {
        parent::register();

        add_action('wp_ajax_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
        // Use the same function for guest users
        add_action('wp_ajax_nopriv_mhm_rentiva_submit_rating', [self::class, 'ajax_submit_rating']);
        add_action('wp_ajax_mhm_rentiva_get_vehicle_rating', [self::class, 'ajax_get_vehicle_rating']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_rating', [self::class, 'ajax_get_vehicle_rating']);
        add_action('wp_ajax_mhm_rentiva_get_vehicle_rating_list', [self::class, 'ajax_get_vehicle_ratings']);
        add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_rating_list', [self::class, 'ajax_get_vehicle_ratings']);

        add_action('wp_ajax_mhm_rentiva_delete_rating', [self::class, 'ajax_delete_rating']);
        add_action('wp_ajax_nopriv_mhm_rentiva_delete_rating', [self::class, 'ajax_delete_rating']);
        add_action('wp_ajax_mhm_rentiva_delete_comment', [self::class, 'ajax_delete_comment']);
        add_action('wp_ajax_nopriv_mhm_rentiva_delete_comment', [self::class, 'ajax_delete_comment']);
    }

    protected static function get_shortcode_tag(): string
    {
        return 'rentiva_vehicle_rating_form';
    }

    protected static function get_template_path(): string
    {
        return 'shortcodes/vehicle-rating-form';
    }

    protected static function get_default_attributes(): array
    {
        return [
            'vehicle_id' => apply_filters('mhm_rentiva/rating_form/vehicle_id', ''),
            'show_rating_display' => apply_filters('mhm_rentiva/rating_form/show_rating_display', '1'),
            'show_form' => apply_filters('mhm_rentiva/rating_form/show_form', '1'),
            'show_ratings_list' => apply_filters('mhm_rentiva/rating_form/show_ratings_list', '1'),
            'class' => apply_filters('mhm_rentiva/rating_form/class', ''),
        ];
    }

    protected static function get_css_filename(): string
    {
        return 'vehicle-rating-form.css';
    }

    protected static function get_js_filename(): string
    {
        return 'vehicle-rating-form.js';
    }

    /**
     * Override enqueue_scripts to use file-based versioning
     * Parent class already handles CSS and JS loading, we just need to ensure proper versioning
     */
    protected static function enqueue_scripts(): void
    {
        $handle = static::get_asset_handle();
        $js_files = static::get_js_files();

        foreach ($js_files as $js_file) {
            if (static::asset_exists($js_file)) {
                // Use file-based versioning for better cache management
                $version = static::get_file_version($js_file);

                wp_enqueue_script(
                    $handle,
                    MHM_RENTIVA_PLUGIN_URL . $js_file,
                    static::get_js_dependencies(),
                    $version,
                    true
                );

                // Localize script with correct object name
                static::localize_script($handle);
                break;
            }
        }
    }

    /**
     * Override enqueue_styles to use file-based versioning
     */
    protected static function enqueue_styles(): void
    {
        $handle = static::get_asset_handle();
        $css_files = static::get_css_files();

        foreach ($css_files as $css_file) {
            if (static::asset_exists($css_file)) {
                // Use file-based versioning for better cache management
                $version = static::get_file_version($css_file);

                wp_enqueue_style(
                    $handle,
                    MHM_RENTIVA_PLUGIN_URL . $css_file,
                    static::get_css_dependencies(),
                    $version
                );
                break;
            }
        }
    }

    /**
     * Get file version based on file modification time
     * Falls back to plugin version if file doesn't exist
     * 
     * @param string $file_path Relative path to file (e.g., 'assets/js/frontend/vehicle-rating-form.js')
     * @return string Version string
     */
    private static function get_file_version(string $file_path): string
    {
        $full_path = MHM_RENTIVA_PLUGIN_PATH . $file_path;
        if (file_exists($full_path)) {
            return (string) filemtime($full_path);
        }
        return MHM_RENTIVA_VERSION; // Fallback to plugin version
    }

    protected static function get_script_object_name(): string
    {
        return 'mhmVehicleRating';
    }

    /**
     * Override get_localized_data to include all required data
     */
    protected static function get_localized_data(): array
    {
        // Get settings from Comments settings
        $comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
        $display_settings = $comments_settings['display'] ?? [];

        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mhm_rentiva_rating_nonce'),
            'is_logged_in' => is_user_logged_in(),
            'current_user_id' => get_current_user_id(),
            'current_user' => wp_get_current_user(),
            'no_ratings' => __('No reviews yet.', 'mhm-rentiva'),
            'reviews_title' => __('Reviews', 'mhm-rentiva'),
            'strings' => static::get_localized_strings(),
            'settings' => [
                'allow_editing' => $display_settings['allow_editing'] ?? true,
                'allow_deletion' => $display_settings['allow_deletion'] ?? true,
                'show_ratings' => $display_settings['show_ratings'] ?? true,
                'show_avatars' => $display_settings['show_avatars'] ?? true
            ],
        ];
    }

    protected static function get_localized_strings(): array
    {
        return [
            'loading' => __('Loading...', 'mhm-rentiva'),
            'error' => __('An error occurred', 'mhm-rentiva'),
            'success' => __('Rating submitted successfully', 'mhm-rentiva'),
            'login_required' => __('You must log in to rate', 'mhm-rentiva'),
            'invalid_rating' => __('Please select a rating', 'mhm-rentiva'),
            'edit_loaded' => __('Your comment has been loaded for editing. Make your changes and click "Update Rating".', 'mhm-rentiva'),
            'delete_confirm' => __('Are you sure you want to delete this comment?', 'mhm-rentiva'),
            'deleting' => __('Deleting...', 'mhm-rentiva'),
            'delete_success' => __('Your comment has been deleted successfully!', 'mhm-rentiva'),
            'delete_error' => __('Error deleting comment: ', 'mhm-rentiva'),
            'delete_error_retry' => __('Error deleting comment. Please try again.', 'mhm-rentiva'),
            'unknown_error' => __('Unknown error', 'mhm-rentiva'),
            'delete' => __('Delete', 'mhm-rentiva'),
        ];
    }

    public static function render(array $atts = [], ?string $content = null): string
    {
        // Assets are automatically loaded via parent::render() -> enqueue_assets_once()

        $defaults = [
            'vehicle_id' => apply_filters('mhm_rentiva/rating_form/vehicle_id', ''),
            'show_rating_display' => apply_filters('mhm_rentiva/rating_form/show_rating_display', '1'),
            'show_form' => apply_filters('mhm_rentiva/rating_form/show_form', '1'),
            'show_ratings_list' => apply_filters('mhm_rentiva/rating_form/show_ratings_list', '1'),
            'class' => apply_filters('mhm_rentiva/rating_form/class', ''),
        ];

        $atts = shortcode_atts($defaults, $atts, self::SHORTCODE);

        // Prepare template data
        $data = self::prepare_template_data($atts);

        // Template render et
        return Templates::render('shortcodes/vehicle-rating-form', $data, true);
    }

    protected static function prepare_template_data(array $atts): array
    {
        $vehicle_id = intval($atts['vehicle_id'] ?? get_the_ID());

        if ($vehicle_id <= 0) {
            return [
                'atts' => $atts,
                'vehicle_id' => 0,
                'vehicle_rating' => [],
                'user_rating' => null,
                'is_logged_in' => false,
                'error' => __('Invalid vehicle ID', 'mhm-rentiva')
            ];
        }

        $vehicle_rating = self::get_vehicle_rating($vehicle_id);
        $user_rating = is_user_logged_in() ? self::get_user_rating($vehicle_id) : null;

        return [
            'atts' => $atts,
            'vehicle_id' => $vehicle_id,
            'vehicle_rating' => $vehicle_rating,
            'user_rating' => $user_rating,
            'is_logged_in' => is_user_logged_in(),
        ];
    }

    /**
     * Gets general rating information for vehicle
     */
    public static function get_vehicle_rating(int $vehicle_id): array
    {
        if ($vehicle_id <= 0) {
            return ['rating_average' => 0, 'rating_count' => 0, 'stars' => '☆☆☆☆☆'];
        }

        // Always calculate from WordPress comments (for cache issues)
        // Cache system removed - always current data
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'status' => 'approve',
            'meta_query' => [
                [
                    'key' => 'mhm_rating',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        // WordPress comments'ten rating'leri al

        $total_rating = 0;
        $count = 0;

        foreach ($comments as $comment) {
            $rating = intval(get_comment_meta($comment->comment_ID, 'mhm_rating', true));
            if ($rating > 0) {
                $total_rating += $rating;
                $count++;
            }
        }

        $average = $count > 0 ? round($total_rating / $count, 1) : 0;

        return [
            'rating_average' => $average,
            'rating_count' => $count,
            'stars' => self::get_star_rating($average)
        ];
    }

    /**
     * Gets star rating
     */
    private static function get_star_rating(float $rating): string
    {
        $stars = '';
        $full_stars = (int) floor($rating);
        $has_half = ($rating - $full_stars) >= 0.5;
        $empty_stars = (int) (5 - $full_stars - ($has_half ? 1 : 0));

        $stars .= str_repeat('★', $full_stars);
        if ($has_half) {
            $stars .= '☆';
        }
        $stars .= str_repeat('☆', $empty_stars);

        return $stars;
    }

    /**
     * Gets user rating for vehicle
     */
    public static function get_user_rating(int $vehicle_id): ?array
    {
        if (!is_user_logged_in() || $vehicle_id <= 0) {
            return null;
        }

        $user_id = get_current_user_id();

        // Get user comment from WordPress comments
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'user_id' => $user_id,
            'status' => ['approve', 'pending'], // Both approved and pending
            'number' => 1,
            'meta_query' => [
                [
                    'key' => 'mhm_rating',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        if (empty($comments)) {
            return null;
        }

        $comment = $comments[0];
        $rating = intval(get_comment_meta($comment->comment_ID, 'mhm_rating', true));

        return [
            'rating' => $rating,
            'comment' => $comment->comment_content,
            'comment_id' => $comment->comment_ID
        ];
    }

    /**
     * AJAX: Submit rating
     */
    public static function ajax_submit_rating(): void
    {
        try {
            // Debug: POST verilerini logla
            error_log('AJAX Submit Rating - POST Data: ' . print_r($_POST, true));

            // Nonce check
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
                error_log('AJAX Submit Rating - Nonce verification failed');
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            // User login check - check from settings
            $comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
            $require_login = $comments_settings['approval']['require_login'] ?? true;
            $allow_guest_comments = $comments_settings['approval']['allow_guest_comments'] ?? false;

            error_log('AJAX Submit Rating - Settings: require_login=' . ($require_login ? 'true' : 'false') . ', allow_guest_comments=' . ($allow_guest_comments ? 'true' : 'false') . ', is_logged_in=' . (is_user_logged_in() ? 'true' : 'false'));

            if ($require_login && !is_user_logged_in()) {
                wp_send_json_error([
                    'message' => __('You must be logged in to submit a rating.', 'mhm-rentiva'),
                    'login_required' => true,
                    'login_url' => wp_login_url(get_permalink() ?: ''),
                    'debug_info' => [
                        'is_user_logged_in' => is_user_logged_in(),
                        'current_user_id' => get_current_user_id(),
                        'wp_get_current_user' => wp_get_current_user()->ID ?? 'null',
                        'ajax_context' => 'AJAX request context'
                    ]
                ]);
            }

            // User permission check - check from settings
            $user_id = get_current_user_id();

            error_log('AJAX Submit Rating - User ID: ' . $user_id . ', Guest comments allowed: ' . ($allow_guest_comments ? 'true' : 'false'));

            // If require_login is false and allow_guest_comments is true, allow guest users
            if (!$require_login && $allow_guest_comments) {
                // Guest users can have user_id = 0 (guest users)
                if ($user_id < 0) {
                    wp_send_json_error(['message' => __('Invalid user session.', 'mhm-rentiva')]);
                }
                // Set user_id = 0 for guest users
                if ($user_id == 0) {
                    $user_id = 0; // Guest user
                }
            } else {
                // Normal user check
                if (!$user_id || $user_id <= 0) {
                    wp_send_json_error(['message' => __('Invalid user session.', 'mhm-rentiva')]);
                }
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $rating = intval($_POST['rating'] ?? 0);
            $comment = wp_kses_post($_POST['comment'] ?? ''); // Daha uygun sanitization

            // Name and email fields for guest users
            $guest_name = '';
            $guest_email = '';
            if (!is_user_logged_in() && $allow_guest_comments) {
                $guest_name = sanitize_text_field(wp_unslash($_POST['guest_name'] ?? ''));
                $guest_email = sanitize_email((string) (($_POST['guest_email'] ?? '') ?: ''));

                error_log('AJAX Submit Rating - Guest data: name=' . $guest_name . ', email=' . $guest_email);

                if (empty($guest_name) || empty($guest_email)) {
                    error_log('AJAX Submit Rating - Guest name or email is empty');
                    wp_send_json_error(['message' => __('Name and email are required for guest comments.', 'mhm-rentiva')]);
                }

                if (!is_email($guest_email)) {
                    error_log('AJAX Submit Rating - Invalid email format: ' . $guest_email);
                    wp_send_json_error(['message' => __('Please enter a valid email address.', 'mhm-rentiva')]);
                }

                // Set email cookie for guest users
                setcookie('guest_email', $guest_email, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                error_log('AJAX Submit Rating - Guest email cookie set: ' . $guest_email);
            }

            // Debug: Check comment data

            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            if ($rating < 1 || $rating > 5) {
                wp_send_json_error(['message' => __('Please select a valid rating.', 'mhm-rentiva')]);
            }

            // SPAM KORUMASI
            if (!self::check_spam_protection($user_id, $vehicle_id, $comment)) {
                wp_send_json_error(['message' => __('Spam protection triggered. Please try again later.', 'mhm-rentiva')]);
            }

            // RATE LIMITING - Is user submitting too quickly?
            if (!self::check_rate_limiting($user_id)) {
                wp_send_json_error(['message' => __('Too many requests. Please wait before submitting another rating.', 'mhm-rentiva')]);
            }

            $user_id = get_current_user_id();

            // Mevcut rating'i kontrol et
            $existing_rating = self::get_user_rating($vehicle_id);

            if ($existing_rating) {
                // Update WordPress comment
                $comment_id = $existing_rating['comment_id'];

                if ($comment_id) {
                    // Update existing WordPress comment
                    wp_update_comment([
                        'comment_ID' => $comment_id,
                        'comment_content' => $comment
                    ]);

                    // Update rating meta
                    update_comment_meta($comment_id, 'mhm_rating', $rating);
                } else {
                    // Create new WordPress comment
                    $comment_id = self::create_wordpress_comment($vehicle_id, $rating, $comment, $user_id, $guest_name, $guest_email);
                }

                $message = __('Your rating has been updated successfully!', 'mhm-rentiva');
            } else {
                // Create WordPress comment only
                $comment_id = self::create_wordpress_comment($vehicle_id, $rating, $comment, $user_id, $guest_name, $guest_email);

                if ($comment_id) {
                    $message = __('Your rating has been submitted successfully!', 'mhm-rentiva');
                } else {
                    wp_send_json_error(['message' => __('Failed to create rating.', 'mhm-rentiva')]);
                }
            }

            // Update vehicle meta
            self::update_vehicle_rating_meta($vehicle_id);

            // Get updated rating information
            $vehicle_rating = self::get_vehicle_rating($vehicle_id);
            $user_rating = self::get_user_rating($vehicle_id);

            // Debug log

            wp_send_json_success([
                'message' => $message,
                'vehicle_rating' => $vehicle_rating,
                'user_rating' => $user_rating
            ]);
        } catch (\Exception $e) {

            wp_send_json_error(['message' => __('An error occurred while submitting rating.', 'mhm-rentiva')]);
        }
    }

    /**
     * Updates vehicle rating meta
     */
    public static function update_vehicle_rating_meta(int $vehicle_id): void
    {
        if ($vehicle_id <= 0) {
            return;
        }

        // WordPress comments'ten rating'leri al
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'status' => 'approve',
            'number' => 0, // Get all comments
            'meta_query' => [
                [
                    'key' => 'mhm_rating',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);

        $ratings = [];
        $total_rating = 0;
        $count = 0;

        foreach ($comments as $comment) {
            $rating = intval(get_comment_meta($comment->comment_ID, 'mhm_rating', true));
            if ($rating > 0) {
                $ratings[] = $rating;
                $total_rating += $rating;
                $count++;
            }
        }

        $average = $count > 0 ? round($total_rating / $count, 1) : 0;

        // Update vehicle meta
        update_post_meta($vehicle_id, '_mhm_rentiva_rating_average', $average);
        update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $count);
    }

    /**
     * Spam protection check
     */
    private static function check_spam_protection(int $user_id, int $vehicle_id, string $comment): bool
    {
        // Debug log

        // If existing rating exists (update situation), bypass spam checks
        $existing_rating = self::get_user_rating($vehicle_id);
        if ($existing_rating) {
            return true; // Don't do spam check in update situation
        }

        // 1. Is same user submitting rating for same vehicle too quickly?
        $recent_ratings = get_posts([
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_mhm_rentiva_vehicle_id',
                    'value' => $vehicle_id,
                    'compare' => '='
                ],
                [
                    'key' => '_mhm_rentiva_customer_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'date_query' => [
                'after' => '1 minute ago'
            ]
        ]);


        if (!empty($recent_ratings)) {
            return false; // Already submitted rating within 5 minutes
        }

        // Get spam protection from settings
        $spam_protection = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::check_spam_protection($user_id, $vehicle_id, $comment);
        if (!$spam_protection) {
            return false; // Spam protection triggered
        }

        return true; // Not spam
    }

    /**
     * Rate limiting check
     */
    private static function check_rate_limiting(int $user_id): bool
    {
        // How many ratings submitted in last 1 hour?
        $recent_ratings = get_posts([
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mhm_rentiva_customer_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'date_query' => [
                'after' => '1 hour ago'
            ]
        ]);

        // 1 saatte maksimum 5 rating
        if (count($recent_ratings) >= 5) {
            return false;
        }

        // How many ratings submitted in last 24 hours?
        $daily_ratings = get_posts([
            'post_type' => 'vehicle_booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mhm_rentiva_customer_id',
                    'value' => $user_id,
                    'compare' => '='
                ]
            ],
            'date_query' => [
                'after' => '24 hours ago'
            ]
        ]);

        // 24 saatte maksimum 20 rating
        if (count($daily_ratings) >= 20) {
            return false;
        }

        return true;
    }

    /**
     * AJAX: Get vehicle rating information
     */
    public static function ajax_get_vehicle_rating(): void
    {
        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            $vehicle_rating = self::get_vehicle_rating($vehicle_id);
            $user_rating = is_user_logged_in() ? self::get_user_rating($vehicle_id) : null;

            wp_send_json_success([
                'vehicle_rating' => $vehicle_rating,
                'user_rating' => $user_rating
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while retrieving rating.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX: Get vehicle rating list
     */
    public static function ajax_get_vehicle_ratings(): void
    {
        try {
            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);

            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            // Rating'leri al - daha esnek query
            $args = [
                'post_type' => 'vehicle_booking',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_mhm_rentiva_vehicle_id',
                        'value' => $vehicle_id,
                        'compare' => '='
                    ],
                    [
                        'key' => '_mhm_rentiva_customer_rating',
                        'compare' => 'EXISTS'
                    ]
                    // _mhm_rentiva_review_approved check removed - more flexible
                ],
                'orderby' => 'date',
                'order' => 'DESC'
            ];

            $bookings = get_posts($args);
            $ratings = [];


            // Get ratings from WordPress Comments system too (temporarily disabled)
            // $wp_comments = self::get_wordpress_rating_comments($vehicle_id);

            foreach ($bookings as $booking) {
                $rating = intval(get_post_meta($booking->ID, '_mhm_rentiva_customer_rating', true));
                $comment = get_post_meta($booking->ID, '_mhm_rentiva_customer_review', true);
                $customer_name = get_post_meta($booking->ID, '_mhm_rentiva_customer_name', true);
                $review_approved = get_post_meta($booking->ID, '_mhm_rentiva_review_approved', true);
                $vehicle_id_meta = get_post_meta($booking->ID, '_mhm_rentiva_vehicle_id', true);


                if ($rating > 0) {
                    $ratings[] = [
                        'id' => $booking->ID,
                        'rating' => $rating,
                        'comment' => $comment,
                        'customer_name' => $customer_name ?: 'Anonim',
                        'display_name' => $customer_name ?: 'Anonymous', // For frontend
                        'date' => $booking->post_date,
                        'created_at' => $booking->post_date
                    ];
                }
            }

            // Also add ratings from WordPress Comments (temporarily disabled)
            /*
            foreach ($wp_comments as $wp_comment) {
                // Duplicate check - don't add if same user and date exists
                $is_duplicate = false;
                foreach ($ratings as $existing_rating) {
                    if ($existing_rating['customer_name'] === $wp_comment['customer_name'] && 
                        $existing_rating['date'] === $wp_comment['date']) {
                        $is_duplicate = true;
                        break;
                    }
                }
                
                if (!$is_duplicate) {
                    $ratings[] = $wp_comment;
                }
            }
            */


            $response_data = [
                'ratings' => $ratings,
                'count' => count($ratings)
            ];


            wp_send_json_success($response_data);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while retrieving ratings.', 'mhm-rentiva')]);
        }
    }

    /**
     * Creates rating comment with WordPress Comments system
     */
    private static function create_wordpress_comment(int $vehicle_id, int $rating, string $comment, int $user_id, string $guest_name = '', string $guest_email = ''): int
    {
        // Name and email fields for guest users
        if ($user_id == 0 && !empty($guest_name) && !empty($guest_email)) {
            $comment_author = $guest_name;
            $comment_author_email = $guest_email;
            $comment_user_id = 0; // Guest user
        } else {
            $user = get_user_by('id', $user_id);
            if (!$user) {
                return 0;
            }
            $comment_author = $user->display_name;
            $comment_author_email = $user->user_email;
            $comment_user_id = $user_id;
        }

        $comment_data = [
            'comment_post_ID' => $vehicle_id,
            'comment_author' => $comment_author,
            'comment_author_email' => $comment_author_email,
            'comment_author_url' => '',
            'comment_content' => $comment, // Comment text
            'comment_type' => 'comment', // Save as normal comment
            'comment_parent' => 0,
            'user_id' => $comment_user_id,
            'comment_author_IP' => $_SERVER['REMOTE_ADDR'] ?? '',
            'comment_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'comment_date' => current_time('mysql'),
            'comment_date_gmt' => current_time('mysql', 1),
            'comment_approved' => \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_comment_approval_status() // Get from settings
        ];

        // Debug: Check comment data

        // Duplicate comment check and cleanup - more robust
        $existing_comments = get_comments([
            'post_id' => $vehicle_id,
            'user_id' => $comment_user_id, // 0 for guest users, user_id for normal users
            'comment_content' => $comment,
            'number' => 10,
            'date_query' => [
                'after' => '1 minute ago' // Check comments within last 1 minute
            ]
        ]);

        if (!empty($existing_comments)) {
            // If same comment exists within last 1 minute, don't create new
            $latest_comment = $existing_comments[0];
            $comment_age = time() - strtotime($latest_comment->comment_date);

            if ($comment_age < 60) { // Within 60 seconds
                return $latest_comment->comment_ID; // Return existing comment ID
            }

            // Clean old duplicates
            for ($i = 1; $i < count($existing_comments); $i++) {
                wp_delete_comment($existing_comments[$i]->comment_ID, true);
            }
        }

        $comment_id = wp_insert_comment($comment_data);

        // Debug: Comment creation result


        if ($comment_id) {



            // Check if comment was really created
            $created_comment = get_comment($comment_id);
            if ($created_comment) {







                // Check if comment is visible in admin
                $admin_comments = get_comments([
                    'post_id' => $created_comment->comment_post_ID,
                    'status' => 'all',
                    'number' => 10
                ]);

                foreach ($admin_comments as $admin_comment) {
                }
            } else {
            }
        } else {

            global $wpdb;



            // Try alternative method - direct DB insert

            $insert_result = $wpdb->insert(
                $wpdb->comments,
                $comment_data,
                ['%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            if ($insert_result) {
                $comment_id = $wpdb->insert_id;
            }
        }

        if ($comment_id) {
            // Add rating meta to comment
            add_comment_meta($comment_id, 'mhm_rating', $rating);
            add_comment_meta($comment_id, 'mhm_vehicle_id', $vehicle_id);
            add_comment_meta($comment_id, 'mhm_comment_type', 'vehicle_rating');

            // Debug: Comment meta added




        }

        return $comment_id;
    }

    /**
     * Updates comment_type of existing comments
     */
    private static function update_existing_comments_type(): void
    {
        global $wpdb;

        // Find and update comments with empty comment_type
        $updated = $wpdb->update(
            $wpdb->comments,
            ['comment_type' => 'comment'],
            ['comment_type' => ''],
            ['%s'],
            ['%s']
        );

        if ($updated > 0) {
        }
    }

    /**
     * Updates rating comment with WordPress Comments system
     */
    private static function update_wordpress_comment(int $comment_id, int $rating, string $comment): bool
    {
        // Debug: Update comment information






        if (!$comment_id) {

            return false;
        }

        $comment_data = [
            'comment_ID' => $comment_id,
            'comment_content' => $comment // Comment text
        ];



        // Check existing comment
        $existing_comment = get_comment($comment_id);


        // Check if comment is visible in admin
        if ($existing_comment) {








            // Admin comments query
            $admin_comments = get_comments([
                'post_id' => $existing_comment->comment_post_ID,
                'status' => 'all',
                'number' => 10
            ]);

            foreach ($admin_comments as $admin_comment) {
            }
        } else {



            // Search for comment ID 2 using different methods
            global $wpdb;
            $comment_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_ID = %d",
                $comment_id
            ));


            // List all comments
            $all_comments = $wpdb->get_results("SELECT comment_ID, comment_post_ID, comment_type, comment_approved FROM {$wpdb->comments} ORDER BY comment_ID DESC LIMIT 10");

            foreach ($all_comments as $comment) {
            }
        }

        // Use direct DB update instead of wp_update_comment
        global $wpdb;
        $update_result = $wpdb->update(
            $wpdb->comments,
            ['comment_content' => $comment],
            ['comment_ID' => $comment_id],
            ['%s'],
            ['%d']
        );



        if ($update_result !== false) {
            // Update rating meta
            update_comment_meta($comment_id, 'mhm_rating', $rating);

            $result = true;
        } else {


            $result = false;
        }

        // If update result is 0 (same content), consider it successful
        if ($update_result === 0) {

            $result = true;
        }

        return $result !== false;
    }

    /**
     * Gets rating comments from WordPress Comments
     */
    public static function get_wordpress_rating_comments(int $vehicle_id): array
    {
        $comments = get_comments([
            'post_id' => $vehicle_id,
            'meta_query' => [
                [
                    'key' => 'mhm_comment_type',
                    'value' => 'vehicle_rating',
                    'compare' => '='
                ]
            ],
            'status' => 'approve',
            'orderby' => 'comment_date',
            'order' => 'DESC'
        ]);

        // Alternative: Get all comments and check meta
        if (empty($comments)) {
            $all_comments = get_comments([
                'post_id' => $vehicle_id,
                'status' => 'approve',
                'orderby' => 'comment_date',
                'order' => 'DESC'
            ]);

            // Use all comments
            $comments = $all_comments;
        }

        $ratings = [];
        foreach ($comments as $comment) {
            $rating = get_comment_meta($comment->comment_ID, 'mhm_rating', true);
            if ($rating) {
                $ratings[] = [
                    'id' => $comment->comment_ID,
                    'rating' => intval($rating),
                    'comment' => $comment->comment_content,
                    'customer_name' => $comment->comment_author,
                    'date' => $comment->comment_date,
                    'created_at' => $comment->comment_date,
                    'display_name' => $comment->comment_author
                ];
            } else {
                // If no meta, check comment type
                if ($comment->comment_type === 'mhm_vehicle_rating') {
                    // Add default rating as 5
                    $ratings[] = [
                        'id' => $comment->comment_ID,
                        'rating' => 5,
                        'comment' => $comment->comment_content,
                        'customer_name' => $comment->comment_author,
                        'date' => $comment->comment_date,
                        'created_at' => $comment->comment_date,
                        'display_name' => $comment->comment_author
                    ];
                }
            }
        }

        return $ratings;
    }

    /**
     * AJAX: Delete user rating
     */
    public static function ajax_delete_rating(): void
    {
        try {
            // Nonce check
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_nonce')) {
                wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
            }

            // Login check - check from settings
            $comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
            $require_login = $comments_settings['approval']['require_login'] ?? true;

            if ($require_login && !is_user_logged_in()) {
                wp_send_json_error(['message' => __('You must be logged in to delete rating.', 'mhm-rentiva')]);
            }

            $vehicle_id = intval($_POST['vehicle_id'] ?? 0);
            $user_id = get_current_user_id();

            if ($vehicle_id <= 0) {
                wp_send_json_error(['message' => __('Invalid vehicle ID.', 'mhm-rentiva')]);
            }

            // Find existing rating
            $existing_rating = self::get_user_rating($vehicle_id);

            if (!$existing_rating) {
                wp_send_json_error(['message' => __('No rating found to delete.', 'mhm-rentiva')]);
            }

            $comment_id = $existing_rating['comment_id'];

            // Delete WordPress Comment
            if ($comment_id) {
                wp_delete_comment($comment_id, true); // Hard delete
            }

            // Update vehicle meta
            self::update_vehicle_rating_meta($vehicle_id);

            wp_send_json_success([
                'message' => __('Rating deleted successfully.', 'mhm-rentiva'),
                'vehicle_rating' => self::get_vehicle_rating($vehicle_id)
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => __('An error occurred while deleting rating.', 'mhm-rentiva')]);
        }
    }

    /**
     * AJAX handler for deleting a comment
     */
    public static function ajax_delete_comment(): void
    {
        // Nonce check
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'] ?? '')), 'mhm_rentiva_rating_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'mhm-rentiva')]);
        }

        // User login check - check from settings
        $comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
        $require_login = $comments_settings['approval']['require_login'] ?? true;

        if ($require_login && !is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to delete comments.', 'mhm-rentiva')]);
        }

        $comment_id = intval($_POST['comment_id'] ?? 0);
        if ($comment_id <= 0) {
            wp_send_json_error(['message' => __('Invalid comment ID.', 'mhm-rentiva')]);
        }

        // Get comment and check ownership
        $comment = get_comment($comment_id);
        if (!$comment) {
            wp_send_json_error(['message' => __('Comment not found.', 'mhm-rentiva')]);
        }

        // Only comment owner can delete
        if ($comment->user_id != get_current_user_id()) {
            wp_send_json_error(['message' => __('You can only delete your own comments.', 'mhm-rentiva')]);
        }

        // Delete comment
        $deleted = wp_delete_comment($comment_id, true);
        if (!$deleted) {
            wp_send_json_error(['message' => __('Failed to delete comment.', 'mhm-rentiva')]);
        }

        // Update vehicle meta
        $vehicle_id = $comment->comment_post_ID;
        self::update_vehicle_rating_meta($vehicle_id);

        wp_send_json_success(['message' => __('Comment deleted successfully.', 'mhm-rentiva')]);
    }
}
