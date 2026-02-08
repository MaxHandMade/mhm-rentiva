<?php

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Vehicle Rating Form Shortcode
 */
final class VehicleRatingForm extends AbstractShortcode
{

	public const SHORTCODE = 'rentiva_vehicle_rating_form';

	public static function register(): void
	{
		parent::register();
		add_action('wp_ajax_mhm_rentiva_submit_rating', array(self::class, 'ajax_submit_rating'));
		add_action('wp_ajax_nopriv_mhm_rentiva_submit_rating', array(self::class, 'ajax_submit_rating'));
		add_action('wp_ajax_mhm_rentiva_get_vehicle_rating_list', array(self::class, 'ajax_get_vehicle_rating_list'));
		add_action('wp_ajax_nopriv_mhm_rentiva_get_vehicle_rating_list', array(self::class, 'ajax_get_vehicle_rating_list'));
		add_action('wp_ajax_mhm_rentiva_delete_rating', array(self::class, 'ajax_delete_rating'));
		add_action('wp_ajax_nopriv_mhm_rentiva_delete_rating', array(self::class, 'ajax_delete_rating'));
	}

	protected static function get_shortcode_tag(): string
	{
		return self::SHORTCODE;
	}

	protected static function get_default_attributes(): array
	{
		return array(
			'vehicle_id'          => '',
			'show_rating_display' => '1',
			'show_form'           => '1',
			'show_ratings_list'   => '1',
			'class'               => '',
		);
	}

	protected static function get_template_path(): string
	{
		return 'shortcodes/vehicle-rating-form';
	}

	protected static function get_css_filename(): string
	{
		return 'vehicle-rating-form.css';
	}

	protected static function get_js_filename(): string
	{
		return 'vehicle-rating-form.js';
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmVehicleRating';
	}

	protected static function get_localized_data(): array
	{
		$data = parent::get_localized_data();
		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$display_settings  = $comments_settings['display'] ?? array();

		return array_merge($data, array(
			'nonce'           => wp_create_nonce('mhm_rentiva_rating_nonce'),
			'is_logged_in'    => is_user_logged_in(),
			'settings'        => array(
				'allow_editing'  => $display_settings['allow_editing'] ?? true,
				'allow_deletion' => $display_settings['allow_deletion'] ?? true,
			),
		));
	}

