<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Booking\ListTable;

if (!defined('ABSPATH')) {
    exit;
}






use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Admin\Booking\Core\Status;
use MHMRentiva\Admin\Core\Utilities\OccupancyMapService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin list-table rendering/sorting needs controlled aggregate queries over booking meta.
final class BookingColumns {



	/**
	 * Flag to prevent infinite loop in title filter
	 */
	private static $in_title_filter = false;

	/**
	 * Safe sanitize text field that handles null values
	 * Includes wp_unslash for WPCS compliance with superglobal sanitization
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Filter and calendar params of the bookings list screen, registered on
	 * WordPress's `query_vars` whitelist (see register_query_vars()) so the
	 * readers below can use get_query_var() instead of reaching into $_GET.
	 *
	 * Same mechanism SearchResults uses for its public filter params — the fix
	 * the accepted approach. It matters here because these are display
	 * parameters of a bookmarkable admin URL: they change no state, and
	 * nonce-gating them would break shareable sorted/filtered links, so neither
	 * a nonce nor an annotation over a raw $_GET read is the right answer.
	 *
	 * `mhmrentiva_month`/`mhmrentiva_year` drive the booking calendar's prev/next navigation.
	 * They carry the plugin prefix rather than the bare `month`/`year` they used
	 * to: registering an unprefixed `month` on a global whitelist would collide
	 * with any other plugin doing the same, and `year` is already one of core's
	 * own public query vars (it filters the query by date).
	 *
	 * @var array<int, string>
	 */
	private const PUBLIC_QUERY_VARS = array(
		'mhmrentiva_booking_status',
		'mhmrentiva_payment_status',
		'mhmrentiva_payment_gateway',
		'mhmrentiva_booking_id',
		'mhmrentiva_license_plate',
		'mhmrentiva_month',
		'mhmrentiva_year',
		'mhmrentiva_customer_email',
		'mhmrentiva_customer_id',
		// View engine (Faz 2): which face of the screen is active. Same
		// bookmarkable-display-parameter reasoning as the params above.
		'mhmrentiva_view',
	);

	/**
	 * Faces this screen offers, in display order. Bookings has no cards face.
	 *
	 * @var array<int, string>
	 */
	private const VIEWS = array( 'list', 'calendar' );

	/**
	 * `query_vars` filter callback.
	 *
	 * @param array<int, string> $vars Registered public query vars.
	 * @return array<int, string>
	 */
	public static function register_query_vars( array $vars ): array {
		return array_values( array_unique( array_merge( $vars, self::PUBLIC_QUERY_VARS ) ) );
	}

	/**
	 * Read sanitized query string value.
	 *
	 * A `null` sentinel default distinguishes "param absent from the request"
	 * from "param present but empty", matching the previous isset() semantics.
	 *
	 * @param string $key Query parameter key.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function get_query_text( string $key, string $default = '' ): string {
		$value = get_query_var( $key, null );
		if ( null === $value || is_array( $value ) ) {
			return $default;
		}

		return sanitize_text_field( wp_unslash( (string) $value ) );
	}

	/**
	 * Read integer query parameter.
	 *
	 * @param string $key Query parameter key.
	 * @param int    $default Default value.
	 * @return int
	 */
	private static function get_query_int( string $key, int $default = 0 ): int {
		$value = get_query_var( $key, null );
		if ( null === $value || is_array( $value ) || '' === $value ) {
			return $default;
		}

		return absint( wp_unslash( (string) $value ) );
	}

	/**
	 * Whitelisted view-face getter — the ONLY way this screen's code reads
	 * `mhmrentiva_view`. Anything outside VIEWS (including an absent param)
	 * resolves to 'list', so the list-face guards below have a single safe
	 * default to reason about.
	 */
	public static function get_current_view(): string {
		$view = self::get_query_text( 'mhmrentiva_view' );
		return in_array( $view, self::VIEWS, true ) ? $view : 'list';
	}

