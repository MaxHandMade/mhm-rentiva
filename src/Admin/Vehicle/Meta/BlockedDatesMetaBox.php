<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vehicle\Meta;

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
	private const NONCE_ACTION   = 'mhm_blocked_dates_save';
	private const NONCE_NAME     = 'mhm_blocked_dates_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes_vehicle', array( self::class, 'add_meta_box' ) );
		add_action( 'save_post_vehicle', array( self::class, 'save' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_mhm_get_blocked_dates', array( self::class, 'ajax_get_blocked_dates' ) );
		add_action( 'wp_ajax_nopriv_mhm_get_blocked_dates', array( self::class, 'ajax_get_blocked_dates' ) );
		add_action( 'wp_ajax_mhm_apply_blocked_dates_to_all', array( self::class, 'ajax_apply_to_all' ) );
		add_action( 'wp_ajax_mhm_remove_blocked_dates_from_all', array( self::class, 'ajax_remove_from_all' ) );
	}

	public static function add_meta_box(): void {
		add_meta_box(
			'mhm_blocked_dates',
			__( 'Blocked Dates', 'mhm-rentiva' ),
			array( self::class, 'render' ),
			'vehicle',
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
					<div id="mhm_blocked_dates_picker"></div>
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
				id="mhm_blocked_dates_value"
				value="<?php echo esc_attr( wp_json_encode( $blocked ) ); ?>"
			>
			<input
				type="hidden"
				name="<?php echo esc_attr( self::META_KEY_NOTES ); ?>"
				id="mhm_blocked_dates_notes_value"
				value="<?php echo esc_attr( wp_json_encode( self::get_blocked_notes( $post->ID ) ) ); ?>"
			>
			<input type="hidden" id="mhm_apply_to_all_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mhm_apply_blocked_to_all' ) ); ?>">
			<input type="hidden" id="mhm_remove_from_all_nonce" value="<?php echo esc_attr( wp_create_nonce( 'mhm_remove_blocked_from_all' ) ); ?>">
			<input type="hidden" id="mhm_current_vehicle_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">

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
		if (
			! isset( $_POST[ self::NONCE_NAME ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION )
		) {
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
		$notes_clean   = array();
		$raw_notes_str = isset( $_POST[ self::META_KEY_NOTES ] )
			? wp_unslash( (string) $_POST[ self::META_KEY_NOTES ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is sanitized after decoding per note value.
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
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mhm_apply_blocked_to_all' )
		) {
			wp_send_json_error( __( 'Security error.', 'mhm-rentiva' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		$source_id = isset( $_POST['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( $source_id <= 0 ) {
			wp_send_json_error( __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
		}

		// Prefer dates from browser payload (unsaved state); fall back to DB.
		$dates = self::parse_dates_from_payload();
		$notes = ! empty( $dates ) ? self::parse_notes_from_payload( $dates ) : array();

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
			'post_type'      => 'vehicle',
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
		if (
			! isset( $_POST['nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mhm_remove_blocked_from_all' )
		) {
			wp_send_json_error( __( 'Security error.', 'mhm-rentiva' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'mhm-rentiva' ) );
		}

		$source_id = isset( $_POST['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : 0;
		if ( $source_id <= 0 ) {
			wp_send_json_error( __( 'Invalid vehicle ID.', 'mhm-rentiva' ) );
		}

		// Prefer dates from browser payload (unsaved state); fall back to DB.
		$dates_to_remove = self::parse_dates_from_payload();
		if ( empty( $dates_to_remove ) ) {
			$dates_to_remove = self::get_blocked_dates( $source_id );
		}
		if ( empty( $dates_to_remove ) ) {
			wp_send_json_success( array( 'count' => 0 ) );
			return;
		}

		$vehicles = get_posts( array(
			'post_type'      => 'vehicle',
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
	private static function parse_dates_from_payload(): array {
		if ( ! isset( $_POST['dates'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling AJAX handlers.
			return array();
		}
		$raw   = sanitize_text_field( wp_unslash( $_POST['dates'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling AJAX handlers.
		$dates = json_decode( $raw, true );
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
	private static function parse_notes_from_payload( array $valid_dates ): array {
		if ( ! isset( $_POST['notes'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the calling AJAX handlers.
			return array();
		}
		$raw   = wp_unslash( (string) $_POST['notes'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is sanitized after decoding per note value.
		$notes = json_decode( $raw, true );
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

	public static function ajax_get_blocked_dates(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read endpoint; no state change.
		$vehicle_id = isset( $_GET['vehicle_id'] ) ? (int) sanitize_text_field( wp_unslash( $_GET['vehicle_id'] ) ) : 0;
		if ( $vehicle_id <= 0 ) {
			wp_send_json_error( 'Invalid vehicle ID' );
		}
		wp_send_json_success( self::get_blocked_dates( $vehicle_id ) );
	}

	public static function enqueue_scripts( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		global $post_type;
		if ( $post_type !== 'vehicle' ) {
			return;
		}
		wp_enqueue_style(
			'flatpickr',
			MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.css',
			array(),
			'4.6.13'
		);
		wp_enqueue_style(
			'mhm-blocked-dates',
			MHM_RENTIVA_PLUGIN_URL . 'assets/css/admin/blocked-dates.css',
			array( 'flatpickr' ),
			MHM_RENTIVA_VERSION
		);
		wp_enqueue_script(
			'flatpickr',
			MHM_RENTIVA_PLUGIN_URL . 'assets/vendor/flatpickr/flatpickr.min.js',
			array(),
			'4.6.13',
			true
		);
		wp_enqueue_script(
			'mhm-blocked-dates',
			MHM_RENTIVA_PLUGIN_URL . 'assets/js/admin/blocked-dates.js',
			array( 'jquery', 'flatpickr' ),
			MHM_RENTIVA_VERSION,
			true
		);
		wp_localize_script( 'mhm-blocked-dates', 'mhmBlockedDatesL10n', array(
			'confirmApply'    => __( 'All blocked dates selected for this vehicle will be applied to all other vehicles, overwriting their existing blocked dates. Do you want to continue?', 'mhm-rentiva' ),
			'confirmRemove'   => __( 'All blocked dates selected for this vehicle will be removed from all other vehicles. Do you want to continue?', 'mhm-rentiva' ),
			/* translators: %d: number of vehicles */
			'appliedTo'       => __( 'Applied to %d vehicles.', 'mhm-rentiva' ),
			/* translators: %d: number of vehicles */
			'removedFrom'     => __( 'Removed from %d vehicles.', 'mhm-rentiva' ),
			'error'           => __( 'An error occurred.', 'mhm-rentiva' ),
			'notePlaceholder' => __( 'Add note... (optional)', 'mhm-rentiva' ),
		) );
	}
}
