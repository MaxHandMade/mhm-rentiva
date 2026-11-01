<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Frontend\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Core\SecurityHelper;
use MHMRentiva\Admin\Core\Security\VerifiedRequest;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

/**
 * Availability calendar (`rentiva_availability_calendar`) on the front-end standard.
 *
 * Aşama 2 PR E: the data is CalendarView, the template prints it inside
 * `.rvs-shell`, and the endpoints answer with the same partials. The first
 * paint is server-rendered; the HTML can be cached for minutes, so the script
 * refreshes the months through `mhmrentiva_calendar_months` on load.
 */
final class AvailabilityCalendar extends AbstractShortcode {

	public const SHORTCODE = 'rentiva_availability_calendar';

	protected static bool $rvs_shell = true;

	public static function register(): void {
		parent::register();

		add_action( 'wp_ajax_mhmrentiva_calendar_months', array( self::class, 'ajax_months' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_calendar_months', array( self::class, 'ajax_months' ) );
		add_action( 'wp_ajax_mhmrentiva_calendar_quote', array( self::class, 'ajax_quote' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_calendar_quote', array( self::class, 'ajax_quote' ) );
		add_action( 'wp_ajax_mhmrentiva_get_vehicle_info', array( self::class, 'ajax_vehicle_info' ) );
		add_action( 'wp_ajax_nopriv_mhmrentiva_get_vehicle_info', array( self::class, 'ajax_vehicle_info' ) );
	}

	protected static function get_shortcode_tag(): string {
		return self::SHORTCODE;
	}

	protected static function get_template_path(): string {
		return 'shortcodes/availability-calendar';
	}

	protected static function get_default_attributes(): array {
		return array(
			'vehicle_id'            => '',
			'show_pricing'          => apply_filters( 'mhmrentiva_availability_calendar_show_pricing', '1' ),
			'show_booking_button'   => apply_filters(
				'mhmrentiva_availability_calendar_show_booking_button',
				apply_filters( 'mhmrentiva_availability_calendar_show_booking_btn', '1' )
			),
			'theme'                 => apply_filters( 'mhmrentiva_availability_calendar_theme', 'default' ),
			'months_to_show'        => apply_filters( 'mhmrentiva_availability_calendar_months_to_show', '2' ),
			'start_month'           => '',
			'show_legend'           => '1',
			'show_month_nav'        => '1',
			'show_vehicle_selector' => '1',
			'start_week_on'         => '1', // 1 = Monday, 0 = Sunday.
			'class'                 => '',
		);
	}

	protected static function get_js_filename(): string {
		return 'availability-calendar.js';
	}

	/**
	 * The calendar, the vehicle dialog and the page script.
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 */
	protected static function enqueue_assets( array $atts = array() ): void {
		AssetManager::enqueue_rvs_availability_styles();
		AssetManager::enqueue_rvs_dialog();
		self::enqueue_scripts( $atts );
	}

	protected static function get_js_dependencies(): array {
		return array( 'jquery', 'mhm-rentiva-rvs-dialog' );
	}

	protected static function get_script_object_name(): string {
		return 'mhmRentivaAvailability';
	}

	protected static function get_localized_strings(): array {
		return array(
			'error'      => __( 'Something went wrong. Please try again.', 'mhm-rentiva' ),
			'loading'    => __( 'Loading...', 'mhm-rentiva' ),
			'pickReturn' => __( 'Now choose your return date.', 'mhm-rentiva' ),
			'selected'   => __( 'Selected', 'mhm-rentiva' ),
		);
	}

	/**
	 * `showPricing` and `showBookingButton` are the defaults; each calendar's own
	 * values are on its root (`data-show-pricing`, `data-show-booking-button`).
	 */
	protected static function get_localized_data(): array {
		$defaults = self::get_default_attributes();
		$data     = parent::get_localized_data();

		$data['nonce']             = wp_create_nonce( 'mhmrentiva_availability_nonce' );
		$data['showPricing']       = CalendarView::is_on( $defaults['show_pricing'] );
		$data['showBookingButton'] = CalendarView::is_on( $defaults['show_booking_button'] );
		$data['dateFormat']        = (string) get_option( 'date_format' );

		return $data;
	}

	protected static function prepare_template_data( array $atts ): array {
		$vehicle_id = CalendarView::resolve_vehicle( $atts['vehicle_id'] ?? '' );

		return array(
			'vehicle_id' => $vehicle_id,
			'atts'       => $atts,
			'view'       => $vehicle_id > 0 ? CalendarView::build( $vehicle_id, $atts ) : array(),
		);
	}

	/**
	 * The nonce-checked request, or a JSON error.
	 */
	private static function verified_request(): VerifiedRequest {
		if ( ! check_ajax_referer( 'mhmrentiva_availability_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'mhm-rentiva' ) ) );
		}
		return VerifiedRequest::from( $_POST );
	}

	/**
	 * Months from `start_month`, as the page's own partial. An optional
	 * `start_date` / `end_date` marks the visitor's selection.
	 */
	public static function ajax_months(): void {
		$req        = self::verified_request();
		$vehicle_id = VehicleDetails::requested_vehicle( $req );
		if ( $vehicle_id <= 0 ) {
			return;
		}
		// The script asks once per load and per step: wider than the quote's allowance.
		SecurityHelper::check_rate_limit_or_die( 'calendar_months', 60, 60, __( 'Too many requests. Please wait.', 'mhm-rentiva' ) );

		$first    = CalendarView::opening_month( $req->text( 'start_month' ) );
		$selected = array_values( array_filter( array( $req->text( 'start_date' ), $req->text( 'end_date' ) ) ) );
		$months   = CalendarView::months(
			$vehicle_id,
			$first,
			$req->intRange( 'months_to_show', 2, 1, CalendarView::MAX_MONTH_SPAN ),
			array(
				'week_start' => $req->intRange( 'week_start', 1, 0, 6 ),
				'selected'   => $selected,
			)
		);

		wp_send_json_success(
			array(
				'html'         => VehicleDetails::months_html( $months ),
				'prev_allowed' => CalendarView::prev_allowed( $first ),
				'start_month'  => $first,
			)
		);
	}

	/**
	 * The selection panel for `start_date` .. `end_date`.
	 */
	public static function ajax_quote(): void {
		$req        = self::verified_request();
		$vehicle_id = VehicleDetails::requested_vehicle( $req );
		if ( $vehicle_id <= 0 ) {
			return;
		}
		// The vehicle page's bucket: both quote the same card.
		SecurityHelper::check_rate_limit_or_die( 'vehicle_quote', 30, 60, __( 'Too many price requests. Please wait.', 'mhm-rentiva' ) );

		wp_send_json_success( CalendarView::quote( $vehicle_id, $req->text( 'start_date' ), $req->text( 'end_date' ) ) );
	}

	/**
	 * The vehicle card after a switch.
	 */
	public static function ajax_vehicle_info(): void {
		$req        = self::verified_request();
		$vehicle_id = VehicleDetails::requested_vehicle( $req );
		if ( $vehicle_id <= 0 ) {
			return;
		}
		SecurityHelper::check_rate_limit_or_die( 'calendar_vehicle_info', 60, 60, __( 'Too many requests. Please wait.', 'mhm-rentiva' ) );

		wp_send_json_success( CalendarView::vehicle_card( $vehicle_id ) );
	}

	/**
	 * A missing or unavailable vehicle is a short empty state, never another car.
	 *
	 * @param array<string, mixed> $template_data Prepared template data.
	 */
	protected static function render_template( array $template_data ): string {
		if ( (int) ( $template_data['vehicle_id'] ?? 0 ) > 0 ) {
			return parent::render_template( $template_data );
		}

		return (string) Templates::render(
			'partials/rvs/calendar-panel',
			array(
				'empty' => CalendarView::names_vehicle( $template_data['atts']['vehicle_id'] ?? '' ) ? __( 'Vehicle not found.', 'mhm-rentiva' ) : __( 'No vehicles found.', 'mhm-rentiva' ),
				'class' => (string) ( $template_data['atts']['class'] ?? '' ),
			),
			true
		);
	}
}
