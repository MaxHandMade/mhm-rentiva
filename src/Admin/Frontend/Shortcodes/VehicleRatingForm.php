<?php
declare(strict_types=1);

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
		$data              = parent::get_localized_data();
		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$display_settings  = $comments_settings['display'] ?? array();

		return array_merge(
			$data,
			array(
				'nonce'        => wp_create_nonce('mhm_rentiva_rating_nonce'),
				'is_logged_in' => is_user_logged_in(),
				'settings'     => array(
					'allow_editing'  => $display_settings['allow_editing'] ?? true,
					'allow_deletion' => $display_settings['allow_deletion'] ?? true,
				),
				'icons'        => array(
					'star'    => \MHMRentiva\Helpers\Icons::get('star', array('class' => 'rv-icon-star')),
					'trash'   => \MHMRentiva\Helpers\Icons::get('trash', array('class' => 'rv-icon-trash')),
					'success' => \MHMRentiva\Helpers\Icons::get('success'),
					'warning' => \MHMRentiva\Helpers\Icons::get('warning'),
					'info'    => \MHMRentiva\Helpers\Icons::get('info'),
					'error'   => \MHMRentiva\Helpers\Icons::get('error'),
				),
			)
		);
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
		if ($vehicle_id <= 0) {
			return array('vehicle_id' => 0);
		}

		return array(
			'vehicle_id'     => $vehicle_id,
			'vehicle_rating' => self::get_vehicle_rating($vehicle_id),
			'user_rating'    => is_user_logged_in() ? self::get_user_rating($vehicle_id) : null,
		);
	}

	public static function get_vehicle_rating(int $vehicle_id): array
	{
		return \MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::get_rating($vehicle_id);
	}

	public static function get_user_rating(int $vehicle_id): ?array
	{
		if (! is_user_logged_in()) {
			return null;
		}
		$comments = get_comments(
			array(
				'post_id' => $vehicle_id,
				'user_id' => get_current_user_id(),
				'number'  => 1,
			)
		);
		if (empty($comments)) {
			return null;
		}
		$c = $comments[0];
		return array(
			'rating'     => get_comment_meta($c->comment_ID, 'mhm_rating', true),
			'comment'    => $c->comment_content,
			'comment_id' => $c->comment_ID,
		);
	}

	public static function ajax_submit_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'mhm-rentiva')));
		}

		$vid     = absint(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);
		$rating  = absint(isset($_POST['rating']) ? wp_unslash($_POST['rating']) : 0);
		$comment = wp_kses_post(isset($_POST['comment']) ? wp_unslash($_POST['comment']) : '');
		$uid     = get_current_user_id();

		if ($vid <= 0 || $rating < 1 || $rating > 5) {
			wp_send_json_error(array('message' => __('Invalid vehicle or rating value', 'mhm-rentiva')));
		}

		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$require_login     = $comments_settings['approval']['require_login'] ?? true;
		$allow_guest       = $comments_settings['approval']['allow_guest_comments'] ?? false;

		if (! $uid && (! $allow_guest || $require_login)) {
			wp_send_json_error(array('message' => __('Login required to post a review', 'mhm-rentiva')));
		}

		// Validate character limits.
		$limits         = $comments_settings['limits'] ?? array();
		$min_length     = (int) ($limits['comment_length_min'] ?? 5);
		$max_length     = (int) ($limits['comment_length_max'] ?? 1000);
		$comment_length = mb_strlen(trim($comment));

		if ($min_length > 0 && $comment_length < $min_length) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: minimum character count */
						__('Comment is too short. Please write at least %d characters.', 'mhm-rentiva'),
						$min_length
					),
				)
			);
		}

		if ($max_length > 0 && $comment_length > $max_length) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: maximum character count */
						__('Comment is too long. Maximum %d characters allowed.', 'mhm-rentiva'),
						$max_length
					),
				)
			);
		}

		// Detect UPDATE vs NEW scenario early.
		// Updates must bypass rate-limiting because the existing comment already
		// counts against the rate window, causing a permanent block.
		$is_update = false;
		$existing  = null;

		if ($uid) {
			$existing = self::get_user_rating($vid);
			if ($existing) {
				$is_update = true;
			}
		}

		// Run spam protection only for NEW submissions.
		// Updates are safe: user already passed spam check on first submission.
		if (! $is_update) {
			if (! \MHMRentiva\Admin\Settings\Comments\CommentsSettings::check_spam_protection($uid, $vid, $comment)) {
				wp_send_json_error(array('message' => __('Your comment could not be submitted. Please wait a moment before trying again.', 'mhm-rentiva')));
			}
		}

		$comment_data = array(
			'comment_post_ID'  => $vid,
			'comment_content'  => $comment,
			'comment_type'     => 'review',
			'comment_approved' => ! empty($comments_settings['approval']['auto_approve']) ? 1 : 0,
		);

		// === UPDATE existing rating ===
		if ($is_update && $existing) {
			$comment_data['comment_ID'] = $existing['comment_id'];
			$comment_data['user_id']    = $uid;
			wp_update_comment($comment_data);
			update_comment_meta($existing['comment_id'], 'mhm_rating', $rating);
			self::update_vehicle_rating_meta($vid);
			wp_send_json_success(array('message' => __('Rating updated successfully', 'mhm-rentiva')));
		}

		// === NEW rating ===
		if ($uid) {
			$comment_data['user_id'] = $uid;
		} else {
			$comment_data['comment_author']       = sanitize_text_field(isset($_POST['guest_name']) ? wp_unslash($_POST['guest_name']) : '');
			$comment_data['comment_author_email'] = sanitize_email(isset($_POST['guest_email']) ? wp_unslash($_POST['guest_email']) : '');

			if (empty($comment_data['comment_author']) || empty($comment_data['comment_author_email'])) {
				wp_send_json_error(array('message' => __('Name and email are required for guests', 'mhm-rentiva')));
			}
		}

		$cid = wp_new_comment($comment_data, true);
		if ($cid && ! is_wp_error($cid)) {
			update_comment_meta($cid, 'mhm_rating', $rating);
			self::update_vehicle_rating_meta($vid);

			$message = $comment_data['comment_approved']
				? __('Rating submitted successfully', 'mhm-rentiva')
				: __('Rating submitted and awaiting approval', 'mhm-rentiva');
			wp_send_json_success(array('message' => $message));
		}

		if (is_wp_error($cid)) {
			wp_send_json_error(array('message' => $cid->get_error_message()));
		}

		wp_send_json_error(array('message' => __('Failed to submit rating', 'mhm-rentiva')));
	}

	public static function ajax_delete_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'mhm-rentiva')));
		}

		$uid = get_current_user_id();
		if (! $uid) {
			wp_send_json_error(array('message' => __('Login required', 'mhm-rentiva')));
		}

		// Support both comment_id and vehicle_id
		$cid = isset($_POST['comment_id']) ? absint(sanitize_text_field(wp_unslash((string) $_POST['comment_id']))) : 0;
		$vid = isset($_POST['vehicle_id']) ? absint(sanitize_text_field(wp_unslash((string) $_POST['vehicle_id']))) : 0;

		// If vehicle_id provided, find user's comment on that vehicle
		if (! $cid && $vid) {
			$user_comments = get_comments(
				array(
					'post_id' => $vid,
					'user_id' => $uid,
					'number'  => 1,
				)
			);
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
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhm_rentiva_rating_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed', 'mhm-rentiva')));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified above in this handler.
		$vid = isset($_POST['vehicle_id']) ? absint(sanitize_text_field(wp_unslash((string) $_POST['vehicle_id']))) : 0;
		if (! $vid) {
			wp_send_json_error(array('message' => __('Invalid vehicle', 'mhm-rentiva')));
		}

		$comments = get_comments(
			array(
				'post_id' => $vid,
				// 'type'    => 'review', // REMOVED: We want ALL comments, not just reviews
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
		\MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::recalculate_and_save($vehicle_id);
	}
}