	protected static function get_localized_strings(): array
	{
		return array(
			'loading'        => __('Loading...', 'mhm-rentiva'),
			'error'          => __('An error occurred', 'mhm-rentiva'),
			'success'        => __('Rating submitted successfully', 'mhm-rentiva'),
			'delete_confirm' => __('Are you sure?', 'mhm-rentiva'),
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		$vehicle_id = intval($atts['vehicle_id'] ?? get_the_ID());
		if ($vehicle_id <= 0) return array('vehicle_id' => 0);

		return array(
			'vehicle_id'     => $vehicle_id,
			'vehicle_rating' => self::get_vehicle_rating($vehicle_id),
			'user_rating'    => is_user_logged_in() ? self::get_user_rating($vehicle_id) : null,
		);
	}

	public static function get_vehicle_rating(int $vehicle_id): array
	{
		global $wpdb;
		$stats = $wpdb->get_row($wpdb->prepare(
			"SELECT COUNT(*) as count, AVG(CAST(meta_value AS DECIMAL(10,1))) as average FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id WHERE c.comment_post_ID = %d AND c.comment_approved = '1' AND cm.meta_key = 'mhm_rating'",
			$vehicle_id
		));
		$avg = round((float)($stats->average ?? 0), 1);
		return array(
			'rating_average' => $avg,
			'rating_count'   => (int)($stats->count ?? 0),
		);
	}

	public static function get_user_rating(int $vehicle_id): ?array
	{
		if (! is_user_logged_in()) return null;
		$comments = get_comments(array('post_id' => $vehicle_id, 'user_id' => get_current_user_id(), 'number' => 1));
		if (empty($comments)) return null;
		$c = $comments[0];
		return array('rating' => get_comment_meta($c->comment_ID, 'mhm_rating', true), 'comment' => $c->comment_content, 'comment_id' => $c->comment_ID);
	}

	public static function ajax_submit_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'mhm-rentiva')));
		}

		$vid = (int) (isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);
		$rating = (int) (isset($_POST['rating']) ? wp_unslash($_POST['rating']) : 0);
		$comment = wp_kses_post(isset($_POST['comment']) ? wp_unslash($_POST['comment']) : '');
		$uid = get_current_user_id();

		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$require_login = $comments_settings['approval']['require_login'] ?? true;
		$allow_guest = $comments_settings['approval']['allow_guest_comments'] ?? false;

		if (! $uid && (! $allow_guest || $require_login)) {
			wp_send_json_error(array('message' => __('Login required to post a review', 'mhm-rentiva')));
		}

		// Validate character limits
		$limits = $comments_settings['limits'] ?? array();
		$min_length = (int) ($limits['comment_length_min'] ?? 5);
		$max_length = (int) ($limits['comment_length_max'] ?? 1000);
		$comment_length = mb_strlen(trim($comment));

		if ($min_length > 0 && $comment_length < $min_length) {
			wp_send_json_error(array(
				'message' => sprintf(
					/* translators: %d: minimum character count */
					__('Comment is too short. Please write at least %d characters.', 'mhm-rentiva'),
					$min_length
				),
			));
		}

		if ($max_length > 0 && $comment_length > $max_length) {
			wp_send_json_error(array(
				'message' => sprintf(
					/* translators: %d: maximum character count */
					__('Comment is too long. Maximum %d characters allowed.', 'mhm-rentiva'),
					$max_length
				),
			));
		}

		// Run comprehensive spam protection checks
		if (! \MHMRentiva\Admin\Settings\Comments\CommentsSettings::check_spam_protection($uid, $vid, $comment)) {
			wp_send_json_error(array('message' => __('Your comment could not be submitted. Please wait a moment before trying again.', 'mhm-rentiva')));
		}

		$comment_data = array(
			'comment_post_ID' => $vid,
			'comment_content' => $comment,
			'comment_type'    => 'review',
			'comment_approved' => $comments_settings['approval']['auto_approve'] ?? 0 ? 1 : 0,
		);

		if ($uid) {
			$comment_data['user_id'] = $uid;
			$existing = self::get_user_rating($vid);
			if ($existing) {
				$comment_data['comment_ID'] = $existing['comment_id'];
				wp_update_comment($comment_data);
				update_comment_meta($existing['comment_id'], 'mhm_rating', $rating);

				// Update vehicle meta
				self::update_vehicle_rating_meta($vid);

				wp_send_json_success(array('message' => __('Rating updated successfully', 'mhm-rentiva')));
			}
		} else {
			$comment_data['comment_author'] = sanitize_text_field(isset($_POST['guest_name']) ? wp_unslash($_POST['guest_name']) : '');
			$comment_data['comment_author_email'] = sanitize_email(isset($_POST['guest_email']) ? wp_unslash($_POST['guest_email']) : '');

			if (empty($comment_data['comment_author']) || empty($comment_data['comment_author_email'])) {
				wp_send_json_error(array('message' => __('Name and email are required for guests', 'mhm-rentiva')));
			}
		}

		$cid = wp_insert_comment($comment_data);
		if ($cid) {
			update_comment_meta($cid, 'mhm_rating', $rating);

			// Update vehicle meta
			self::update_vehicle_rating_meta($vid);

			$message = $comment_data['comment_approved'] ? __('Rating submitted successfully', 'mhm-rentiva') : __('Rating submitted and awaiting approval', 'mhm-rentiva');
			wp_send_json_success(array('message' => $message));
		}

		wp_send_json_error(array('message' => __('Failed to submit rating', 'mhm-rentiva')));
	}

	public static function ajax_delete_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? wp_unslash($_POST['nonce']) : '';
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'mhm-rentiva')));
		}

		$uid = get_current_user_id();
		if (! $uid) {
			wp_send_json_error(array('message' => __('Login required', 'mhm-rentiva')));
		}

		// Support both comment_id and vehicle_id
		$cid = isset($_POST['comment_id']) ? (int) wp_unslash($_POST['comment_id']) : 0;
		$vid = isset($_POST['vehicle_id']) ? (int) wp_unslash($_POST['vehicle_id']) : 0;

		// If vehicle_id provided, find user's comment on that vehicle
		if (! $cid && $vid) {
			$user_comments = get_comments(array(
				'post_id' => $vid,
				'user_id' => $uid,
				'number'  => 1,
			));
			if (! empty($user_comments)) {
				$cid = $user_comments[0]->comment_ID;
			}
		}

		if (! $cid) {
			wp_send_json_error(array('message' => __('Comment not found', 'mhm-rentiva')));
		}

		$c = get_comment($cid);
		if (! $c) {
			wp_send_json_error(array('message' => __('Comment not found', 'mhm-rentiva')));
		}

		// Check permission: owner or admin
		if ($c->user_id == $uid || current_user_can('manage_options')) {
			$vid = (int) $c->comment_post_ID;
			wp_delete_comment($cid, true);

			// Update vehicle meta
			self::update_vehicle_rating_meta($vid);

			wp_send_json_success(array('message' => __('Rating deleted successfully', 'mhm-rentiva')));
		}

		wp_send_json_error(array('message' => __('Permission denied', 'mhm-rentiva')));
	}

	public static function ajax_get_vehicle_rating_list(): void
	{
		$vid = (int) (isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);
		if (! $vid) {
			wp_send_json_error(array('message' => __('Invalid vehicle', 'mhm-rentiva')));
		}

		$comments = get_comments(
			array(
				'post_id' => $vid,
				'type'    => 'review',
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);

		$ratings = array();
		foreach ($comments as $c) {
			$ratings[] = array(
				'display_name' => $c->comment_author,
				'rating'       => (int) get_comment_meta($c->comment_ID, 'mhm_rating', true),
				'comment'      => $c->comment_content,
				'date'         => $c->comment_date,
			);
		}

		wp_send_json_success(array('ratings' => $ratings));
	}

	/**
	 * Update vehicle rating meta information
	 */
	public static function update_vehicle_rating_meta(int $vehicle_id): void
	{
		$comments = get_comments(
			array(
				'post_id' => $vehicle_id,
				'type'    => 'review',
				'status'  => 'approve',
			)
		);

		$total_rating = 0;
		$count        = count($comments);

		if ($count > 0) {
			foreach ($comments as $comment) {
				$rating        = get_comment_meta($comment->comment_ID, 'mhm_rating', true);
				$total_rating += (float) $rating;
			}
			$average = $total_rating / $count;
		} else {
			$average = 0;
		}

		update_post_meta($vehicle_id, '_mhm_rentiva_average_rating', round($average, 1));
		update_post_meta($vehicle_id, '_mhm_rentiva_rating_count', $count);
	}
}
