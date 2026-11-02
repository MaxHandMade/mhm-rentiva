<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Shortcodes\Contact\RenderStamp;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Settings\Comments\CommentsSettings;
use MHMRentiva\Admin\Vehicle\Helpers\RatingHelper;
use MHMRentiva\Admin\Vehicle\Helpers\VerifiedReviewHelper;
use MHMRentiva\Admin\Vehicle\Hooks\ReviewEnforcer;
use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;

/**
 * Vehicle Rating Form Shortcode
 *
 * Known limit: a non-numeric explicit `vehicle_id` (e.g. "abc") arrives as 0 from
 * the attribute mapper and is therefore treated as "not given".
 */
final class VehicleRatingForm extends AbstractShortcode {



	public const SHORTCODE = 'rentiva_vehicle_rating_form';

	private const SCRIPT_HANDLE = 'mhm-rentiva-vehicle-rating-form';

	/**
	 * Most reviews one list draws, whatever the per-page setting says: the list is on
	 * a public, cacheable page.
	 */
	private const MAX_LIST_ROWS = 50;

	protected static bool $rvs_shell = true;

	public static function register(): void
	{
		parent::register();
		add_action('wp_ajax_mhmrentiva_submit_rating', array( self::class, 'ajax_submit_rating' ));
		add_action('wp_ajax_nopriv_mhmrentiva_submit_rating', array( self::class, 'ajax_submit_rating' ));
		add_action('wp_ajax_mhmrentiva_delete_rating', array( self::class, 'ajax_delete_rating' ));
		add_action('wp_ajax_nopriv_mhmrentiva_delete_rating', array( self::class, 'ajax_delete_rating' ));
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

	/**
	 * The form carries a nonce: it stays out of the HTML cache (PR F R9). Standalone
	 * only: inside a cached parent such as the vehicle page the parent's cache decides.
	 */
	protected static function is_caching_enabled(): bool
	{
		return false;
	}

	/**
	 * No cache key either: building one writes the cache version transient.
	 */
	protected static function get_cache_key(array $atts): string
	{
		return '';
	}

	protected static function enqueue_assets(array $atts = array()): void
	{
		AssetManager::enqueue_rvs_reviews_styles();
		AssetManager::enqueue_rvs_dialog();

		wp_enqueue_script(self::SCRIPT_HANDLE);
		self::localize_script(self::SCRIPT_HANDLE);
	}

	/**
	 * For a parent that draws this form inside its own markup: on a cache hit the
	 * parent's HTML comes back without the nested shortcode ever running.
	 */
	public static function enqueue_nested_assets(): void
	{
		self::enqueue_assets_once();
	}

	protected static function get_script_object_name(): string
	{
		return 'mhmVehicleRating';
	}

	protected static function get_localized_data(): array
	{
		$data = parent::get_localized_data();

		$data['nonce'] = RenderContext::script_nonce('mhmrentiva_rating_nonce');

		return $data;
	}

	protected static function get_localized_strings(): array
	{
		return array(
			'name_required'  => __('Enter your name.', 'mhm-rentiva'),
			'email_required' => __('Enter a valid email address.', 'mhm-rentiva'),
			'select_rating'  => __('Choose a rating from 1 to 5.', 'mhm-rentiva'),
			/* translators: %1$s: fewest characters allowed. */
			'comment_short'  => __('A review needs at least %1$s characters.', 'mhm-rentiva'),
			/* translators: %1$s: most characters allowed. */
			'comment_long'   => __('A review can have at most %1$s characters.', 'mhm-rentiva'),
			/* translators: 1: characters written, 2: most characters allowed, 3: fewest characters allowed. */
			'count_min'      => __('%1$s / %2$s, at least %3$s characters', 'mhm-rentiva'),
			/* translators: 1: characters written, 2: most characters allowed. */
			'count'          => __('%1$s / %2$s', 'mhm-rentiva'),
			'send_failed'    => __('Your review could not be sent', 'mhm-rentiva'),
			'connection'     => __('The connection was lost. What you wrote is still here; try again.', 'mhm-rentiva'),
			'sending'        => __('Sending...', 'mhm-rentiva'),
			'update'         => __('Update review', 'mhm-rentiva'),
			'edit_heading'   => __('Edit your review', 'mhm-rentiva'),
			'deleted_title'  => __('Your review was deleted', 'mhm-rentiva'),
			'deleted'        => __('Your rating and review were removed.', 'mhm-rentiva'),
		);
	}

	protected static function prepare_template_data(array $atts): array
	{
		[ $vehicle_id, $badge ] = self::resolve_vehicle($atts);
		if ($vehicle_id <= 0) {
			return array(
				'vehicle_id'    => 0,
				'badge_message' => $badge,
			);
		}

		// These three toggles map one-to-one onto the template's three sections
		// (summary, review list, submission form).
		$to_bool = static function ($value): bool {
			return filter_var($value, FILTER_VALIDATE_BOOLEAN);
		};

		$settings   = CommentsSettings::get_settings();
		$display    = (array) ( $settings['display'] ?? array() );
		$limits     = (array) ( $settings['limits'] ?? array() );
		$logged_in  = is_user_logged_in();
		$allow_post = ReviewsView::can_post(
			$logged_in,
			(bool) ( $settings['approval']['require_login'] ?? true ),
			(bool) ( $settings['approval']['allow_guest_comments'] ?? false )
		);
		$show_form  = $to_bool($atts['show_form'] ?? '1');
		$show_list  = $to_bool($atts['show_ratings_list'] ?? '1');
		$cards      = $show_list ? self::review_cards($vehicle_id, $display, $show_form && $allow_post) : array();
		$own        = $logged_in ? self::get_user_rating($vehicle_id) : null;
		$mode       = ReviewsView::form_mode(null !== $own, $show_list, (bool) ( $display['allow_editing'] ?? true ));

		return array(
			'vehicle_id'          => $vehicle_id,
			'badge_message'       => $badge,
			'uid'                 => wp_unique_id('rvs-reviews-'),
			'nested'              => self::is_nested_in_rvs_shell(),
			'class'               => (string) ( $atts['class'] ?? '' ),
			'summary'             => ReviewsView::summary(RatingHelper::get_rating($vehicle_id), RatingHelper::get_distribution($vehicle_id)),
			'show_rating_display' => $to_bool($atts['show_rating_display'] ?? '1'),
			'show_form'           => $show_form,
			'show_ratings_list'   => $show_list,
			'cards'               => $cards,
			'can_post'            => $allow_post,
			'form_mode'           => $mode,
			'own_rating'          => ReviewsView::FORM_EDIT === $mode ? (int) ( $own['rating'] ?? 0 ) : 0,
			'own_comment'         => ReviewsView::FORM_EDIT === $mode ? (string) ( $own['comment'] ?? '' ) : '',
			'guest_fields'        => ! $logged_in && $allow_post,
			'has_dialog'          => array() !== array_filter(array_column($cards, 'can_delete')),
			'min_length'          => (int) ( $limits['comment_length_min'] ?? 5 ),
			'max_length'          => (int) ( $limits['comment_length_max'] ?? 1000 ),
			// A preview prints none: nothing can be sent from it.
			'render_stamp'        => RenderContext::current()->is_preview() ? '' : RenderStamp::issue(RenderStamp::FORM_REVIEW),
		);
	}

	/**
	 * The list: the visitor's own review first, approved or waiting (R12), then the
	 * public reviews. Nobody else's waiting review, ever; a guest has no "own".
	 *
	 * @param array<string, mixed> $display  Comment display settings.
	 * @param bool                 $can_edit The form is on the page to edit in.
	 * @return list<array<string, mixed>> ReviewsView::card().
	 */
	private static function review_cards(int $vehicle_id, array $display, bool $can_edit): array
	{
		$own      = self::get_user_rating($vehicle_id);
		$own_id   = null === $own ? 0 : (int) $own['comment_id'];
		$comments = array_values(
			array_filter(
				RatingHelper::get_public_reviews($vehicle_id, min(self::MAX_LIST_ROWS, CommentsSettings::get_comments_per_page())),
				static fn(\WP_Comment $comment): bool => (int) $comment->comment_ID !== $own_id
			)
		);
		if ($own_id > 0) {
			$own_comment = get_comment($own_id);
			if ($own_comment instanceof \WP_Comment) {
				array_unshift($comments, $own_comment);
			}
		}
		if (array() === $comments) {
			return array();
		}

		// One query for the whole list, no N+1.
		$verified = VerifiedReviewHelper::get_verified_comment_ids_for_vehicle($vehicle_id);
		$preview  = RenderContext::current()->is_preview();
		$cards    = array();
		foreach ($comments as $comment) {
			$id     = (int) $comment->comment_ID;
			$is_own = $id === $own_id;
			$author = (string) $comment->comment_author;
			if ('' === $author && (int) $comment->user_id > 0) {
				$user   = get_userdata( (int) $comment->user_id);
				$author = $user ? (string) $user->display_name : '';
			}
			$cards[] = ReviewsView::card(
				array(
					'id'          => $id,
					'author'      => $author,
					'rating'      => (int) get_comment_meta($id, 'mhmrentiva_rating', true),
					'text'        => (string) $comment->comment_content,
					'date'        => (string) $comment->comment_date,
					'pending'     => '1' !== (string) $comment->comment_approved,
					'verified'    => in_array($id, $verified, true),
					'own'         => $is_own,
					'can_edit'    => $can_edit && (bool) ( $display['allow_editing'] ?? true ),
					'can_delete'  => (bool) ( $display['allow_deletion'] ?? true ),
					'show_avatar' => (bool) ( $display['show_avatars'] ?? true ),
					'show_rating' => (bool) ( $display['show_ratings'] ?? true ),
					'disabled'    => $preview,
				)
			);
		}

		return $cards;
	}

	/**
	 * Which vehicle the form binds to: the shared rule in
	 * PreviewSamples::resolve_vehicle() (explicit id must be publicly readable,
	 * else the page's vehicle, else a preview sample). The template never looks
	 * for an id of its own.
	 *
	 * @param array<string,mixed> $atts Normalised shortcode attributes.
	 * @return array{0:int,1:string} Vehicle ID (0 = none) and the preview badge text ('' = no badge).
	 */
	private static function resolve_vehicle(array $atts): array
	{
		// CanonicalAttributeMapper types vehicle_id as int, so "not given" arrives as 0.
		return PreviewSamples::resolve_vehicle( (int) ( $atts['vehicle_id'] ?? 0 ) );
	}

	/**
	 * Prefixes the preview badge (empty on the front end) to the rendered form.
	 *
	 * @param array<string,mixed> $template_data Prepared template data.
	 */
	protected static function render_template(array $template_data): string
	{
		$badge = (string) ( $template_data['badge_message'] ?? '' );
		unset($template_data['badge_message']);

		// A badge with no vehicle is the invalid-vehicle badge: it already says what
		// to check, so the template's "No vehicle selected" instruction is left out.
		// (Outside a preview RenderContext::badge() is '' and the template's state shows.)
		if ('' !== $badge && (int) ( $template_data['vehicle_id'] ?? 0 ) <= 0) {
			$badge_html = RenderContext::badge($badge);
			if ('' !== $badge_html) {
				return $badge_html;
			}
		}

		$html = parent::render_template($template_data);
		if ('' === $html || '' === $badge) {
			return $html;
		}

		return RenderContext::badge($badge) . $html;
	}

	public static function get_vehicle_rating(int $vehicle_id): array
	{
		return RatingHelper::get_rating($vehicle_id);
	}

	public static function get_user_rating(int $vehicle_id): ?array
	{
		return RatingHelper::get_user_review($vehicle_id, get_current_user_id());
	}

	public static function ajax_submit_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhmrentiva_rating_nonce')) {
			wp_send_json_error(array( 'message' => __('Security check failed', 'mhm-rentiva') ));
		}

