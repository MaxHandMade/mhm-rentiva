<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Core\Security\VerifiedRequest;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Blocked Dates Meta Box for Vehicles
 *
 * Stores dates when a vehicle is unavailable for reservations.
 * Data is saved as JSON array in postmeta key `_mhm_blocked_dates`.
 */
final class BlockedDatesMetaBox {

	private const META_KEY       = '_mhm_blocked_dates';
	private const META_KEY_NOTES = '_mhm_blocked_dates_notes';
	private const NONCE_ACTION   = 'mhmrentiva_blocked_dates_save';
	private const NONCE_NAME     = 'mhmrentiva_blocked_dates_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes_mhmrentiva_vehicle', array( self::class, 'add_meta_box' ) );
		add_action( 'save_post_mhmrentiva_vehicle', array( self::class, 'save' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_mhmrentiva_apply_blocked_dates_to_all', array( self::class, 'ajax_apply_to_all' ) );
		add_action( 'wp_ajax_mhmrentiva_remove_blocked_dates_from_all', array( self::class, 'ajax_remove_from_all' ) );
		add_action( 'wp_ajax_mhmrentiva_toggle_blocked_date', array( self::class, 'ajax_toggle_blocked_date' ) );
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'mhmrentiva_blocked_dates',
			__( 'Blocked Dates', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			'mhmrentiva_vehicle',
			'normal',
			'default'
		);
	}

	public static function render( \WP_Post $post ): void {
		$blocked = self::get_blocked_dates( $post->ID );
		$count   = count( $blocked );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="mhm-blocked-dates-wrap">
			<div class="blocked-dates-header">
				<p class="blocked-dates-description">
					<span class="dashicons dashicons-lock"></span>
					<?php esc_html_e( 'Select dates when this vehicle is unavailable for reservations.', 'mhm-rentiva' ); ?>
				</p>
				<span class="blocked-dates-count-badge" id="mhm-blocked-count-badge" <?php echo $count === 0 ? 'style="display:none;"' : ''; ?>>
					<span id="mhm-blocked-count-num"><?php echo esc_html( (string) $count ); ?></span>
					<?php esc_html_e( 'days blocked', 'mhm-rentiva' ); ?>
				</span>
			</div>

			<div class="blocked-dates-body">
				<div class="blocked-dates-calendar-col">
					<div id="mhmrentiva_blocked_dates_picker"></div>
				</div>
				<div class="blocked-dates-chips-col">
					<div class="blocked-dates-chips-header">
						<span class="blocked-dates-chips-title"><?php esc_html_e( 'Blocked Days', 'mhm-rentiva' ); ?></span>
						<button type="button" id="mhm-clear-all-blocked" class="button-link blocked-dates-clear-btn" <?php echo $count === 0 ? 'style="display:none;"' : ''; ?>>
							<?php esc_html_e( 'Clear All', 'mhm-rentiva' ); ?>
						</button>
					</div>
					<div class="blocked-dates-chips" id="mhm-blocked-dates-chips">
						<div class="blocked-dates-empty" id="mhm-blocked-empty" <?php echo $count > 0 ? 'style="display:none;"' : ''; ?>>
							<span class="dashicons dashicons-calendar-alt"></span>
							<p><?php esc_html_e( 'No dates blocked yet. Click days on the calendar.', 'mhm-rentiva' ); ?></p>
						</div>
					</div>
				</div>
			</div>

			<input
				type="hidden"
				name="<?php echo esc_attr( self::META_KEY ); ?>"
				id="mhmrentiva_blocked_dates_value"
				value="<?php echo esc_attr( wp_json_encode( $blocked ) ); ?>"
			>
			<input
				type="hidden"
				name="<?php echo esc_attr( self::META_KEY_NOTES ); ?>"
				id="mhmrentiva_blocked_dates_notes_value"
				value="<?php echo esc_attr( wp_json_encode( self::get_blocked_notes( $post->ID ) ) ); ?>"
			>
			<input type="hidden" id="mhmrentiva_apply_to_all_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mhmrentiva_apply_blocked_to_all' ) ); ?>">
			<input type="hidden" id="mhmrentiva_remove_from_all_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mhmrentiva_remove_blocked_from_all' ) ); ?>">
			<input type="hidden" id="mhmrentiva_current_vehicle_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">

			<div class="blocked-dates-footer">
				<button type="button" id="mhm-apply-blocked-to-all" class="button blocked-dates-apply-all-btn" <?php echo empty( $blocked ) ? 'disabled' : ''; ?>>
					<span class="dashicons dashicons-share-alt2"></span>
					<?php esc_html_e( 'Apply to All Vehicles', 'mhm-rentiva' ); ?>
				</button>
				<button type="button" id="mhm-remove-blocked-from-all" class="button blocked-dates-remove-all-btn" <?php echo empty( $blocked ) ? 'disabled' : ''; ?>>
					<span class="dashicons dashicons-minus"></span>
					<?php esc_html_e( 'Remove from All Vehicles', 'mhm-rentiva' ); ?>
				</button>
				<span class="blocked-dates-apply-result" id="mhm-apply-result" style="display:none;"></span>
			</div>
		</div>
		<?php
	}

	public static function save( int $post_id ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! isset( $_POST[ self::META_KEY ] ) ) {
			return;
		}
		$raw   = sanitize_text_field( wp_unslash( $_POST[ self::META_KEY ] ) );
		$dates = json_decode( $raw, true );
		if ( ! is_array( $dates ) ) {
			$dates = array();
		}
		$clean = array();
		foreach ( $dates as $d ) {
			$sanitized = sanitize_text_field( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized ) ) {
				$clean[] = $sanitized;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean );
		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $clean ) );

		// Save notes — decode JSON first, then sanitize each value individually
		$notes_clean = array();
		// Cleaned on the read, like the dates payload above. sanitize_textarea_field()
		// strips tags, and PHP's strip_tags() truncates everything after an unclosed
		// "<" -- which would silently destroy the whole notes payload. The picker
		// therefore emits every "<" as its JSON unicode escape (see
		// assets/js/admin/blocked-dates.js), so the blob reaching this line has no
		// literal "<" to trip over. Each decoded note is sanitized again below.
		$raw_notes_str = isset( $_POST[ self::META_KEY_NOTES ] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST[ self::META_KEY_NOTES ] ) )
			: '{}';
		$notes_raw     = json_decode( $raw_notes_str, true );
		if ( is_array( $notes_raw ) ) {
			foreach ( $notes_raw as $d => $note ) {
				$d = sanitize_text_field( (string) $d );
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && in_array( $d, $clean, true ) ) {
					$note = sanitize_textarea_field( (string) $note );
					if ( $note !== '' ) {
						$notes_clean[ $d ] = $note;
					}
				}
			}
		}
		update_post_meta( $post_id, self::META_KEY_NOTES, wp_json_encode( (object) $notes_clean ) );
	}

	/**
	 * Get blocked dates for a vehicle.
	 *
	 * @param int $post_id Vehicle post ID.
	 * @return string[] Array of date strings in Y-m-d format.
	 */
	public static function get_blocked_dates( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY, true );
		if ( empty( $raw ) ) {
			return array();
		}
		$dates = json_decode( $raw, true );
		return is_array( $dates ) ? $dates : array();
	}

	/**
	 * Toggle a single blocked date for a vehicle (used by the calendar quick-block UI).
	 *
	 * Pure data operation — no nonce/capability check (the AJAX wrapper enforces those).
	 *
	 * @param int    $vehicle_id Vehicle post ID.
	 * @param string $date       Date in Y-m-d format.
	 * @return array{blocked:bool,count:int}|\WP_Error
	 */
	public static function toggle_blocked_date( int $vehicle_id, string $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return new \WP_Error( 'invalid_date', __( 'Invalid date format.', 'mhm-rentiva' ) );
		}
		$parts = explode( '-', $date );
		if ( ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) {
			return new \WP_Error( 'invalid_date', __( 'Invalid date format.', 'mhm-rentiva' ) );
		}

		$dates = self::get_blocked_dates( $vehicle_id );
		$key   = array_search( $date, $dates, true );

		if ( false !== $key ) {
			unset( $dates[ $key ] );
			$blocked = false;
		} else {
			$dates[] = $date;
			$blocked = true;
		}

		$dates = array_values( array_unique( $dates ) );
		sort( $dates );
		update_post_meta( $vehicle_id, self::META_KEY, wp_json_encode( $dates ) );

		return array(
			'blocked' => $blocked,
			'count'   => count( $dates ),
		);
	}

	/**
	 * AJAX: toggle a single blocked date from the monthly reservation calendar.
	 */
	public static function ajax_toggle_blocked_date(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_toggle_blocked_date' ) ) {
			wp_send_json_error( __( 'Security error.', 'mhm-rentiva' ) );
		}

		$vehicle_id = isset( $_POST['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( $vehicle_id <= 0 || ! current_user_can( 'edit_post', $vehicle_id ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		$date   = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$result = self::toggle_blocked_date( $vehicle_id, $date );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Get blocked date notes for a vehicle.
	 *
	 * @param int $post_id Vehicle post ID.
	 * @return array<string,string> Map of date → note.
	 */
	public static function get_blocked_notes( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_KEY_NOTES, true );
		if ( empty( $raw ) ) {
			return array();
		}
		$notes = json_decode( $raw, true );
		return is_array( $notes ) ? $notes : array();
	}

	/**
	 * AJAX: Apply blocked dates of this vehicle to all other vehicles.
	 */
	public static function ajax_apply_to_all(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_apply_blocked_to_all' ) ) {
			wp_send_json_error( __( 'Security error.', 'mhm-rentiva' ) );
		}
		$source_id = isset( $_POST['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( $source_id <= 0 ) {
			wp_send_json_error( __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
		}

		// This writes blocked-date meta on EVERY published vehicle, so the blanket
		// edit_posts was the wrong gate: the vehicle CPT registers with
		// map_meta_cap and the default 'post' capability_type, which means a stock
		// Author who owns one listing holds edit_posts and is handed a real nonce on
		// their own vehicle's edit screen -- enough to rewrite the whole fleet's
		// availability. A fleet-wide write needs the capability that actually means
		// "may edit content you do not own".
		if ( ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		// Prefer dates from browser payload (unsaved state); fall back to DB.
		$req   = VerifiedRequest::from( $_POST );
		$dates = self::parse_dates_from_payload( $req );
		$notes = ! empty( $dates ) ? self::parse_notes_from_payload( $req, $dates ) : array();

		if ( empty( $dates ) ) {
			$dates = self::get_blocked_dates( $source_id );
			$notes = self::get_blocked_notes( $source_id );
		}

		if ( empty( $dates ) ) {
			wp_send_json_error( __( 'No blocked dates selected.', 'mhm-rentiva' ) );
		}

		$dates_json = wp_json_encode( $dates );
		$notes_json = wp_json_encode( (object) $notes );

		// Also save to source vehicle so DB is in sync with browser.
		update_post_meta( $source_id, self::META_KEY, $dates_json );
		update_post_meta( $source_id, self::META_KEY_NOTES, $notes_json );

		$vehicles = get_posts( array(
			'post_type'      => 'mhmrentiva_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- post__not_in kept intentionally; alternative `post__in` query would require extra ID collection round-trip.
			'exclude'        => array( $source_id ),
		) );

		foreach ( $vehicles as $vid ) {
			update_post_meta( (int) $vid, self::META_KEY, $dates_json );
			update_post_meta( (int) $vid, self::META_KEY_NOTES, $notes_json );
		}

		wp_send_json_success( array( 'count' => count( $vehicles ) ) );
	}

	/**
	 * AJAX: Remove this vehicle's blocked dates from all other vehicles.
	 * Only removes the intersection — does not touch other blocked dates.
	 */
	public static function ajax_remove_from_all(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'mhmrentiva_remove_blocked_from_all' ) ) {
			wp_send_json_error( __( 'Security error.', 'mhm-rentiva' ) );
		}
		$source_id = isset( $_POST['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( $source_id <= 0 ) {
			wp_send_json_error( __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
		}

		// This writes blocked-date meta on EVERY published vehicle, so the blanket
		// edit_posts was the wrong gate: the vehicle CPT registers with
		// map_meta_cap and the default 'post' capability_type, which means a stock
		// Author who owns one listing holds edit_posts and is handed a real nonce on
		// their own vehicle's edit screen -- enough to rewrite the whole fleet's
		// availability. A fleet-wide write needs the capability that actually means
		// "may edit content you do not own".
		if ( ! current_user_can( 'edit_post', $source_id ) || ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		// Prefer dates from browser payload (unsaved state); fall back to DB.
		$dates_to_remove = self::parse_dates_from_payload( VerifiedRequest::from( $_POST ) );
		if ( empty( $dates_to_remove ) ) {
			$dates_to_remove = self::get_blocked_dates( $source_id );
		}
		if ( empty( $dates_to_remove ) ) {
			wp_send_json_success( array( 'count' => 0 ) );
			return;
		}

		$vehicles = get_posts( array(
			'post_type'      => 'mhmrentiva_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- post__not_in kept intentionally; alternative `post__in` query would require extra ID collection round-trip.
			'exclude'        => array( $source_id ),
		) );

		foreach ( $vehicles as $vid ) {
			$vid      = (int) $vid;
			$existing = self::get_blocked_dates( $vid );
			$updated  = array_values( array_diff( $existing, $dates_to_remove ) );
			update_post_meta( $vid, self::META_KEY, wp_json_encode( $updated ) );

			// Also remove notes for deleted dates
			$existing_notes = self::get_blocked_notes( $vid );
			foreach ( $dates_to_remove as $d ) {
				unset( $existing_notes[ $d ] );
			}
			update_post_meta( $vid, self::META_KEY_NOTES, wp_json_encode( (object) $existing_notes ) );
		}

		wp_send_json_success( array( 'count' => count( $vehicles ) ) );
	}

	/**
	 * Parse and sanitize blocked dates from AJAX payload.
	 *
	 * @return string[] Sanitized date strings in Y-m-d format.
	 */
	private static function parse_dates_from_payload( VerifiedRequest $req ): array {
		if ( ! $req->has( 'dates' ) ) {
			return array();
		}
		$dates = json_decode( $req->text( 'dates' ), true );
		if ( ! is_array( $dates ) ) {
			return array();
		}
		$clean = array();
		foreach ( $dates as $d ) {
			$sanitized = sanitize_text_field( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $sanitized ) ) {
				$clean[] = $sanitized;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean );
		return $clean;
	}

	/**
	 * Parse and sanitize blocked date notes from AJAX payload.
	 *
	 * @param string[] $valid_dates Only keep notes for these dates.
	 * @return array<string,string> Map of date → note.
	 */
	private static function parse_notes_from_payload( VerifiedRequest $req, array $valid_dates ): array {
		if ( ! $req->has( 'notes' ) ) {
			return array();
		}
		// Decoded raw, then every note goes through sanitize_textarea_field below.
		$notes = json_decode( (string) $req->raw( 'notes' ), true );
		if ( ! is_array( $notes ) ) {
			return array();
		}
		$clean = array();
		foreach ( $notes as $d => $note ) {
			$d = sanitize_text_field( (string) $d );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && in_array( $d, $valid_dates, true ) ) {
				$note = sanitize_textarea_field( (string) $note );
				if ( $note !== '' ) {
					$clean[ $d ] = $note;
				}
			}
		}
		return $clean;
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		global $post_type;
		if ( $post_type !== 'mhmrentiva_vehicle' ) {
			return;
		}
		wp_enqueue_style(
			'flatpickr',
			MHMRENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
			array(),
			'4.6.13'
		);
		wp_enqueue_style(
			'mhm-rentiva-blocked-dates',
			MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/blocked-dates.css',
			array( 'flatpickr' ),
			MHMRENTIVA_VERSION
		);
		wp_enqueue_script(
			'flatpickr',
			MHMRENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
			array(),
			'4.6.13',
			true
		);

		// Localize flatpickr (currently TR; other languages fall back to default English).
		$flatpickr_locale = self::resolve_flatpickr_locale();
		if ( $flatpickr_locale !== null ) {
			wp_enqueue_script(
				'flatpickr-l10n-' . $flatpickr_locale,
				MHMRENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/l10n/' . $flatpickr_locale . '.min.js',
				array( 'flatpickr' ),
				'4.6.13',
				true
			);
		}

		wp_enqueue_script(
			'mhm-rentiva-blocked-dates',
			MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/blocked-dates.js',
			array( 'jquery', 'flatpickr' ),
			MHMRENTIVA_VERSION,
			true
		);
		wp_localize_script( 'mhm-rentiva-blocked-dates', 'mhmBlockedDatesL10n', array(
			'confirmApply'    => __( 'All blocked dates selected for this vehicle will be applied to all other vehicles, overwriting their existing blocked dates. Do you want to continue?', 'mhm-rentiva' ),
			'confirmRemove'   => __( 'All blocked dates selected for this vehicle will be removed from all other vehicles. Do you want to continue?', 'mhm-rentiva' ),
			/* translators: %d: number of vehicles */
			'appliedTo'       => __( 'Applied to %d vehicles.', 'mhm-rentiva' ),
			/* translators: %d: number of vehicles */
			'removedFrom'     => __( 'Removed from %d vehicles.', 'mhm-rentiva' ),
			'error'           => __( 'An error occurred.', 'mhm-rentiva' ),
			'notePlaceholder' => __( 'Add note... (optional)', 'mhm-rentiva' ),
			'flatpickrLocale' => $flatpickr_locale,
		) );
	}

	/**
	 * Resolve the flatpickr locale code to load for the current WordPress locale.
	 *
	 * Returns the short locale string (e.g. 'tr') when a matching l10n file is
	 * bundled, or null to leave flatpickr in its default English locale.
	 *
	 * @return string|null
	 */
	private static function resolve_flatpickr_locale(): ?string {
		$wp_locale = (string) get_locale();
		$short     = strtolower( substr( $wp_locale, 0, 2 ) );

		// Bundled flatpickr l10n files under assets/vendor/flatpickr/l10n/.
		$supported = array( 'tr' );

		return in_array( $short, $supported, true ) ? $short : null;
	}
}