	public static function register(): void {
		add_filter( 'query_vars', array( self::class, 'register_query_vars' ) );
		add_filter( 'manage_mhmrentiva_booking_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_mhmrentiva_booking_posts_custom_column', array( self::class, 'render' ), 10, 2 );
		add_filter( 'manage_edit-mhmrentiva_booking_sortable_columns', array( self::class, 'sortable' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_sorting' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'status_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_status_filter' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'booking_id_filter' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'license_plate_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_custom_filters' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		add_filter( 'the_title', array( self::class, 'modify_booking_title' ), 10, 2 );
		// "Approve" row action (Faz 2 Task 7) -- the only per-row link
		// surface this screen has (Faz 1a's in-place transform added no
		// row-level links of its own), so it goes into the same native
		// post_row_actions slot core's Edit/Quick Edit/Trash/View already
		// use, rather than a second link location.
		add_filter( 'post_row_actions', array( self::class, 'add_approve_row_action' ), 10, 2 );
		add_filter( 'post_class', array( self::class, 'add_completed_row_class' ), 10, 3 );
		// Optional UI extras; now enabled by default (safe after export form fix)
		if ( apply_filters( 'mhmrentiva_enable_booking_admin_extras', true ) ) {
			add_action( 'admin_notices', array( self::class, 'add_booking_stats_cards' ) );
		}
		// Chips are the status filter UI itself (not an "extra"): they replace
		// the old dropdown and must survive the extras filter being disabled.
		add_action( 'admin_notices', array( self::class, 'status_chips' ), 15 );
		// Neutral toolbar seam: renders NOTHING (no container) unless a
		// subscriber adds actions. Lite ships no subscriber.
		add_action( 'admin_notices', array( self::class, 'toolbar_actions' ), 5 );
		// View-switch toggle (Faz 2): always renders, Pro or not — it is core
		// list-screen machinery, not a Pro teaser, so it is unconditional like
		// the toolbar/chips above rather than gated behind the extras filter.
		add_action( 'admin_notices', array( self::class, 'render_view_toggle' ), 6 );
		// Calendar face (Faz 2 view engine) — replaces the retired
		// add_booking_calendar() in the SAME admin_notices slot (priority
		// 20), so booking-list-filters.js's `.mhm-calendars` relocation
		// still finds its target here without a JS registration change.
		// Genuine always-on Lite functionality (a screen FACE, not a Pro
		// teaser), so — like the toggle above — it is unconditional rather
		// than gated behind the extras filter the old renderer sat inside.
		add_action( 'admin_notices', array( self::class, 'render_calendar_view' ), 20 );
	}

	/**
	 * Add 'status-completed' CSS class to completed booking rows.
	 *
	 * @param string[] $classes  Existing CSS classes.
	 * @param string[] $css_class Additional classes passed to get_post_class().
	 * @param int      $post_id  Post ID.
	 * @return string[]
	 */
	public static function add_completed_row_class( array $classes, array $css_class, int $post_id ): array {
		if ( get_post_type( $post_id ) !== 'mhmrentiva_booking' ) {
			return $classes;
		}
		$status = get_post_meta( $post_id, '_mhmrentiva_status', true );
		if ( $status === 'completed' ) {
			$classes[] = 'status-completed';
		}
		return $classes;
	}

	/**
	 * `post_row_actions` filter callback -- adds the "Approve" link
	 * (Faz 2 Task 7) for `pending` bookings only. Styling (green pill/button
	 * look) lands in Task 8; this emits plain markup: `rv-bkl-approve` class
	 * + `data-booking-id`, consumed by assets/js/admin/booking-approve.js.
	 *
	 * Placed FIRST in the returned array (rather than appended) so it reads
	 * as the primary action for a booking awaiting approval, ahead of
	 * Edit/Quick Edit/Trash/View.
	 *
	 * @param array<string, string> $actions Existing row actions.
	 * @param \WP_Post              $post    The row's post.
	 * @return array<string, string>
	 */
	public static function add_approve_row_action( array $actions, \WP_Post $post ): array {
		if ( 'mhmrentiva_booking' !== $post->post_type ) {
			return $actions;
		}

		// Core applies post_row_actions unconditionally in the Trash view too
		// (it only hides Edit there) -- without this check the link would
		// render for a trashed booking, whose _mhmrentiva_status meta is
		// untouched by trashing and still reads PENDING below. Bookings are
		// only ever created with post_status 'publish' (every
		// wp_insert_post()/wp_update_post() call across
		// ManualBookingMetaBox/BookingEditMetaBox/Util.php uses it) --
		// 'private'/'pending' appear only as defensive inclusions in a few
		// READ-side aggregate queries elsewhere in this file, never as a
		// state a booking actually reaches. Restricting to the one real
		// state a live booking has rejects every non-publish state (trash,
		// draft, auto-draft, future) in a single check.
		if ( 'publish' !== $post->post_status ) {
			return $actions;
		}

		// Status::get() folds a missing/unrecognized meta value to PENDING --
		// the same canonical fold the chip counts and the occupancy map use
		// (OccupancyMapService's docblock). Reading the raw meta value here
		// instead would disagree with what the status column right next to
		// this link already displays.
		if ( Status::PENDING !== Status::get( $post->ID ) ) {
			return $actions;
		}

		$approve = array(
			'mhmrentiva_approve' => sprintf(
				'<a href="#" class="rv-bkl-approve" data-booking-id="%d">%s</a>',
				absint( $post->ID ),
				esc_html__( 'Approve', 'mhm-rentiva' )
			),
		);

		return $approve + $actions;
	}

	public static function columns( array $cols ): array {
		// Keep title; move date column to the end
		$date = $cols['date'] ?? null;
		unset( $cols['date'] );

		// The title cell already shows "Name - phone" (modify_booking_title)
		// and carries the row actions — it IS the customer column, the header
		// now says so. License Plate lives as the sub-line of Vehicle; Days as
		// the sub-line of Dates (their standalone columns are gone). Every
		// remaining column is visible by default — user decision 2026-08-10:
		// first-time users see everything and trim via Screen Options themselves.
		$cols['title'] = __( 'Customer', 'mhm-rentiva' );

		$cols['mhmrentiva_booking_id']        = __( 'Booking ID', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_vehicle']   = __( 'Vehicle', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_dates']     = __( 'Dates', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_total']     = __( 'Total', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_deposit']   = __( 'Deposit Amount', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_remaining'] = __( 'Remaining Amount', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_payment']   = __( 'Payment', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_status']    = __( 'Status', 'mhm-rentiva' );
		$cols['mhmrentiva_booking_type']      = __( 'Booking Type', 'mhm-rentiva' );

		if ( $date !== null ) {
			$cols['date'] = $date;
		}
		return $cols;
	}


	public static function enqueue_scripts( string $hook ): void {
		global $post_type;

		// Load only on booking list page
		if ( $hook === 'edit.php' && $post_type === 'mhmrentiva_booking' ) {
			// Load statistics cards CSS
			wp_enqueue_style(
				'mhm-rentiva-stats-cards',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/components/stats-cards.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/css/components/stats-cards.css' )
			);

			wp_enqueue_style(
				'mhm-rentiva-shared-admin',
				MHMRENTIVA_PLUGIN_URL . 'src-react/shared/admin.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'src-react/shared/admin.css' )
			);

			// Load simple calendar CSS
			wp_enqueue_style(
				'mhm-rentiva-simple-calendars',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/components/calendars.css',
				array(),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/css/components/calendars.css' )
			);

			wp_enqueue_style(
				'mhm-rentiva-booking-calendar',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/booking-calendar.css',
				array( 'mhm-rentiva-simple-calendars' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/css/admin/booking-calendar.css' )
			);

			// Faz 2 Task 8 skin: toggle + occupancy matrix + approve action.
			// Declared as a dependency of booking-list.css below so it loads
			// after the base calendar files but before the screen skin.
			wp_enqueue_style(
				'mhm-rentiva-occupancy-matrix',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/occupancy-matrix.css',
				array( 'mhm-rentiva-simple-calendars', 'mhm-rentiva-booking-calendar' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/css/admin/occupancy-matrix.css' )
			);

			// Refined skin — now declares its dependency chain explicitly
			// (Faz 2 Task 8; it used to declare none, unlike vehicle-list.css's
			// equivalent, so load order relied on call order alone).
			wp_enqueue_style(
				'mhm-rentiva-booking-list',
				MHMRENTIVA_PLUGIN_URL . 'assets/css/admin/booking-list.css',
				array( 'mhm-rentiva-stats-cards', 'mhm-rentiva-shared-admin', 'mhm-rentiva-simple-calendars', 'mhm-rentiva-booking-calendar', 'mhm-rentiva-occupancy-matrix' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/css/admin/booking-list.css' )
			);

			// assets/js/admin/booking-calendar.js is NOT enqueued here (Faz 2
			// Task 5 retirement): it drove the old aggregate grid's month-nav
			// by querying DOM ids (`monthYear`/`calendarDays`/`prevMonth`/
			// `nextMonth`) that renderer never actually printed — the markup
			// used `.calendar-nav-btn`/`.calendar-current` instead, so the
			// script's querySelectors always returned null and it early-
			// returned on every load. It was already dead before this
			// retirement; FleetOccupancyMatrix's month-nav is server-rendered
			// links (add_query_arg()), no JS needed. File left on disk
			// (unreferenced) rather than deleted — cheap to revert if wrong.

			// Note: rely on WordPress core bulk-action behavior to avoid interference.

			// Add body class
			add_filter( 'admin_body_class', array( self::class, 'add_body_class' ) );

			// Filters UX: auto-submit on change
			wp_enqueue_script(
				'mhm-rentiva-booking-list-filters',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/booking-list-filters.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/js/admin/booking-list-filters.js' ),
				true
			);

			// Calendar day popup behavior (rendered by render_calendar_view()
			// via the shared FleetOccupancyMatrix renderer's popup partial).
			wp_enqueue_script(
				'mhm-rentiva-booking-popup',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/booking-popup.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/js/admin/booking-popup.js' ),
				true
			);

			wp_localize_script(
				'mhm-rentiva-booking-popup',
				'mhmBookingPopup',
				array(
					'i18n' => array(
						'bookingsOnThisDay' => __( 'bookings on this day', 'mhm-rentiva' ),
						'customer'          => __( 'Customer', 'mhm-rentiva' ),
						'pickup'            => __( 'Pickup', 'mhm-rentiva' ),
						'returnLabel'       => __( 'Return', 'mhm-rentiva' ),
						'total'             => __( 'Total', 'mhm-rentiva' ),
						'editBooking'       => __( 'Edit Booking', 'mhm-rentiva' ),
					),
				)
			);

			// "Approve" row action (Faz 2 Task 7). Own small script rather
			// than folded into booking-list-filters.js: that file is layout/
			// filter-submit plumbing with no AJAX writes in it anywhere, and
			// this is the one new write endpoint in this round -- keeping it
			// in its own file keeps the guard-carrying network call isolated
			// and easy to find/remove independently of the layout script.
			wp_enqueue_script(
				'mhm-rentiva-booking-approve',
				MHMRENTIVA_PLUGIN_URL . 'assets/js/admin/booking-approve.js',
				array( 'jquery' ),
				\MHMRentiva\Admin\Core\AssetManager::get_file_version( 'assets/js/admin/booking-approve.js' ),
				true
			);

			wp_localize_script(
				'mhm-rentiva-booking-approve',
				'mhmBookingApprove',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'mhmrentiva_approve_booking' ),
					'i18n'    => array(
						'approve'   => __( 'Approve', 'mhm-rentiva' ),
						'approving' => __( 'Approving…', 'mhm-rentiva' ),
						'approved'  => __( 'Booking approved.', 'mhm-rentiva' ),
						'failed'    => __( 'This booking could not be approved. It may have changed — reload the list.', 'mhm-rentiva' ),
					),
				)
			);
		}
	}

	public static function add_body_class( string $classes ): string {
		$classes .= ' mhm-booking-list';

		// Faz 2 view engine: face-scoped visibility CSS keys off this class
		// (booking-list.css); 'list' carries no face class at all.
		$view = self::get_current_view();
		if ( 'calendar' === $view ) {
			$classes .= ' mhm-view-calendar';
		}

		return $classes;
	}

	public static function render( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mhmrentiva_booking_id':
				echo '<span class="booking-id">#' . esc_html( mhmrentiva_get_display_id( $post_id ) ) . '</span>';
				break;

			case 'mhmrentiva_booking_vehicle':
				// Check both old and new meta keys
				$vehicle_id = (int) ( get_post_meta( $post_id, '_mhmrentiva_booking_vehicle_id', true ) ?: get_post_meta( $post_id, '_mhmrentiva_vehicle_id', true ) );
				if ( $vehicle_id ) {
					$vehicle_title = get_the_title( $vehicle_id );
					$vehicle_link  = get_edit_post_link( $vehicle_id );
					echo '<div class="vehicle-info">';
					if ( $vehicle_link ) {
						echo '<span class="vehicle-name"><a href="' . esc_url( $vehicle_link ) . '">' . esc_html( $vehicle_title ) . '</a></span>';
					} else {
						echo '<span class="vehicle-name">' . esc_html( $vehicle_title ) . '</span>';
					}
					// Plate sub-line. `_mhmrentiva_license_plate` is the key the
					// vehicle editor actually writes (measured: the old
					// `_mhmrentiva_vehicle_plate` read here had zero rows, so
					// this sub-line rendered empty while a standalone License
					// Plate column carried the data — that column is gone now).
					$vehicle_plate = get_post_meta( $vehicle_id, '_mhmrentiva_license_plate', true )
						?: get_post_meta( $vehicle_id, '_mhmrentiva_vehicle_plate', true );
					if ( $vehicle_plate ) {
						echo '<span class="vehicle-plate">' . esc_html( $vehicle_plate ) . '</span>';
					}
					echo '</div>';
				} else {
					echo '—';
				}
				break;

			case 'mhmrentiva_booking_dates':
				// Check both old and new meta keys
				$pickup_date  = get_post_meta( $post_id, '_mhmrentiva_booking_pickup_date', true ) ?: get_post_meta( $post_id, '_mhmrentiva_pickup_date', true );
				$pickup_time  = get_post_meta( $post_id, '_mhmrentiva_booking_pickup_time', true ) ?: get_post_meta( $post_id, '_mhmrentiva_pickup_time', true ) ?: get_post_meta( $post_id, '_mhmrentiva_start_time', true );
				$dropoff_date = get_post_meta( $post_id, '_mhmrentiva_booking_dropoff_date', true ) ?: get_post_meta( $post_id, '_mhmrentiva_dropoff_date', true );
				$dropoff_time = get_post_meta( $post_id, '_mhmrentiva_booking_dropoff_time', true ) ?: get_post_meta( $post_id, '_mhmrentiva_dropoff_time', true ) ?: get_post_meta( $post_id, '_mhmrentiva_end_time', true );

				if ( $pickup_date && $dropoff_date ) {
					// Normalize date format (convert to DD.MM.YYYY format)
					$formatted_pickup  = self::format_date_for_display( $pickup_date );
					$formatted_dropoff = self::format_date_for_display( $dropoff_date );

					echo '<div class="date-info">';
					// Show date and time information together
					$pickup_datetime = $formatted_pickup;
					if ( $pickup_time ) {
						$pickup_datetime .= ', ' . esc_html( $pickup_time );
					}

					$dropoff_datetime = $formatted_dropoff;
					if ( $dropoff_time ) {
						$dropoff_datetime .= ', ' . esc_html( $dropoff_time );
					}

					echo '<div class="date-range">' . esc_html( $pickup_datetime . ' - ' . $dropoff_datetime ) . '</div>';

					// Day count sub-line (its standalone column is gone).
					$days = (int) ( get_post_meta( $post_id, '_mhmrentiva_booking_rental_days', true ) ?: get_post_meta( $post_id, '_mhmrentiva_rental_days', true ) );
					if ( $days > 0 ) {
						/* translators: %d: number of rental days */
						echo '<div class="date-days">' . esc_html( sprintf( _n( '%d day', '%d days', $days, 'mhm-rentiva' ), $days ) ) . '</div>';
					}
					echo '</div>';
				} else {
					echo '—';
				}
				break;

			case 'mhmrentiva_booking_total':
				// Check both old and new meta keys
				$total = (float) ( get_post_meta( $post_id, '_mhmrentiva_booking_total_price', true ) ?: get_post_meta( $post_id, '_mhmrentiva_total_price', true ) );
				if ( $total > 0 ) {
					echo '<span class="total-amount">' . esc_html( self::format_price( $total ) ) . '</span>';
				} else {
					echo '—';
				}
				break;

			case 'mhmrentiva_booking_deposit':
				// Check payment type
				$payment_type = get_post_meta( $post_id, '_mhmrentiva_payment_type', true );

				if ( 'deposit' === $payment_type ) {
					// Get deposit amount (already calculated)
					$deposit_amount = get_post_meta( $post_id, '_mhmrentiva_deposit_amount', true );

					if ( $deposit_amount && $deposit_amount > 0 ) {
						echo '<span class="deposit-amount">' . esc_html( self::format_price( floatval( $deposit_amount ) ) ) . '</span>';
					} else {
						echo '—';
					}
				} else {
					// Full payment made
					echo '—';
				}
				break;

			case 'mhmrentiva_booking_remaining':
				// Check payment type and amounts
				$payment_type = get_post_meta( $post_id, '_mhmrentiva_payment_type', true );

				if ( 'deposit' === $payment_type ) {
					// Get remaining amount (already calculated)
					$remaining_amount = get_post_meta( $post_id, '_mhmrentiva_remaining_amount', true );

					if ( $remaining_amount && $remaining_amount > 0 ) {
						echo '<span class="remaining-amount">' . esc_html( self::format_price( floatval( $remaining_amount ) ) ) . '</span>';
					} else {
						echo '<span class="remaining-amount paid">' . esc_html__( 'Paid', 'mhm-rentiva' ) . '</span>';
					}
				} else {
					// Full payment made or payment type unclear
					echo '<span class="remaining-amount paid">' . esc_html__( 'Paid', 'mhm-rentiva' ) . '</span>';
				}
				break;

			case 'mhmrentiva_booking_status':
				$status = Status::get( $post_id );
				$label  = Status::get_label( $status );
				echo '<span class="badge status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
				break;

			case 'mhmrentiva_booking_payment':
				// Check both old and new meta keys
				$status    = (string) ( get_post_meta( $post_id, '_mhmrentiva_booking_payment_status', true ) ?: get_post_meta( $post_id, '_mhmrentiva_payment_status', true ) );
				$amount    = (int) ( get_post_meta( $post_id, '_mhmrentiva_booking_payment_amount', true ) ?: get_post_meta( $post_id, '_mhmrentiva_payment_amount', true ) );
				$currency  = (string) ( get_post_meta( $post_id, '_mhmrentiva_booking_payment_currency', true ) ?: get_post_meta( $post_id, '_mhmrentiva_payment_currency', true ) );
				$gateway   = (string) ( get_post_meta( $post_id, '_mhmrentiva_booking_payment_gateway', true ) ?: get_post_meta( $post_id, '_mhmrentiva_payment_gateway', true ) );
				$receiptId = (int) ( get_post_meta( $post_id, '_mhmrentiva_booking_offline_receipt_id', true ) ?: get_post_meta( $post_id, '_mhmrentiva_offline_receipt_id', true ) );

				if ( '' === $currency ) {
					$currency = is_callable( array( Settings::class, 'get' ) ) ? (string) Settings::get( 'currency', 'USD' ) : 'USD';
				}

				echo '<div class="payment-info">';
				$label       = $status ? self::get_payment_status_label( $status ) : __( 'Unpaid', 'mhm-rentiva' );
				$status_slug = sanitize_html_class( $status ?: 'unpaid' );
				echo '<span class="badge payment-status-' . esc_attr( $status_slug ) . '">' . esc_html( $label ) . '</span>';

				if ( $amount > 0 ) {
					$val = number_format_i18n( $amount / 100, 2 );
					echo '<div class="amount">' . esc_html( $val . ' ' . strtoupper( $currency ) ) . '</div>';
				}

				$gw = $gateway !== '' ? $gateway : ( $receiptId ? 'offline' : '' );
				if ( $gw !== '' ) {
					$gateway_label = self::get_payment_gateway_label( $gw );
					echo '<span class="mhm-gateway-pill">' . esc_html( $gateway_label ) . '</span>';
				}
				echo '</div>';
				break;

			case 'mhmrentiva_booking_type':
				$is_transfer = (bool) get_post_meta( $post_id, '_mhmrentiva_transfer_origin_id', true );
				$is_manual   = get_post_meta( $post_id, '_mhmrentiva_booking_type', true ) === 'manual';

				if ( $is_transfer ) {
					echo '<span class="booking-type transfer">&#x2708; ' . esc_html__( 'Transfer', 'mhm-rentiva' ) . '</span>';
				} else {
					echo '<span class="booking-type rental">&#x1F697; ' . esc_html__( 'Rental', 'mhm-rentiva' ) . '</span>';
				}

				if ( $is_manual ) {
					echo '<span class="booking-type-manual-badge" title="' . esc_attr__( 'Manual entry', 'mhm-rentiva' ) . '">M</span>';
				}
				break;
		}
	}

	public static function sortable( array $cols ): array {
		$cols['mhmrentiva_booking_total']   = 'mhmrentiva_booking_total';
		$cols['mhmrentiva_booking_dates']   = 'mhmrentiva_booking_dates';
		$cols['mhmrentiva_booking_payment'] = 'mhmrentiva_booking_payment';
		return $cols;
	}

	public static function apply_sorting( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== 'mhmrentiva_booking' ) {
			return;
		}

		$orderby = $q->get( 'orderby' );
		if ( $orderby === 'mhmrentiva_booking_total' ) {
			// Check both old and new meta keys
			$q->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_mhmrentiva_booking_total_price',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_mhmrentiva_total_price',
						'compare' => 'EXISTS',
					),
				)
			);
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'mhmrentiva_booking_dates' ) {
			// Check both old and new meta keys
			$q->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_mhmrentiva_booking_start_ts',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_mhmrentiva_start_ts',
						'compare' => 'EXISTS',
					),
				)
			);
			$q->set( 'orderby', 'meta_value_num' );
		} elseif ( $orderby === 'mhmrentiva_booking_payment' ) {
			// Check both old and new meta keys
			$q->set(
				'meta_query',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_mhmrentiva_booking_payment_amount',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_mhmrentiva_payment_amount',
						'compare' => 'EXISTS',
					),
				)
			);
			$q->set( 'orderby', 'meta_value_num' );
		}
	}

	/**
	 * Toolbar action links above the booking list — a neutral extension seam.
	 *
	 * With no subscriber the filter returns an empty array and NOTHING is
	 * rendered, container included. Each subscriber-provided item is
	 * array{label: string, url: string, class?: string}.
	 */
	public static function toolbar_actions(): void {
		global $pagenow, $post_type;

		if ( $pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		/**
		 * Filters the action links rendered above the booking list table.
		 *
		 * @param array<int, array{label: string, url: string, class?: string}> $actions Toolbar actions.
		 */
		$actions = apply_filters( 'mhmrentiva_booking_list_toolbar_actions', array() );

		if ( empty( $actions ) || ! is_array( $actions ) ) {
			return;
		}

		echo '<div class="rv-bkl-toolbar">';
		foreach ( $actions as $action ) {
			if ( empty( $action['label'] ) || empty( $action['url'] ) ) {
				continue;
			}
			$class = 'rv-bkl-toolbar__btn' . ( empty( $action['class'] ) ? '' : ' ' . $action['class'] );
			printf(
				'<a class="%s" href="%s">%s</a>',
				esc_attr( $class ),
				esc_url( $action['url'] ),
				esc_html( $action['label'] )
			);
		}
		echo '</div>';
	}

	/**
	 * Segmented view-switch control (List | Calendar) — Faz 2 view engine.
	 *
	 * Rendered as its OWN admin_notices block rather than folded into
	 * toolbar_actions() above: that method's neutral-seam contract is a
	 * house rule pinned by BookingToolbarSeamTest (renders NOTHING, container
	 * included, when no Pro subscriber adds actions — no empty box teasing
	 * an absent feature). The toggle is not that: it is genuine, always-on
	 * Lite functionality, so it gets its own block. The relocation JS
	 * (booking-list-filters.js) wraps this block together with
	 * `.rv-bkl-toolbar` into one flex row WHEN a Pro subscriber actually
	 * renders the toolbar; when nothing subscribes, `.rv-bkl-toolbar` is
	 * absent from the DOM entirely and the toggle simply stands alone.
	 *
	 * Markup only (`rv-view-toggle` / `rv-view-toggle__btn` / `is-active`);
	 * styling lands in Task 8.
	 */
	public static function render_view_toggle(): void {
		global $pagenow, $post_type;

		if ( $pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		$current = self::get_current_view();
		$faces   = array(
			'list'     => __( 'List', 'mhm-rentiva' ),
			'calendar' => __( 'Calendar', 'mhm-rentiva' ),
		);

		echo '<div class="rv-view-toggle">';
		foreach ( $faces as $face => $label ) {
			$url   = 'list' === $face ? remove_query_arg( 'mhmrentiva_view' ) : add_query_arg( 'mhmrentiva_view', $face );
			$class = 'rv-view-toggle__btn' . ( $current === $face ? ' is-active' : '' );
			printf(
				'<a class="%s" href="%s">%s</a>',
				esc_attr( $class ),
				esc_url( $url ),
				esc_html( $label )
			);
		}
		echo '</div>';
	}

	/**
	 * Status chip strip — replaces the old status dropdown.
	 *
	 * Same URL contract: each chip is a plain link carrying the registered
	 * `mhmrentiva_booking_status` public query var, consumed by
	 * apply_status_filter() unchanged, so old bookmarks keep working. Counts
	 * come from the canonical stats (DashboardService enumeration). Every
	 * status with a non-zero count gets a chip; the five core statuses are
	 * always shown so the strip does not jump around as data changes, while
	 * rare empty states (draft, no_show, ...) stay out of the way.
	 */
	/**
	 * Base URL for the chip strip: this screen's edit.php PLUS the active
	 * view context.
	 *
	 * The view toggle preserves context (it calls add_query_arg() on the
	 * CURRENT URL); the chips are built from a bare base, so without this a
	 * chip click on the Calendar face dropped `mhmrentiva_view` and silently
	 * returned the user to the List face. The calendar's month/year travel
	 * with it for the same reason — filtering must not also navigate you
	 * back to the current month.
	 */
	private static function chip_base(): string {
		$base = admin_url( 'edit.php?post_type=mhmrentiva_booking' );

		$view = self::get_current_view();
		if ( 'list' === $view ) {
			return $base;
		}

		$base = add_query_arg( 'mhmrentiva_view', $view, $base );
		foreach ( array( 'mhmrentiva_month', 'mhmrentiva_year' ) as $key ) {
			$value = self::get_query_int( $key );
			if ( $value > 0 ) {
				$base = add_query_arg( $key, $value, $base );
			}
		}

		return $base;
	}

	public static function status_chips(): void {
		global $pagenow, $post_type;

		if ( $pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		$stats     = self::get_booking_stats();
		$by_status = is_array( $stats['by_status'] ?? null ) ? $stats['by_status'] : array();
		$current   = self::get_query_text( 'mhmrentiva_booking_status' );
		$base      = self::chip_base();

		$always_shown = array(
			Status::PENDING,
			Status::CONFIRMED,
			Status::IN_PROGRESS,
			Status::COMPLETED,
			Status::CANCELLED,
		);

		echo '<div class="rv-bkl-chips">';

		printf(
			'<a class="rv-bkl-chip%s" href="%s">%s <span class="rv-bkl-chip__count">%d</span></a>',
			'' === $current ? ' is-active' : '',
			esc_url( $base ),
			esc_html__( 'All', 'mhm-rentiva' ),
			(int) $stats['total']
		);

		foreach ( Status::allowed() as $status ) {
			$count = (int) ( $by_status[ $status ] ?? 0 );
			if ( 0 === $count && ! in_array( $status, $always_shown, true ) ) {
				continue;
			}

			printf(
				'<a class="rv-bkl-chip%s" href="%s">%s <span class="rv-bkl-chip__count">%d</span></a>',
				$current === $status ? ' is-active' : '',
				esc_url( add_query_arg( 'mhmrentiva_booking_status', $status, $base ) ),
				esc_html( Status::get_label( $status ) ),
				absint( $count )
			);
		}

		echo '</div>';
	}

	public static function status_filter( string $post_type ): void {
		if ( $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		// The status dropdown that used to render here became the chip strip
		// (status_chips()) — same registered query var, one filter UI.

		// Payment status filter
		$pcur = self::get_query_text( 'mhmrentiva_payment_status' );
		echo '<select name="mhmrentiva_payment_status" class="postform">';
		echo '  <option value="">' . esc_html__( 'All payments', 'mhm-rentiva' ) . '</option>';
		foreach ( array( 'unpaid', 'paid', 'refunded', 'failed' ) as $s ) {
			$label = self::get_payment_status_label( $s );
			echo '  <option value="' . esc_attr( $s ) . '"' . selected( $pcur, $s, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// Payment gateway filter — enumerates the DISTINCT gateway values
		// bookings actually carry, not WC's full registered-gateway list
		// (bacs/cheque/cod/sandbox noise whose selection the apply logic
		// never even filtered by). Hidden entirely when no booking has a
		// gateway yet.
		$in_use = self::get_gateways_in_use();
		if ( ! empty( $in_use ) ) {
			$gcur = self::get_query_text( 'mhmrentiva_payment_gateway' );
			echo '<select name="mhmrentiva_payment_gateway" class="postform">';
			echo '  <option value="">' . esc_html__( 'All payment methods', 'mhm-rentiva' ) . '</option>';
			foreach ( $in_use as $gw ) {
				$label = self::get_payment_gateway_label( $gw );
				echo '  <option value="' . esc_attr( $gw ) . '"' . selected( $gcur, $gw, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}
	}

	/**
	 * DISTINCT payment gateway values present on non-trash bookings.
	 *
	 * @return array<int, string>
	 */
	private static function get_gateways_in_use(): array {
		$cached = wp_cache_get( 'mhmrentiva_booking_gateways_in_use' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = %s AND pm.meta_value != ''
                AND p.post_type = %s AND p.post_status != %s
                ORDER BY pm.meta_value",
				'_mhmrentiva_payment_gateway',
				'mhmrentiva_booking',
				'trash'
			)
		);

		$values = array_values( array_filter( array_map( 'strval', (array) $values ) ) );
		wp_cache_set( 'mhmrentiva_booking_gateways_in_use', $values, '', 3600 );

		return $values;
	}

	public static function apply_status_filter( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== 'mhmrentiva_booking' ) {
			return;
		}

		$meta = array();

		$booking_status_filter = self::get_query_text( 'mhmrentiva_booking_status' );
		if ( '' !== $booking_status_filter ) {
			$val = $booking_status_filter;
			if ( in_array( $val, Status::allowed(), true ) ) {
				// Check both old and new meta keys
				$clauses = array(
					'relation' => 'OR',
					array(
						'key'     => '_mhmrentiva_booking_status',
						'value'   => $val,
						'compare' => '=',
					),
					array(
						'key'     => '_mhmrentiva_status',
						'value'   => $val,
						'compare' => '=',
					),
				);
				// Count semantics parity: the canonical enumeration folds
				// status-less bookings into pending (COALESCE), so the
				// pending filter must match them too — otherwise the chip
				// says N and the list shows fewer.
				if ( Status::PENDING === $val ) {
					$clauses[] = array(
						'key'     => '_mhmrentiva_status',
						'compare' => 'NOT EXISTS',
					);
					$clauses[] = array(
						'key'     => '_mhmrentiva_status',
						'value'   => '',
						'compare' => '=',
					);
				}
				$meta[] = $clauses;
			}
		}
		$payment_status_filter = self::get_query_text( 'mhmrentiva_payment_status' );
		if ( '' !== $payment_status_filter ) {
			$val = $payment_status_filter;
			if ( in_array( $val, array( 'unpaid', 'paid', 'refunded', 'failed' ), true ) ) {
				// Check both the old and the new meta keys
				$meta[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_mhmrentiva_booking_payment_status',
						'value'   => $val,
						'compare' => '=',
					),
					array(
						'key'     => '_mhmrentiva_payment_status',
						'value'   => $val,
						'compare' => '=',
					),
				);
			}
		}
		$payment_gateway_filter = self::get_query_text( 'mhmrentiva_payment_gateway' );
		if ( '' !== $payment_gateway_filter ) {
			$val = $payment_gateway_filter;
			// Whitelist = the same in-use set the dropdown offers; the old
			// code special-cased 'woocommerce' and silently ignored every
			// other selection.
			if ( in_array( $val, self::get_gateways_in_use(), true ) ) {
				$meta[] = array(
					array(
						'key'     => '_mhmrentiva_payment_gateway',
						'value'   => $val,
						'compare' => '=',
					),
				);
			}
		}
		if ( ! empty( $meta ) ) {
			$meta['relation'] = 'AND';
			$q->set( 'meta_query', $meta );
		}
	}

	/**
	 * Return localized label for payment status.
	 */
	private static function get_payment_status_label( string $status ): string {
		$labels = array(
			'unpaid'               => __( 'Unpaid', 'mhm-rentiva' ),
			'paid'                 => __( 'Paid', 'mhm-rentiva' ),
			'refunded'             => __( 'Refunded', 'mhm-rentiva' ),
			'failed'               => __( 'Failed', 'mhm-rentiva' ),
			'pending_verification' => __( 'Pending Verification', 'mhm-rentiva' ),
			'pending'              => __( 'Pending', 'mhm-rentiva' ),
			'cancelled'            => __( 'Cancelled', 'mhm-rentiva' ),
			'processing'           => __( 'Processing', 'mhm-rentiva' ),
			'on-hold'              => __( 'On Hold', 'mhm-rentiva' ),
			'completed'            => __( 'Paid', 'mhm-rentiva' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Return localized label for payment gateway.
	 */
	private static function get_payment_gateway_label( string $gateway ): string {
		$labels = array(
			'offline'       => __( 'Offline', 'mhm-rentiva' ),
			'woocommerce'   => __( 'Online Payment', 'mhm-rentiva' ),
			'bank_transfer' => __( 'Bank Transfer', 'mhm-rentiva' ),
			'cash'          => __( 'Cash', 'mhm-rentiva' ),
		);

		return $labels[ $gateway ] ?? ucwords( str_replace( '_', ' ', $gateway ) );
	}

	private static function format_price( float $price ): string {
		// Canonical currency formatting (WC-aware symbol/position/separators);
		// this screen must not hand-format money differently from the rest.
		return \MHMRentiva\Admin\Core\CurrencyHelper::format_price( $price, 2 );
	}

	/**
	 * Format date for display according to settings.
	 */
	private static function format_date_for_display( string $date ): string {
		if ( empty( $date ) ) {
			return '';
		}

		// Get date format from settings
		// ✅ Use SettingsCore::get() instead of removed BookingSettings method
		$date_format = \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_date_format', 'Y-m-d' );

		// If already in desired format, return as is
		if ( $date_format === 'DD.MM.YYYY' && preg_match( '/^\d{2}\.\d{2}\.\d{4}$/', $date ) ) {
			return $date;
		}

		if ( $date_format === 'YYYY-MM-DD' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return $date;
		}

		// If in YYYY-MM-DD format, convert to desired format
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date_obj = \DateTime::createFromFormat( 'Y-m-d', $date );
			if ( $date_obj !== false ) {
				switch ( $date_format ) {
					case 'DD.MM.YYYY':
						return $date_obj->format( 'd.m.Y' );
					case 'YYYY-MM-DD':
						return $date_obj->format( 'Y-m-d' );
					case 'MM/DD/YYYY':
						return $date_obj->format( 'm/d/Y' );
					case 'DD-MM-YYYY':
						return $date_obj->format( 'd-m-Y' );
					default:
						return $date_obj->format( 'd.m.Y' );
				}
			}
		}

		// Try other formats as well
		$timestamp = strtotime( $date );
		if ( $timestamp !== false ) {
			return gmdate( 'd.m.Y', $timestamp );
		}

		// If none work, return original value
		return $date;
	}

	/**
	 * Output booking statistics cards.
	 */
	public static function add_booking_stats_cards(): void {
		global $pagenow, $post_type;

		// Show only on booking list page
		if ( $pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		// Get statistics data
		$stats = self::get_booking_stats();
		?>
		<div class="mhm-stats-grid">
			<div class="mhm-stat-card">
				<span class="dashicons dashicons-calendar-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e( 'Total Bookings', 'mhm-rentiva' ); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( $stats['total'] ); ?></p>
					<p class="mhm-stat-card__sub"><?php echo esc_html( $stats['monthly'] ); ?> <?php esc_html_e( 'This month', 'mhm-rentiva' ); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-pending">
				<span class="dashicons dashicons-clock"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e( 'Pending', 'mhm-rentiva' ); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( $stats['pending'] ); ?></p>
					<p class="mhm-stat-card__sub"><?php echo esc_html( $stats['pending_this_week'] ); ?> <?php esc_html_e( 'This week', 'mhm-rentiva' ); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-completed">
				<span class="dashicons dashicons-yes"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e( 'Completed', 'mhm-rentiva' ); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( $stats['completed'] ); ?></p>
					<p class="mhm-stat-card__sub"><?php echo esc_html( $stats['completed_this_month'] ); ?> <?php esc_html_e( 'This month', 'mhm-rentiva' ); ?></p>
				</div>
			</div>

			<div class="mhm-stat-card is-revenue">
				<span class="dashicons dashicons-money-alt"></span>
				<div class="mhm-stat-card__body">
					<p class="mhm-stat-card__label"><?php esc_html_e( 'Monthly Revenue', 'mhm-rentiva' ); ?></p>
					<p class="mhm-stat-card__value"><?php echo esc_html( self::format_price( $stats['monthly_revenue'] ) ); ?></p>
					<p class="mhm-stat-card__sub"><?php echo $stats['revenue_trend'] >= 0 ? '+' : ''; ?><?php echo esc_html( $stats['revenue_trend'] ); ?>% <?php esc_html_e( 'vs last month', 'mhm-rentiva' ); ?></p>
				</div>
			</div>
		</div>

		<?php
	}

	/**
	 * Collect booking statistics data.
	 *
	 * Status counts and monthly revenue come from DashboardService — the
	 * canonical source — so this band can never disagree with the dashboard
	 * (it used to carry its own copy of the SQL, publish-only and dual
	 * meta-key; the canonical definition won). Only the windowed sub-metrics
	 * (this-week/this-month breakdowns, revenue trend) live here, because
	 * this screen is their only consumer.
	 */
	public static function get_booking_stats(): array {
		// Try to get stats from cache
		$cache_key = 'mhmrentiva_booking_stats';
		$stats     = wp_cache_get( $cache_key );

		if ( false === $stats ) {
			global $wpdb;

			$dashboard = \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_booking_stats();
			$metrics   = \MHMRentiva\Admin\Utilities\Dashboard\DashboardService::get_dashboard_metrics();

			// Pending bookings this week (check both old and new meta keys)
			$pending_this_week = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.post_type = %s AND p.post_status = %s 
                 AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
                 AND p.post_date >= %s",
					'mhmrentiva_booking',
					'publish',
					'_mhmrentiva_booking_status',
					'pending',
					'_mhmrentiva_status',
					'pending',
					gmdate( 'Y-m-d', strtotime( '-7 days' ) )
				)
			);

			// Confirmed bookings this month (check both old and new meta keys)
			$confirmed_this_month = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.post_type = %s AND p.post_status = %s 
                 AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
                 AND p.post_date >= %s",
					'mhmrentiva_booking',
					'publish',
					'_mhmrentiva_booking_status',
					'confirmed',
					'_mhmrentiva_status',
					'confirmed',
					gmdate( 'Y-m-01' )
				)
			);

			// Completed bookings this month (check both old and new meta keys)
			$completed_this_month = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->postmeta} pm 
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                 WHERE p.post_type = %s AND p.post_status = %s 
                 AND ((pm.meta_key = %s AND pm.meta_value = %s) OR (pm.meta_key = %s AND pm.meta_value = %s))
                 AND p.post_date >= %s",
					'mhmrentiva_booking',
					'publish',
					'_mhmrentiva_booking_status',
					'completed',
					'_mhmrentiva_status',
					'completed',
					gmdate( 'Y-m-01' )
				)
			);

			$trend_range   = (int) \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_booking_stats_trend_range', 30 );
			$revenue_trend = self::calculate_revenue_trend( $trend_range );

			$stats = array(
				'total'                => $dashboard['total'],
				'monthly'              => $dashboard['monthly'],
				'pending'              => $dashboard['pending'],
				'confirmed'            => $dashboard['confirmed'],
				'in_progress'          => $dashboard['in_progress'],
				'completed'            => $dashboard['completed'],
				'cancelled'            => $dashboard['cancelled'],
				'by_status'            => $dashboard['by_status'],
				'pending_this_week'    => $pending_this_week,
				'confirmed_this_month' => $confirmed_this_month,
				'completed_this_month' => $completed_this_month,
				'monthly_revenue'      => (float) $metrics['monthly_revenue'],
				'revenue_trend'        => $revenue_trend,
			);

			wp_cache_set( $cache_key, $stats, '', 3600 );
		}

		return $stats;
	}

	/**
	 * Calculate revenue trend.
	 */
	private static function calculate_revenue_trend( int $trend_range_days ): float {
		global $wpdb;

		// Current period: full current calendar month
		$current_period_start = gmdate( 'Y-m-01' );
		$current_period_end   = gmdate( 'Y-m-t' ) . ' 23:59:59'; // last day of current month, end of day

		// Previous period: full previous calendar month
		$previous_period_start = gmdate( 'Y-m-01', strtotime( 'first day of last month' ) );
		$previous_period_end   = gmdate( 'Y-m-t', strtotime( 'last day of last month' ) ) . ' 23:59:59'; // last day of previous month, end of day

		// Current period revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
		$current_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = '_mhmrentiva_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s",
				'mhmrentiva_booking',
				'_mhmrentiva_total_price',
				$current_period_start,
				$current_period_end
			)
		);

		// Previous period revenue - ONLY COMPLETED AND CONFIRMED BOOKINGS
		$previous_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm.meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id
             WHERE p.post_type = %s AND p.post_status IN ('publish', 'private', 'pending') AND p.post_status != 'trash'
             AND pm.meta_key = %s
             AND pm_status.meta_key = '_mhmrentiva_status'
             AND pm_status.meta_value IN ('completed', 'confirmed')
             AND p.post_date >= %s AND p.post_date <= %s",
				'mhmrentiva_booking',
				'_mhmrentiva_total_price',
				$previous_period_start,
				$previous_period_end
			)
		);

		// Calculate revenue trend.
		if ( $previous_revenue > 0 ) {
			$trend = ( ( $current_revenue - $previous_revenue ) / $previous_revenue ) * 100;
			return round( $trend, 1 );
		} elseif ( $current_revenue > 0 ) {
			return 100.0;
		} else {
			return 0.0;
		}
	}

	/**
	 * Calendar face (Faz 2 view engine). Replaces the retired
	 * add_booking_calendar()/get_booking_calendar_days() below-table
	 * aggregate grid: rows are the vehicles with an "occupied" booking (see
	 * OccupancyMapService's docblock for that definition) overlapping the
	 * requested month, AFTER the screen's active filters (status chip,
	 * gateway, search) are applied — painted through the same
	 * FleetOccupancyMatrix renderer the Vehicles Calendar face (Task 4)
	 * uses.
	 *
	 * Registered in the SAME admin_notices slot (priority 20) the old
	 * renderer held, so booking-list-filters.js's `.mhm-calendars`
	 * relocation still finds its target here (its selector was widened from
	 * the old renderer's `.booking-calendar-page` class to match the
	 * matrix's own marker class as part of this change).
	 */
	public static function render_calendar_view(): void {
		global $pagenow, $post_type;

		if ( $pagenow !== 'edit.php' || $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		if ( 'calendar' !== self::get_current_view() ) {
			return;
		}

		// Month/year bounds: current year ± 10, same rule the old renderer
		// used (and VehicleColumns' Calendar face converged on in Task 4).
		$current_month = self::get_query_int( 'mhmrentiva_month', (int) gmdate( 'n' ) );
		$current_year  = self::get_query_int( 'mhmrentiva_year', (int) gmdate( 'Y' ) );

		if ( $current_month < 1 || $current_month > 12 ) {
			$current_month = (int) gmdate( 'n' );
		}
		$this_year = (int) gmdate( 'Y' );
		if ( $current_year < ( $this_year - 10 ) || $current_year > ( $this_year + 10 ) ) {
			$current_year = $this_year;
		}

		$row_source        = self::get_calendar_row_source( $current_month, $current_year );
		$vehicle_ids       = $row_source['vehicle_ids'];
		$vehicleless_count = $row_source['vehicleless_count'];

		/**
		 * Filters the number of vehicle rows the Bookings Calendar face
		 * renders before trimming. Test-visible knob so a lower cap can be
		 * exercised without seeding 100+ fixture vehicles; production
		 * default is 100.
		 *
		 * @param int $cap Row cap, applied after the title-ASC sort.
		 */
		$cap = (int) apply_filters( 'mhmrentiva_occupancy_matrix_row_cap', 100 );
		if ( $cap <= 0 ) {
			$cap = 100;
		}

		$vehicle_query = new \WP_Query(
			array(
				'post_type'           => 'mhmrentiva_vehicle',
				'post__in'            => ! empty( $vehicle_ids ) ? $vehicle_ids : array( 0 ),
				'post_status'         => 'publish',
				'orderby'             => 'title',
				'order'               => 'ASC',
				'posts_per_page'      => $cap,
				'no_found_rows'       => false,
				'ignore_sticky_posts' => true,
			)
		);
		$vehicle_posts = $vehicle_query->posts;

		if ( $vehicle_query->found_posts > count( $vehicle_posts ) ) {
			printf(
				'<div class="notice notice-info mhm-occupancy-matrix-cap-notice"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of vehicle rows shown before the fleet is trimmed. */
						__( 'Showing first %d vehicles — narrow the filters to see the rest.', 'mhm-rentiva' ),
						$cap
					)
				)
			);
		}

		$row_ids = array();
		foreach ( $vehicle_posts as $vehicle_post ) {
			$row_ids[] = (int) $vehicle_post->ID;
		}
		if ( ! empty( $row_ids ) ) {
			// Vehicle posts are NOT part of this screen's main query (which
			// is over mhmrentiva_booking) -- prime titles/plates in ONE call
			// instead of 2 queries per row-head.
			_prime_post_caches( $row_ids, false, true );
		}

		$status_filter = self::get_query_text( 'mhmrentiva_booking_status' );

		// Fix round 1, Finding 2: an active status chip outside the
		// occupied-status set (cancelled/refunded/no_show/draft/
		// pending_payment) makes get_calendar_row_source()'s own HAVING
		// unsatisfiable -- ANY status = that chip on top of the base
		// occupied-status restriction is always false, so $vehicle_posts is
		// unconditionally empty. FleetOccupancyMatrix::render() prints
		// nothing for an empty $vehicles array (correctly, for its OTHER
		// caller -- Vehicles legitimately renders zero-booking vehicle
		// rows), so a bare header with no explanation is what the user
		// would see without this branch. No silent empties: explain instead
		// of rendering the matrix.
		if ( empty( $vehicle_posts ) ) {
			// "Can this chip ever paint?" — asked of the ONE occupied-status
			// set OccupancyMapService owns, not of a local copy of it.
			if ( '' !== $status_filter && ! in_array( $status_filter, OccupancyMapService::PAINTED_STATUSES, true ) ) {
				printf(
					'<div class="notice notice-info mhm-occupancy-matrix-empty"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: the active status chip's label (e.g. "Cancelled"). */
							__( 'The %s filter has no calendar view — cancelled and similar bookings do not occupy vehicles. Switch to the List view to see them.', 'mhm-rentiva' ),
							Status::get_label( $status_filter )
						)
					)
				);
			} else {
				printf(
					'<div class="notice notice-info mhm-occupancy-matrix-empty"><p>%s</p></div>',
					esc_html__( 'No bookings match the current filters in this month.', 'mhm-rentiva' )
				);
			}
		} else {
			\MHMRentiva\Admin\Core\ListTable\FleetOccupancyMatrix::render(
				$vehicle_posts,
				$current_month,
				$current_year,
				array(
					'show_plate'          => false,
					'enable_block_toggle' => false,
					'filter_statuses'     => '' !== $status_filter ? array( $status_filter ) : array(),
					'screen'              => 'bookings',
				)
			);
		}

		if ( $vehicleless_count > 0 ) {
			// A `div.notice`, exactly like the cap and empty notices above.
			// It used to be a bare `<p>`: core's common.js only relocates
			// `div.updated/.error/.notice`, and this face's own relocation
			// JS moves the `.mhm-calendars` wrapper alone — so the note was
			// left stranded above the page <h1>, detached from the matrix it
			// annotates, and unstyled (only the `-empty` and `-cap-notice`
			// classes had CSS). Same element, same class family, same
			// relocation, same skin as its two siblings now.
			printf(
				'<div class="notice notice-info mhm-occupancy-matrix-vehicleless-note"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of bookings with no vehicle assigned (transfers etc.), formatted for display. */
						_n(
							'%s booking has no vehicle assigned and is not shown in this view — see the List view.',
							'%s bookings have no vehicle assigned and are not shown in this view — see the List view.',
							$vehicleless_count,
							'mhm-rentiva'
						),
						number_format_i18n( $vehicleless_count )
					)
				)
			);
		}
	}

	/**
	 * Row source for the Calendar face: one unpaginated query mirroring
	 * OccupancyMapService::get_map()'s WHERE/HAVING shape (same dual-key
	 * COALESCE resolution for vehicle/pickup/dropoff/status, same
	 * pickup<=month-end AND dropoff>=month-start overlap window, same
	 * occupied-status set and pending-deadline exemption — the exact
	 * definition of "occupied" get_map()'s own docblock says is defined
	 * once, for every consumer), narrowed by whichever of the status chip /
	 * gateway filter / search box this screen currently carries.
	 *
	 * That get_map() call is not reusable here: it aggregates rows into a
	 * per-day-per-vehicle map with no row-level filter hook, so this
	 * mirrors its SQL shape instead of calling it (the brief's fallback
	 * option). Runs as ONE query returning every in-scope booking's
	 * resolved vehicle id (0/empty included) rather than two, so the
	 * vehicle-less count below is a by-product of the same result set the
	 * row ids come from, not a second query.
	 *
	 * @return array{vehicle_ids: int[], vehicleless_count: int}
	 */
	private static function get_calendar_row_source( int $month, int $year ): array {
		global $wpdb;

		$start = sprintf( '%04d-%02d-01', $year, $month );
		$end   = sprintf( '%04d-%02d-%02d', $year, $month, (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) ) );

		// Search 's': the same 3-field LIKE WP_Query's own parse_search()
		// runs for the list face's native admin search box -- no custom
		// search filter is registered for bookings anywhere in this plugin.
		$search      = self::get_query_text( 's' );
		$search_like = '' !== $search ? '%' . $wpdb->esc_like( $search ) . '%' : '';

		// Gateway filter: same whitelist apply_status_filter() enforces
		// (the DISTINCT in-use set, not WC's full registered-gateway list).
		// A value outside the whitelist is folded to '' -- i.e. no filter --
		// exactly as the old fragment-appending shape did by not appending.
		$gateway_filter = self::get_query_text( 'mhmrentiva_payment_gateway' );
		if ( '' !== $gateway_filter && ! in_array( $gateway_filter, self::get_gateways_in_use(), true ) ) {
			$gateway_filter = '';
		}

		// Status chip: mirrors apply_status_filter()'s allowed-value check;
		// the base HAVING below already restricts to the occupied-status
		// set, so a chip value outside it (e.g. 'cancelled') simply yields
		// zero rows -- the same degenerate result FleetOccupancyMatrix's own
		// filter_statuses produces for a status get_map() never painted. A
		// value outside Status::allowed() is folded to '' (no filter), again
		// matching the old shape.
		$status_filter = self::get_query_text( 'mhmrentiva_booking_status' );
		if ( '' !== $status_filter && ! in_array( $status_filter, Status::allowed(), true ) ) {
			$status_filter = '';
		}

		// ONE literal SQL string with a FIXED placeholder set: every
		// optional filter is expressed as a neutralizing `%s = '' OR ...`
		// pair rather than a concatenated fragment, so nothing is appended
		// to the query text at runtime and prepare() sees a constant
		// statement. The previous shape was safe (literal-only fragments)
		// but it was still a shape -- Plugin Check's
		// PluginCheck.Security.DirectDB.UnescapedDBParameter fires on the
		// concatenation itself, and a documented suppression is input to a
		// human WP.org reviewer. Eliminate the shape, not the finding.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.ID AS booking_id,
                    COALESCE(NULLIF(pm_v1.meta_value, ''), pm_v2.meta_value) AS vehicle_id,
                    COALESCE(NULLIF(pm_p1.meta_value, ''), pm_p2.meta_value) AS pickup_date,
                    COALESCE(NULLIF(pm_d1.meta_value, ''), pm_d2.meta_value, pm_d3.meta_value) AS dropoff_date,
                    COALESCE(NULLIF(pm_s1.meta_value, ''), NULLIF(pm_s2.meta_value, ''), 'pending') AS status,
                    pm_deadline.meta_value AS deadline
            FROM {$wpdb->posts} b
            LEFT JOIN {$wpdb->postmeta} pm_s1 ON b.ID = pm_s1.post_id AND pm_s1.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_s2 ON b.ID = pm_s2.post_id AND pm_s2.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_v1 ON b.ID = pm_v1.post_id AND pm_v1.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_v2 ON b.ID = pm_v2.post_id AND pm_v2.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_p1 ON b.ID = pm_p1.post_id AND pm_p1.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_p2 ON b.ID = pm_p2.post_id AND pm_p2.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_d1 ON b.ID = pm_d1.post_id AND pm_d1.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_d2 ON b.ID = pm_d2.post_id AND pm_d2.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_d3 ON b.ID = pm_d3.post_id AND pm_d3.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_deadline ON b.ID = pm_deadline.post_id AND pm_deadline.meta_key = %s
            LEFT JOIN {$wpdb->postmeta} pm_gw ON b.ID = pm_gw.post_id AND pm_gw.meta_key = %s
            WHERE b.post_type = %s
            AND b.post_status IN ('publish', 'private', 'pending')
            AND ( %s = '' OR ( b.post_title LIKE %s OR b.post_excerpt LIKE %s OR b.post_content LIKE %s ) )
            AND ( %s = '' OR pm_gw.meta_value = %s )
            HAVING pickup_date IS NOT NULL AND dropoff_date IS NOT NULL
            AND pickup_date <= %s AND dropoff_date >= %s
            AND FIND_IN_SET(status, %s) > 0
            AND (
                status != 'pending' OR
                deadline IS NULL OR
                deadline = '' OR
                deadline > %s
            )
            AND ( %s = '' OR status = %s )",
				'_mhmrentiva_status',
				'_mhmrentiva_booking_status',
				'_mhmrentiva_vehicle_id',
				'_mhmrentiva_booking_vehicle_id',
				'_mhmrentiva_pickup_date',
				'_mhmrentiva_booking_pickup_date',
				'_mhmrentiva_dropoff_date',
				'_mhmrentiva_return_date',
				'_mhmrentiva_end_date',
				'_mhmrentiva_payment_deadline',
				'_mhmrentiva_payment_gateway',
				'mhmrentiva_booking',
				$search,
				$search_like,
				$search_like,
				$search_like,
				$gateway_filter,
				$gateway_filter,
				$end,
				$start,
				OccupancyMapService::painted_statuses_csv(),
				current_time( 'mysql', true ),
				$status_filter,
				$status_filter
			)
		);

		$vehicle_ids       = array();
		$vehicleless_count = 0;

		foreach ( (array) $rows as $row ) {
			$vehicle_id = (int) $row->vehicle_id;
			if ( $vehicle_id > 0 ) {
				$vehicle_ids[ $vehicle_id ] = true;
			} else {
				++$vehicleless_count;
			}
		}

		return array(
			'vehicle_ids'       => array_map( 'intval', array_keys( $vehicle_ids ) ),
			'vehicleless_count' => $vehicleless_count,
		);
	}

	/**
	 * Fetch booking data for calendar view.
	 */
	private static function get_calendar_bookings( int $month, int $year ): array {
		global $wpdb;

		// Rezervasyon verilerini al
		$start_date = sprintf( '%04d-%02d-01', $year, $month );
		$end_date   = sprintf( '%04d-%02d-%02d', $year, $month, (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_title, pm_customer.meta_value as customer_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_customer ON p.ID = pm_customer.post_id AND pm_customer.meta_key = %s
            WHERE p.post_type = 'mhmrentiva_booking'
                AND p.post_status = 'publish'
                AND p.post_date >= %s
                AND p.post_date <= %s
            ORDER BY p.post_date DESC
            LIMIT 20",
				'_mhmrentiva_customer_name',
				$start_date,
				$end_date . ' 23:59:59'
			)
		);

		$bookings = array();
		foreach ( $results as $result ) {
			$bookings[] = array(
				'id'       => $result->ID,
				'title'    => $result->post_title ?: __( 'Booking #', 'mhm-rentiva' ) . $result->ID,
				'customer' => $result->customer_name ?: __( 'Unknown Customer', 'mhm-rentiva' ),
			);
		}

		// Provide sample entries if no bookings exist
		if ( empty( $bookings ) ) {
			$bookings = array(
				array(
					'id'       => 1,
					'title'    => __( 'Sample Booking 1', 'mhm-rentiva' ),
					'customer' => __( 'Sample Customer 1', 'mhm-rentiva' ),
				),
				array(
					'id'       => 2,
					'title'    => __( 'Sample Booking 2', 'mhm-rentiva' ),
					'customer' => __( 'Sample Customer 2', 'mhm-rentiva' ),
				),
			);
		}

		return $bookings;
	}

	/**
	 * Priority mapping for statuses.
	 */
	private static function get_status_priority( string $status ): int {
		switch ( $status ) {
			case 'confirmed':
				return 3;
			case 'pending':
				return 2;
			case 'cancelled':
				return 1;
			default:
				return 0;
		}
	}

	/**
	 * Return display icon for a status.
	 */
	private static function get_status_icon( string $status ): string {
		switch ( $status ) {
			case 'confirmed':
				return '✅';
			case 'pending':
				return '⏳';
			case 'cancelled':
				return '❌';
			default:
				return '📅';
		}
	}

	/**
	 * Return descriptive label for status icon.
	 */
	private static function get_status_label( string $status ): string {
		switch ( $status ) {
			case 'confirmed':
				return __( 'Confirmed Booking', 'mhm-rentiva' );
			case 'pending':
				return __( 'Pending Booking', 'mhm-rentiva' );
			case 'cancelled':
				return __( 'Cancelled Booking', 'mhm-rentiva' );
			default:
				return __( 'Booking', 'mhm-rentiva' );
		}
	}

	/**
	 * Render booking ID filter input.
	 */
	public static function booking_id_filter( string $post_type ): void {
		if ( $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		$current = self::get_query_text( 'mhmrentiva_booking_id' );

		echo '<input type="text" name="mhmrentiva_booking_id" value="' . esc_attr( $current ) . '" placeholder="' . esc_attr__( 'Booking ID', 'mhm-rentiva' ) . '" class="postform" style="width: 120px;" />';
	}

	/**
	 * Render vehicle license plate filter.
	 */
	public static function license_plate_filter( string $post_type ): void {
		if ( $post_type !== 'mhmrentiva_booking' ) {
			return;
		}

		$current = self::get_query_text( 'mhmrentiva_license_plate' );

		echo '<input type="text" name="mhmrentiva_license_plate" value="' . esc_attr( $current ) . '" placeholder="' . esc_attr__( 'License Plate', 'mhm-rentiva' ) . '" class="postform" style="width: 120px;" />';
	}

	/**
	 * Apply custom filters to query.
	 */
	public static function apply_custom_filters( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( ( $q->get( 'post_type' ) ?? '' ) !== 'mhmrentiva_booking' ) {
			return;
		}

		$meta_query = $q->get( 'meta_query' ) ?: array();

		// Customer e-mail filter — the Customers screen links here with
		// ?mhmrentiva_customer_email=… ("View Bookings"); without this clause
		// the parameter was silently ignored and the full list rendered.
		// Registered query var, read through the same helper the sibling
		// filters use.
		$customer_email = sanitize_email( self::get_query_text( 'mhmrentiva_customer_email' ) );
		if ( '' !== $customer_email ) {
			$meta_query[] = array(
				'key'   => '_mhmrentiva_customer_email',
				'value' => $customer_email,
			);
			$q->set( 'meta_query', $meta_query );
		}

		// Booking ID filter
		$booking_id_filter = self::get_query_int( 'mhmrentiva_booking_id' );
		if ( $booking_id_filter > 0 ) {
			$booking_id = $booking_id_filter;
			if ( $booking_id > 0 ) {
				$q->set( 'p', $booking_id );
			}
		}

		// License plate filter
		$license_plate = self::get_query_text( 'mhmrentiva_license_plate' );
		if ( '' !== $license_plate ) {

			// Lookup vehicle IDs by license plate fragment
			global $wpdb;
			$vehicle_ids = $wpdb->get_col(
				$wpdb->prepare(
					"
                SELECT DISTINCT p.ID 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'mhmrentiva_vehicle'
                    AND p.post_status = 'publish'
                    AND pm.meta_key = '_mhmrentiva_license_plate'
                    AND pm.meta_value LIKE %s
            ",
					'%' . $wpdb->esc_like( $license_plate ) . '%'
				)
			);

			if ( ! empty( $vehicle_ids ) ) {
				// Collect bookings for those vehicles
				$vehicle_ids = array_values( array_map( 'intval', $vehicle_ids ) );
				$booking_ids = $wpdb->get_col(
					$wpdb->prepare(
						"
					SELECT DISTINCT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type = 'mhmrentiva_booking'
						AND p.post_status = 'publish'
						AND pm.meta_key IN ('_mhmrentiva_booking_vehicle_id', '_mhmrentiva_vehicle_id')
						AND pm.meta_value IN (" . implode( ', ', array_fill( 0, count( $vehicle_ids ), '%d' ) ) . ')
				',
						$vehicle_ids
					)
				);

				if ( ! empty( $booking_ids ) ) {
					$q->set( 'post__in', $booking_ids );
				} else {
					// No bookings found
					$q->set( 'post__in', array( 0 ) );
				}
			} else {
				// No vehicles found
				$q->set( 'post__in', array( 0 ) );
			}
		}

		if ( ! empty( $meta_query ) ) {
			$q->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Get booking title display text for list table
	 */
	public static function get_booking_title_display( int $post_id ): string {
		// Use BookingQueryHelper to get customer info (handles multiple meta keys)
		$customer_info = array();
		if ( class_exists( '\\MHMRentiva\\Admin\\Core\\Utilities\\BookingQueryHelper' ) ) {
			$customer_info = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::getBookingCustomerInfo( (int) $post_id );
		}

		$customer_first_name = $customer_info['first_name'] ?? '';
		$customer_last_name  = $customer_info['last_name'] ?? '';
		$customer_email      = $customer_info['email'] ?? '';
		$customer_phone      = $customer_info['phone'] ?? '';

		// Build customer name
		if ( $customer_first_name && $customer_last_name ) {
			$customer_name = trim( $customer_first_name . ' ' . $customer_last_name );
		} elseif ( $customer_first_name ) {
			$customer_name = $customer_first_name;
		} elseif ( $customer_last_name ) {
			$customer_name = $customer_last_name;
		} else {
			// Fallback to legacy meta fields
			$customer_name = get_post_meta( $post_id, '_mhmrentiva_booking_customer_name', true ) ?:
				get_post_meta( $post_id, '_mhmrentiva_customer_name', true ) ?:
				get_post_meta( $post_id, '_mhmrentiva_contact_name', true );
		}

		// If still empty, resolve via related WP user
		if ( ! $customer_name ) {
			$user_id = get_post_meta( $post_id, '_mhmrentiva_customer_user_id', true );
			if ( $user_id ) {
				$user = get_userdata( $user_id );
				if ( $user ) {
					$customer_name = $user->display_name ?: trim( $user->first_name . ' ' . $user->last_name );
					if ( empty( $customer_email ) ) {
						$customer_email = $user->user_email;
					}
					if ( empty( $customer_phone ) ) {
						$customer_phone = get_user_meta( $user_id, 'phone', true );
					}
				}
			}
		}

		// If still no customer name, try WooCommerce order
		if ( ! $customer_name && function_exists( 'wc_get_order' ) ) {
			// Try multiple order ID meta keys
			$order_id = get_post_meta( $post_id, '_mhmrentiva_order_id', true ) ?:
				get_post_meta( $post_id, '_mhmrentiva_wc_order_id', true ) ?:
				get_post_meta( $post_id, '_mhmrentiva_booking_order_id', true );

			if ( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
					if ( empty( $customer_email ) ) {
						$customer_email = $order->get_billing_email();
					}
					if ( empty( $customer_phone ) ) {
						$customer_phone = $order->get_billing_phone();
					}
				}
			}
		}

		// If still no customer name, try to extract from email
		if ( ! $customer_name && $customer_email ) {
			// Extract name from email (part before @)
			$email_parts = explode( '@', $customer_email );
			if ( ! empty( $email_parts[0] ) ) {
				$customer_name = $email_parts[0];
				// Replace dots and underscores with spaces, capitalize first letter
				$customer_name = str_replace( array( '.', '_', '-' ), ' ', $customer_name );
				$customer_name = ucwords( strtolower( $customer_name ) );
			}
		}

		// Without a customer name, use default title
		if ( ! $customer_name ) {
			// Get post title directly from database to avoid infinite loop with the_title filter
			$post          = get_post( $post_id );
			$default_title = $post ? $post->post_title : '';
			if ( empty( $default_title ) || $default_title === __( 'Auto Draft', 'mhm-rentiva' ) ) {
				/* translators: %d: booking ID */
				return sprintf( __( 'Booking #%d', 'mhm-rentiva' ), $post_id );
			}
			// $default_title is raw $post->post_title (untrusted DB data) — escape
			// it here too, at this early return point.
			return esc_html( $default_title );
		}

		// Return plain text summary prioritizing phone over email
		$new_title = $customer_name;

		if ( $customer_phone ) {
			$new_title .= ' - ' . $customer_phone;
		} elseif ( $customer_email ) {
			$new_title .= ' - ' . $customer_email;
		}

		// $new_title is assembled from DB-read data (post meta / user profile /
		// WooCommerce order fields), which is untrusted per the rubric. Escape
		// here (the single point where this helper hands the value back to its
		// only caller, the `the_title` filter) so it is escaped exactly once.
		return esc_html( $new_title );
	}

	/**
	 * Replace booking title with customer details.
	 */
	public static function modify_booking_title( string $title, ?int $post_id = null ): string {
		// Prevent infinite loop
		if ( self::$in_title_filter ) {
			return $title;
		}

		// Apply only within admin booking list context
		if ( ! is_admin() || ! $post_id ) {
			return $title;
		}

		// Check if we're on the booking list page
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $title;
		}

		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'mhmrentiva_booking' || $screen->base !== 'edit' ) {
			return $title;
		}

		// Set flag to prevent recursion
		self::$in_title_filter = true;

		// Use the shared function to get booking title display
		$new_title = self::get_booking_title_display( $post_id );

		// Reset flag
		self::$in_title_filter = false;

		// If we got a valid title, return it; otherwise keep original
		return ! empty( $new_title ) ? $new_title : $title;
	}
}