		$vid     = absint(isset($_POST['vehicle_id']) ? wp_unslash($_POST['vehicle_id']) : 0);
		$rating  = absint(isset($_POST['rating']) ? wp_unslash($_POST['rating']) : 0);
		$comment = wp_kses_post(isset($_POST['comment']) ? wp_unslash($_POST['comment']) : '');
		$uid     = get_current_user_id();

		if ($vid <= 0 || $rating < 1 || $rating > 5) {
			wp_send_json_error(array( 'message' => __('Invalid vehicle or rating value', 'mhm-rentiva') ));
		}

		// The ID has to name an actual vehicle. Without this the value flows
		// straight into comment_post_ID and update_vehicle_rating_meta(), so a
		// nonce-carrying submitter could attach a `review` comment and vehicle
		// rating aggregates to any post on the site.
		if (get_post_type($vid) !== 'mhmrentiva_vehicle') {
			wp_send_json_error(array( 'message' => __('Invalid vehicle or rating value', 'mhm-rentiva') ));
		}

		$comments_settings = \MHMRentiva\Admin\Settings\Comments\CommentsSettings::get_settings();
		$can_post          = ReviewsView::can_post(
			$uid > 0,
			(bool) ( $comments_settings['approval']['require_login'] ?? true ),
			(bool) ( $comments_settings['approval']['allow_guest_comments'] ?? false )
		);

		if (! $can_post) {
			wp_send_json_error(array( 'message' => __('Login required to post a review', 'mhm-rentiva') ));
		}

		// Validate character limits.
		$limits         = $comments_settings['limits'] ?? array();
		$min_length     = (int) ( $limits['comment_length_min'] ?? 5 );
		$max_length     = (int) ( $limits['comment_length_max'] ?? 1000 );
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

		// The setting is the server's too, not only the missing button: without this a
		// direct request still rewrites the review. Refused before anything is written.
		if ($is_update && ! ( $comments_settings['display']['allow_editing'] ?? true )) {
			wp_send_json_error(array( 'message' => __('Editing reviews is turned off on this site.', 'mhm-rentiva') ));
		}

		// Run spam protection only for NEW submissions.
		// Updates are safe: user already passed spam check on first submission.
		if (! $is_update) {
			$honeypot = isset($_POST[ ContactFormView::HONEYPOT_FIELD ]) ? sanitize_text_field(wp_unslash( (string) $_POST[ ContactFormView::HONEYPOT_FIELD ])) : '';
			$stamp    = isset($_POST[ RenderStamp::FIELD ]) ? sanitize_text_field(wp_unslash( (string) $_POST[ RenderStamp::FIELD ])) : null;
			if ('' !== $honeypot || RenderStamp::too_fast($stamp, RenderStamp::FORM_REVIEW) || ! \MHMRentiva\Admin\Settings\Comments\CommentsSettings::check_spam_protection($uid, $vid, $comment)) {
				wp_send_json_error(array( 'message' => __('Your comment could not be submitted. Please wait a moment before trying again.', 'mhm-rentiva') ));
			}
		}

		$comment_data = array(
			'comment_post_ID'  => $vid,
			'comment_content'  => $comment,
			'comment_type'     => 'review',
			// ReviewEnforcer (preprocess_comment) enforces the 1-5 rating. It reads
			// this value from the comment data rather than from $_POST, so the
			// nonce-verified handler that owns the request has to pass it along.
			'comment_meta'     => array( 'rating' => $rating ),
		);

		// === UPDATE existing rating ===
		if ($is_update && $existing) {
			$comment_data['comment_ID'] = $existing['comment_id'];
			$comment_data['user_id']    = $uid;
			// Approved stays approved only under auto approve; approved under moderation
			// goes back to waiting. Waiting stays waiting either way: an edit never
			// undoes an administrator's "unapprove". (get_user_review() finds 0 and 1
			// only, so spam and trash never arrive here.)
			$comment_data['comment_approved'] = '1' === (string) $existing['status'] && ! empty($comments_settings['approval']['auto_approve']) ? 1 : 0;
			// Core unslashes what these two APIs are given: unslashed text loses its backslashes.
			$updated = wp_update_comment(wp_slash($comment_data), true);
			if (is_wp_error($updated)) {
				wp_send_json_error(array( 'message' => $updated->get_error_message() ));
			}
			// 0 is "nothing to change" and goes on: the rating may still differ.
			if (false === $updated) {
				wp_send_json_error(array( 'message' => __('Failed to submit rating', 'mhm-rentiva') ));
			}
			update_comment_meta($existing['comment_id'], 'mhmrentiva_rating', $rating);
			self::update_vehicle_rating_meta($vid);
			wp_send_json_success(ReviewsView::submission_answer(self::is_approved( (int) $existing['comment_id']), true, false));
		}

		// === NEW rating ===
		// wp_new_comment() reads comment_author, comment_author_email and
		// comment_author_url straight out of this array before it applies any
		// default of its own -- wp_allow_comment()'s duplicate and flood checks
		// read them, and so do wp_filter_comment()'s pre_comment_* filters. Every
		// branch therefore has to supply all three keys or a site running
		// WP_DEBUG logs an "Undefined array key" warning for each one.
		if ($uid) {
			// Mirrors wp_handle_comment_submission(): display name (falling back
			// to the login, exactly as core does), account email, account URL.
			$user = wp_get_current_user();

			$comment_data['user_id']              = $uid;
			$comment_data['comment_author']       = empty($user->display_name) ? (string) $user->user_login : (string) $user->display_name;
			$comment_data['comment_author_email'] = (string) $user->user_email;
			$comment_data['comment_author_url']   = (string) $user->user_url;
		} else {
			// The form collects no guest website field, so comment_author_url
			// gets the empty string core itself falls back to when a submission
			// carries no `url` value.
			$comment_data['comment_author']       = sanitize_text_field(isset($_POST['guest_name']) ? wp_unslash($_POST['guest_name']) : '');
			$comment_data['comment_author_email'] = sanitize_email(isset($_POST['guest_email']) ? wp_unslash($_POST['guest_email']) : '');
			$comment_data['comment_author_url']   = '';

			if (empty($comment_data['comment_author']) || empty($comment_data['comment_author_email'])) {
				wp_send_json_error(array( 'message' => __('Name and email are required for guests', 'mhm-rentiva') ));
			}
		}

		// wp_new_comment() likewise reads comment_author_IP and comment_agent
		// before applying any default (preg_replace/substr on the raw values),
		// which on PHP 8.1+ surfaces as a core deprecation when the keys are
		// absent — under WP-CLI or test requests REMOTE_ADDR is unset and the
		// notice pollutes the JSON output buffer. Sourced from $_SERVER exactly
		// as core's wp_handle_comment_submission() does.
		$comment_data['comment_author_IP'] = isset($_SERVER['REMOTE_ADDR'])
			? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
			: '';
		$comment_data['comment_agent']     = isset($_SERVER['HTTP_USER_AGENT'])
			? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 254)
			: '';

		// Refused here, as JSON: the check inside core's pipeline can only wp_die().
		if (ReviewEnforcer::has_review($vid, $uid, (string) $comment_data['comment_author_email'])) {
			wp_send_json_error(array( 'message' => ReviewEnforcer::duplicate_message() ));
		}

		$cid = wp_new_comment(wp_slash($comment_data), true);
		if ($cid && ! is_wp_error($cid)) {
			update_comment_meta($cid, 'mhmrentiva_rating', $rating);
			self::update_vehicle_rating_meta($vid);

			wp_send_json_success(ReviewsView::submission_answer(self::is_approved( (int) $cid), false, 0 === $uid));
		}

		if (is_wp_error($cid)) {
			wp_send_json_error(array( 'message' => $cid->get_error_message() ));
		}

		wp_send_json_error(array( 'message' => __('Failed to submit rating', 'mhm-rentiva') ));
	}

	public static function ajax_delete_rating(): void
	{
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash( (string) $_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'mhmrentiva_rating_nonce')) {
			wp_send_json_error(array( 'message' => __('Security check failed', 'mhm-rentiva') ));
		}

		$uid = get_current_user_id();
		if (! $uid) {
			wp_send_json_error(array( 'message' => __('Login required', 'mhm-rentiva') ));
		}

		// Support both comment_id and vehicle_id
		$cid = isset($_POST['comment_id']) ? absint(sanitize_text_field(wp_unslash( (string) $_POST['comment_id']))) : 0;
		$vid = isset($_POST['vehicle_id']) ? absint(sanitize_text_field(wp_unslash( (string) $_POST['vehicle_id']))) : 0;

		// If vehicle_id provided, find user's comment on that vehicle
		if (! $cid && $vid) {
			$user_comments = get_comments(
				array(
					'post_id' => $vid,
					'user_id' => $uid,
					'type'    => 'review',
					'number'  => 1,
				)
			);
			if (! empty($user_comments)) {
				$cid = $user_comments[0]->comment_ID;
			}
		}

		if (! $cid) {
			wp_send_json_error(array( 'message' => __('Comment not found', 'mhm-rentiva') ));
		}

		$c = get_comment($cid);
		if (! $c) {
			wp_send_json_error(array( 'message' => __('Comment not found', 'mhm-rentiva') ));
		}

		// Checked on the resolved comment rather than on the submitted vehicle_id,
		// so both entry points are covered: without it this endpoint deletes the
		// caller's comment on any post and then writes vehicle rating aggregates
		// onto that post through update_vehicle_rating_meta().
		if (get_post_type( (int) $c->comment_post_ID) !== 'mhmrentiva_vehicle') {
			wp_send_json_error(array( 'message' => __('Comment not found', 'mhm-rentiva') ));
		}

		// Check permission: an admin always; the owner while the setting allows it.
		$settings  = CommentsSettings::get_settings();
		$owner_may = (int) $c->user_id === $uid && (bool) ( $settings['display']['allow_deletion'] ?? true );
		if ($owner_may || current_user_can('manage_options')) {
			$vid = (int) $c->comment_post_ID;
			if (! wp_delete_comment($cid, true)) {
				wp_send_json_error(array( 'message' => __('The review could not be deleted.', 'mhm-rentiva') ));
			}

			// Update vehicle meta
			self::update_vehicle_rating_meta($vid);

			wp_send_json_success(array( 'message' => __('Rating deleted successfully', 'mhm-rentiva') ));
		}

		wp_send_json_error(array( 'message' => __('Permission denied', 'mhm-rentiva') ));
	}

	/**
	 * What was stored, not what was asked for: core can change the status on insert.
	 */
	private static function is_approved(int $comment_id): bool
	{
		return 'approved' === wp_get_comment_status($comment_id);
	}

	/**
	 * Update vehicle rating meta information
	 */
	public static function update_vehicle_rating_meta(int $vehicle_id): void
	{
		RatingHelper::recalculate_and_save($vehicle_id);
	}
}
