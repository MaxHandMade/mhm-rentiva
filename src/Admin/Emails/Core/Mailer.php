<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\Core;

if (!defined('ABSPATH')) {
    exit;
}



// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded application queries are intentional in this module.



use MHMRentiva\Admin\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mailer {



	/**
	 * Booking data provider instance
	 *
	 * @var BookingDataProviderInterface|null
	 */
	private static ?BookingDataProviderInterface $booking_data_provider = null;

	/**
	 * Get booking data provider instance
	 *
	 * Uses filter hook to allow addons to provide custom implementations
	 *
	 * @return BookingDataProviderInterface Booking data provider
	 */
	private static function get_booking_data_provider(): BookingDataProviderInterface {
		if ( self::$booking_data_provider === null ) {
			/**
			 * Filter: Allow addons to provide custom booking data provider
			 *
			 * @param BookingDataProviderInterface|null $provider Booking data provider instance
			 * @return BookingDataProviderInterface Modified booking data provider instance
			 *
			 * @example
			 * add_filter('mhmrentiva_booking_data_provider', function($provider) {
			 *     if (class_exists('MyCustomBookingProvider')) {
			 *         return new MyCustomBookingProvider();
			 *     }
			 *     return $provider;
			 * });
			 */
			$provider = apply_filters( 'mhmrentiva_booking_data_provider', null );

			if ( $provider instanceof BookingDataProviderInterface ) {
				self::$booking_data_provider = $provider;
			} else {
				// Default: Use BookingQueryHelperAdapter
				self::$booking_data_provider = new BookingQueryHelperAdapter();
			}
		}

		return self::$booking_data_provider;
	}
	public static function register(): void {
		// Placeholder for future hooks
	}

	/**
	 * Template key => the option its "Enabled" box on the email-templates
	 * screen writes ('1' ticked, '0' unticked; EmailTemplates::save_email_fields()).
	 *
	 * Only templates the screen draws a box for are listed; any other key
	 * (booking_status_changed_admin, auto_cancel -- which has its own toggle
	 * in Booking settings -- and every add-on template) is not gated here.
	 */
	private const TEMPLATE_ENABLED_OPTIONS = array(
		'booking_created_customer'        => 'mhmrentiva_booking_created_enabled',
		'booking_status_changed_customer' => 'mhmrentiva_booking_status_enabled',
		'booking_created_admin'           => 'mhmrentiva_booking_admin_enabled',
		'booking_cancelled'               => 'mhmrentiva_booking_cancelled_enabled',
		'booking_reminder_customer'       => 'mhmrentiva_booking_reminder_enabled',
		'refund_customer'                 => 'mhmrentiva_refund_customer_enabled',
		'refund_admin'                    => 'mhmrentiva_refund_admin_enabled',
	);

	/**
	 * The templates "Send Booking Notifications" (Email settings, "booking
	 * confirmation, reminder, and cancellation emails") covers. The check used
	 * to name 'booking_confirmation' / 'booking_reminder' /
	 * 'booking_cancellation', keys no template has, so the toggle -- and the
	 * customer's own opt-out meta -- stopped nothing. The welcome check had the
	 * same defect ('welcome' for 'welcome_customer').
	 */
	private const BOOKING_NOTIFICATION_KEYS = array(
		'booking_created_customer',
		'booking_reminder_customer',
		'booking_cancelled',
		'auto_cancel',
	);

	/**
	 * Whether the operator left this template's "Enabled" box ticked.
	 *
	 * Reads the box exactly as the screen draws it -- get_option( $key, '1' )
	 * and checked when '1' -- so a box that was never saved, which the screen
	 * shows ticked, keeps sending. Only an explicit untick ('0') stops it.
	 *
	 * @param string $key Template key.
	 */
	public static function is_template_enabled( string $key ): bool {
		$option = self::TEMPLATE_ENABLED_OPTIONS[ $key ] ?? null;
		if ( null === $option ) {
			return true;
		}

		$value = get_option( $option, '1' );

		return '1' === $value || 1 === $value || true === $value;
	}

	/**
	 * Central email sending method
	 *
	 * @param string $key Template key
	 * @param string $to Recipient email
	 * @param array  $context Template context
	 * @param array  $headers Additional headers
	 * @return bool Success status
	 */
	public static function send( string $key, string $to, array $context = array(), array $headers = array() ): bool {
		// The template's own "Enabled" box. Checked here, at the one door every
		// automatic sender goes through, so no send path can forget it.
		if ( ! self::is_template_enabled( $key ) ) {
			return false;
		}

		return self::deliver( $key, $to, $context, $headers );
	}

	/**
	 * An operator-requested test send of a template.
	 *
	 * Ignores the template's "Enabled" box: the box decides what customers
	 * receive, not which templates the operator may look at (the preview
	 * ignores it for the same reason). Every global rule -- outgoing mail
	 * switched off, test mode -- still applies.
	 *
	 * @param string $key     Template key.
	 * @param string $to      Recipient email.
	 * @param array  $context Template context.
	 * @param array  $headers Additional headers.
	 */
	public static function send_test( string $key, string $to, array $context = array(), array $headers = array() ): bool {
		return self::deliver( $key, $to, $context, $headers );
	}

	/**
	 * An e-mail the operator wrote (or edited) for one customer, already
	 * rendered (PR 3b-3). It is not an automatic notification: the template's
	 * "Enabled" box and the customer's automatic-notification preference do not
	 * stop it -- the operator chose to send it, and the booking screen says when
	 * the customer turned notifications off. Every global rule still applies:
	 * outgoing mail switched off, test mode (sandbox recipient). The send is
	 * logged through mhmrentiva_email_sent like every other e-mail.
	 *
	 * @param string $log_key Template key, or 'manual_customer' for a custom message.
	 * @return array{sent: bool, to: string} Whether it went out, and to whom (the test address in test mode).
	 */
	public static function send_operator_message( string $log_key, string $to, string $subject, string $body, array $context = array() ): array {
		if ( '' === $to || ! is_email( $to ) || ! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_send_enabled() ) {
			return array(
				'sent' => false,
				'to'   => $to,
			);
		}

		$to      = \MHMRentiva\Admin\Settings\Groups\EmailSettings::sandbox_recipient( $to );
		$headers = array(
			'From: ' . \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_name() . ' <' . \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_address() . '>',
			'Content-Type: text/html; charset=UTF-8',
		);
		$ok      = wp_mail( $to, $subject, $body, $headers );

		do_action( 'mhmrentiva_email_sent', $log_key, $to, $ok, $subject, $context );

		return array(
			'sent' => (bool) $ok,
			'to'   => $to,
		);
	}

	/**
	 * Whether the customer with this address turned automatic booking
	 * notifications off on their account page.
	 */
	public static function customer_turned_off_notifications( string $email ): bool {
		$user = '' !== $email ? get_user_by( 'email', $email ) : false;

		return $user instanceof \WP_User && '0' === get_user_meta( $user->ID, 'mhmrentiva_booking_notifications', true );
	}

	/**
	 * Render and hand a template to wp_mail().
	 *
	 * @param string $key     Template key.
	 * @param string $to      Recipient email.
	 * @param array  $context Template context.
	 * @param array  $headers Additional headers.
	 */
	private static function deliver( string $key, string $to, array $context, array $headers ): bool {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		// Check if email sending is enabled
		if ( ! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_send_enabled() ) {
			return false;
		}

		// Customer communication preferences check
		if ( isset( $context['customer']['email'] ) && $context['customer']['email'] === $to ) {
			// Get user ID from email
			$user = get_user_by( 'email', $to );
			if ( $user ) {
				// Welcome email check - both global setting and user preference
				if ( $key === 'welcome_customer' ) {
					$global_enabled = self::is_welcome_email_enabled();
					$user_enabled   = get_user_meta( $user->ID, 'mhmrentiva_welcome_email', true );
					if ( ! $global_enabled || $user_enabled === '0' ) {
						return false;
					}
				}

				// Booking notification check - both global setting and user preference
				if ( in_array( $key, self::BOOKING_NOTIFICATION_KEYS, true ) ) {
					$global_enabled = self::is_booking_notifications_enabled();
					$user_enabled   = get_user_meta( $user->ID, 'mhmrentiva_booking_notifications', true );
					if ( ! $global_enabled || $user_enabled === '0' ) {
						return false;
					}
				}

				// Marketing email check - both global setting and user preference
				if ( $key === 'marketing' ) {
					$global_enabled = self::is_marketing_emails_enabled();
					$user_enabled   = get_user_meta( $user->ID, 'mhmrentiva_marketing_emails', true );
					if ( ! $global_enabled || $user_enabled === '0' ) {
						return false;
					}
				}
			} else {
				// User not found, check only global settings
				if ( $key === 'welcome_customer' && ! self::is_welcome_email_enabled() ) {
					return false;
				}

				if ( in_array( $key, self::BOOKING_NOTIFICATION_KEYS, true ) && ! self::is_booking_notifications_enabled() ) {
					return false;
				}

				if ( $key === 'marketing' && ! self::is_marketing_emails_enabled() ) {
					return false;
				}
			}
		}

		// If in test mode, send to test address
		$to = \MHMRentiva\Admin\Settings\Groups\EmailSettings::sandbox_recipient( $to );

		// Written in the SITE's language, whoever triggered it: inside wp-admin
		// the request runs in the operator's profile language, and an e-mail
		// an English-profile operator's status change sent to a Turkish
		// site's customer used to arrive in English.
		list( $subject, $body ) = self::render_in_site_locale( $key, $context );
		$headers                = self::normalize_headers( $headers );

		// Default headers
		$from_name    = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_name();
		$from_address = \MHMRentiva\Admin\Settings\Groups\EmailSettings::get_from_address();

		$default_headers = array(
			'From: ' . $from_name . ' <' . $from_address . '>',
			'Content-Type: text/html; charset=UTF-8',
		);

		$headers = array_merge( $default_headers, $headers );
		$headers = array_unique( $headers );

		$ok = wp_mail( $to, $subject, $body, $headers );

		// Action hook for logging/monitoring
		do_action( 'mhmrentiva_email_sent', $key, $to, $ok, $subject, $context );

		return $ok;
	}

	/**
	 * Subject and body of a template, rendered in the site locale.
	 *
	 * The preview uses this too, so what the operator previews is what the
	 * customer receives.
	 *
	 * @param string $key     Template key.
	 * @param array  $context Template context.
	 * @return array{0: string, 1: string} Subject, body.
	 */
	public static function render_in_site_locale( string $key, array $context ): array {
		return \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::in_site_locale(
			static function () use ( $key, $context ): array {
				return array(
					Templates::compile_subject( $key, $context ),
					Templates::render_body( $key, $context ),
				);
			}
		);
	}

	/**
	 * Booking email sending helper
	 *
	 * @param string $template_key Template key
	 * @param int    $booking_id Booking ID
	 * @param string $recipient_type 'customer' or 'admin'
	 * @param array  $additional_context Additional context data
	 * @return bool Success status
	 */
	public static function sendBookingEmail( string $template_key, int $booking_id, string $recipient_type, array $additional_context = array() ): bool {
		$booking_data = self::getBookingContext( $booking_id );
		if ( ! $booking_data ) {
			return false;
		}

		$context = array_merge( $booking_data, $additional_context );

		if ( $recipient_type === 'customer' ) {
			$email = $context['customer']['email'] ?? '';
			if ( empty( $email ) ) {
				return false;
			}
			return self::send( $template_key, $email, $context );
		} elseif ( $recipient_type === 'admin' ) {
			// Every booking template sent with 'admin' is booking-related
			// (booking_created_admin, booking_status_changed_admin).
			$email = \MHMRentiva\Admin\Settings\Groups\EmailSettings::admin_recipient( 'booking' );
			if ( empty( $email ) ) {
				return false;
			}
			return self::send( $template_key, $email, $context );
		}

		return false;
	}

	/**
	 * Prepare booking context data
	 *
	 * @param int $booking_id Booking ID
	 * @return array|null Booking context data
	 */
	public static function getBookingContext( int $booking_id ): ?array {
		if ( $booking_id <= 0 ) {
			return null;
		}

		$post = get_post( $booking_id );
		if ( ! $post || $post->post_type !== 'mhmrentiva_booking' ) {
			return null;
		}

		// ⭐ Get data using interface-based provider (loose coupling)
		$provider        = self::get_booking_data_provider();
		$customer_info   = $provider->getBookingCustomerInfo( $booking_id );
		$vehicle_info    = $provider->getBookingVehicleInfo( $booking_id );
		$date_info       = $provider->getBookingDateInfo( $booking_id );
		$payment_status  = $provider->getBookingPaymentStatus( $booking_id );
		$payment_gateway = $provider->getBookingPaymentGateway( $booking_id );
		$total_price     = $provider->getBookingTotalPrice( $booking_id );

		// Get payment information
		$payment_type     = get_post_meta( $booking_id, '_mhmrentiva_payment_type', true );
		$payment_method   = get_post_meta( $booking_id, '_mhmrentiva_payment_method', true );
		$deposit_amount   = get_post_meta( $booking_id, '_mhmrentiva_deposit_amount', true );
		$remaining_amount = get_post_meta( $booking_id, '_mhmrentiva_remaining_amount', true );
		$payment_deadline = get_post_meta( $booking_id, '_mhmrentiva_payment_deadline', true );
		$wc_order_id      = \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( (int) $booking_id );

		// Service type: 'rental' (default) or 'transfer'
		$service_type  = (string) get_post_meta( $booking_id, '_mhmrentiva_service_type', true );
		$transfer_flag = (string) get_post_meta( $booking_id, '_mhmrentiva_is_transfer', true );
		if ( $service_type === '' && (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_origin_id', true ) > 0 ) {
			$service_type = 'transfer';
		}
		if ( $service_type === '' && in_array( $transfer_flag, array( '1', 'yes', 'true' ), true ) ) {
			$service_type = 'transfer';
		}
		if ( $service_type === '' ) {
			$service_type = 'rental';
		}

		// Transfer route context (origin + destination + meta)
		$transfer_context = array();
		if ( $service_type === 'transfer' ) {
			$origin_id      = (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_origin_id', true );
			$destination_id = (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_destination_id', true );
			// Locations come from an add-on via the filter. This whole branch only
			// runs for transfer bookings, which Lite cannot create; if it is ever
			// reached without a subscriber the names degrade to empty strings below.
			$origin_loc       = $origin_id > 0 ? apply_filters( 'mhmrentiva_location_by_id', null, $origin_id ) : null;
			$dest_loc         = $destination_id > 0 ? apply_filters( 'mhmrentiva_location_by_id', null, $destination_id ) : null;
			$transfer_context = array(
				'origin_name'      => $origin_loc ? (string) $origin_loc->name : '',
				'origin_city'      => $origin_loc && ! empty( $origin_loc->city ) ? (string) $origin_loc->city : '',
				'destination_name' => $dest_loc ? (string) $dest_loc->name : '',
				'destination_city' => $dest_loc && ! empty( $dest_loc->city ) ? (string) $dest_loc->city : '',
				'distance_km'      => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_distance_km', true ),
				'duration_min'     => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_duration_min', true ),
				'adults'           => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_adults', true ),
				'children'         => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_children', true ),
				'luggage_big'      => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_luggage_big', true ),
				'luggage_small'    => (int) get_post_meta( $booking_id, '_mhmrentiva_transfer_luggage_small', true ),
			);
		}

		$customer_name = trim( (string) $customer_info['first_name'] . ' ' . (string) $customer_info['last_name'] );

		$pickup_time_raw = self::first_meta( $booking_id, array( '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ) );
		$return_time_raw = self::first_meta( $booking_id, array( '_mhmrentiva_end_time', '_mhmrentiva_dropoff_time', '_mhmrentiva_booking_dropoff_time' ) );

		return array(
			'booking'  => array(
				'id'               => $booking_id,
				'order_id'         => $wc_order_id ?: $booking_id,
				// The number every screen shows (the order number when there is
				// one); templates print this, not the raw post ID.
				'display_id'       => mhmrentiva_get_display_id( (int) $booking_id ),
				'title'            => $post->post_title,
				'status'           => $post->post_status,
				'service_type'     => $service_type,
				'payment_status'   => $payment_status,
				'payment_gateway'  => $payment_gateway,
				'total_price'      => $total_price,
				// Printed in the site's date format, in the site language (PR 3b-3);
				// the stored value stays under *_raw for anything that compares.
				'pickup_date'      => self::display_date( (string) ( $date_info['pickup_date'] ?? '' ) ),
				'return_date'      => self::display_date( (string) ( $date_info['return_date'] ?? '' ) ),
				'pickup_date_raw'  => (string) ( $date_info['pickup_date'] ?? '' ),
				'return_date_raw'  => (string) ( $date_info['return_date'] ?? '' ),
				// The times in the site's time format, and a date-and-time value
				// that prints the date alone when no time is stored (11-08).
				'pickup_time'      => self::display_time( $pickup_time_raw ),
				'return_time'      => self::display_time( $return_time_raw ),
				'pickup_time_raw'  => $pickup_time_raw,
				'return_time_raw'  => $return_time_raw,
				'pickup_datetime'  => self::display_datetime( (string) ( $date_info['pickup_date'] ?? '' ), $pickup_time_raw ),
				'return_datetime'  => self::display_datetime( (string) ( $date_info['return_date'] ?? '' ), $return_time_raw ),
				'rental_days'      => $date_info['rental_days'] ?? 0,
				'payment_type'     => $payment_type,
				'payment_method'   => $payment_method,
				'deposit_amount'   => $deposit_amount,
				'remaining_amount' => $remaining_amount,
				'payment_deadline' => $payment_deadline ? get_date_from_gmt( (string) $payment_deadline ) : '',
			),
			'customer' => array(
				// Kept as the name itself (admin templates print it in a table).
				'name'            => $customer_name,
				// What a greeting prints: the name, or a phrase when there is none,
				// so a customer never reads "Dear ," (PR 3b-3).
				'salutation_name' => '' !== $customer_name
					? $customer_name
					: \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::resolve(
						static fn (): string => __( 'valued customer', 'mhm-rentiva' )
					),
				'first_name'      => $customer_info['first_name'],
				'last_name'       => $customer_info['last_name'],
				'email'           => $customer_info['email'],
				'phone'           => $customer_info['phone'],
			),
			'vehicle'  => array(
				'id'             => $vehicle_info['id'] ?? 0,
				'title'          => $vehicle_info['title'] ?? '',
				'price_per_day'  => $vehicle_info['price_per_day'] ?? 0,
				'featured_image' => $vehicle_info['featured_image'] ?? '',
			),
			'transfer' => $transfer_context,
			// /panel/ was retired (it now redirects to the account page); custom
			// bodies that still read panel.url get the account URL directly.
			'panel'    => array(
				'url' => \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url(),
			),
			'account'  => array(
				'url'         => \MHMRentiva\Admin\Frontend\Account\AccountController::get_account_url(),
				'booking_url' => \MHMRentiva\Admin\Frontend\Account\AccountController::get_booking_view_url( (int) $booking_id ),
			),
			'site'     => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url( '/' ),
				'admin_email' => get_option( 'admin_email' ),
			),
		);
	}

	/**
	 * A stored Y-m-d date as the site prints dates, in the site language.
	 *
	 * Built from midnight in the site's time zone so a western zone does not
	 * print the day before (wp_date() turns a UTC stamp into local time).
	 * Anything that is not a Y-m-d date prints empty.
	 */
	private static function display_date( string $raw ): string {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, wp_timezone() );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $raw ) {
			return '';
		}

		return (string) \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::in_site_locale(
			static fn () => wp_date( (string) get_option( 'date_format' ), $date->getTimestamp(), wp_timezone() )
		);
	}

	/**
	 * The first of $keys holding a value, in the order the booking edit screen
	 * reads them (BookingEditDates); '' and '0' are "not set" there too.
	 *
	 * @param array<int, string> $keys
	 */
	private static function first_meta( int $booking_id, array $keys ): string {
		foreach ( $keys as $key ) {
			$value = (string) get_post_meta( $booking_id, $key, true );
			if ( '' !== $value && '0' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * A stored H:i (or H:i:s) time as the site prints times, in the site
	 * language. Anything else prints empty.
	 */
	private static function display_time( string $raw ): string {
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?\z/', $raw, $m ) ) {
			return '';
		}
		// A wall-clock time with no date: built in the site's zone, like the date
		// path (a "T" in the time format names the site's zone, not UTC), on a
		// fixed day no zone changes its clocks on, so no gap can move the hour.
		$zone = wp_timezone();
		$time = ( new \DateTimeImmutable( '2000-01-03', $zone ) )->setTime( (int) $m[1], (int) $m[2] );

		return (string) \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::in_site_locale(
			static fn () => wp_date( (string) get_option( 'time_format' ), $time->getTimestamp(), $zone )
		);
	}

	/**
	 * The date and the time the way e-mails print them: "date, time", or the
	 * date alone when the time is missing or unreadable.
	 */
	private static function display_datetime( string $raw_date, string $raw_time ): string {
		$date = self::display_date( $raw_date );
		$time = self::display_time( $raw_time );
		if ( '' === $date || '' === $time ) {
			return $date;
		}

		return (string) \MHMRentiva\Admin\Core\Utilities\SiteLocaleString::resolve(
			/* translators: 1: a date, 2: a time of day (e.g. "November 20, 2026, 9:30 am"). */
			static fn (): string => sprintf( _x( '%1$s, %2$s', 'e-mail date and time', 'mhm-rentiva' ), $date, $time )
		);
	}

	/**
	 * Prepare message context data
	 *
	 * Status, category and thread id are read from the thread's root.
	 *
	 * @param int $message_id Message ID
	 * @return array|null Message context data
	 */
	public static function getMessageContext( int $message_id ): ?array {
		if ( $message_id <= 0 ) {
			return null;
		}

		$post = get_post( $message_id );
		if ( ! $post || $post->post_type !== 'mhmrentiva_message' ) {
			return null;
		}

		$customer_name  = get_post_meta( $message_id, '_mhmrentiva_customer_name', true );
		$customer_email = get_post_meta( $message_id, '_mhmrentiva_customer_email', true );
		// Status, category and thread belong to the thread, and the thread's
		// record of them is its root: a reply's own status is whatever its
		// writer left there (Pro <= 6.2.0 births every admin reply `pending`),
		// so an e-mail about a reply to a closed thread said "pending".
		// Identity, text, sender and booking stay on the given record -- the
		// e-mail is about that message.
		$root_id        = self::resolve_message_root( $message_id );
		$category       = get_post_meta( $root_id, '_mhmrentiva_message_category', true );
		$status         = get_post_meta( $root_id, '_mhmrentiva_message_status', true );
		$thread_id      = $root_id !== $message_id
			? (string) $root_id
			: get_post_meta( $message_id, '_mhmrentiva_thread_id', true );
		$msg_booking_id = (int) get_post_meta( $message_id, '_mhmrentiva_booking_id', true );
		$msg_order_id   = $msg_booking_id ? \MHMRentiva\Admin\Core\Utilities\BookingQueryHelper::resolve_wc_order_id( $msg_booking_id ) : 0;
		$sender_name    = $customer_name ?: __( 'Anonymous', 'mhm-rentiva' );
		// Text, not the stored HTML: Pro saves content through wp_kses_post, and
		// a saved custom body puts {message_body} through the placeholder engine
		// unescaped. Entities are kept (no decode), so the file templates'
		// esc_html() does not double-encode and no `<` is reintroduced. Line
		// breaks and block ends become newlines first, or "<p>a</p><p>b</p>"
		// would arrive as "ab".
		$text = wp_strip_all_tags(
			(string) preg_replace( '#<br\s*/?>|</(?:p|div|li|h[1-6]|blockquote|tr)>#i', "\n", $post->post_content )
		);

		return array(
			'message'  => array(
				'id'         => $message_id,
				'subject'    => $post->post_title,
				'content'    => $post->post_content,
				'date'       => $post->post_date,
				'category'   => $category,
				'status'     => $status,
				'thread_id'  => $thread_id,
				// The names Pro's default message templates read. Aliases of
				// content (as text) and the customer fields, not new data.
				'body'       => $text,
				'reply'      => $text,
				'from_name'  => $sender_name,
				'from_email' => $customer_email,
			),
			'booking'  => array(
				'id'       => $msg_booking_id ?: '',
				'order_id' => ( $msg_order_id ?: $msg_booking_id ) ?: '',
			),
			'customer' => array(
				'name'  => $sender_name,
				'email' => $customer_email,
			),
			'site'     => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => home_url( '/' ),
				'admin_email' => get_option( 'admin_email' ),
			),
		);
	}

	/**
	 * The root of a message's thread, or the message itself when the thread
	 * cannot be resolved (spec v2.4 §5, plan R-2/R-3).
	 *
	 * The root is the record the message's `_mhmrentiva_thread_id` names, in
	 * the canonical form every writer produces: digits only, no leading zero.
	 * The digit check comes before the (int) cast so "12abc" never becomes
	 * record 12. Anything that is not a live message -- a UUID thread id from
	 * the legacy admin form, a deleted or trashed record, another post type --
	 * falls back to the message itself, which is what this reader did before.
	 * Every post_status except `trash` counts as a root (spec §6.1's admin
	 * rule); the customer-side `publish` rule does not apply to e-mail context.
	 *
	 * @param int $message_id Message ID.
	 * @return int Root message ID, or $message_id.
	 */
	private static function resolve_message_root( int $message_id ): int {
		$thread = get_post_meta( $message_id, '_mhmrentiva_thread_id', true );
		if ( ! is_string( $thread ) || ! ctype_digit( $thread ) || '0' === $thread[0] ) {
			return $message_id;
		}

		$root_id = (int) $thread;
		if ( $root_id === $message_id ) {
			return $message_id;
		}

		$root = get_post( $root_id );
		if ( ! $root instanceof \WP_Post || 'mhmrentiva_message' !== $root->post_type || 'trash' === $root->post_status ) {
			return $message_id;
		}

		return $root_id;
	}

	/**
	 * Normalize headers
	 *
	 * @param array $headers Headers array
	 * @return array Normalized headers
	 */
	private static function normalize_headers( array $headers ): array {
		$out = array();
		foreach ( $headers as $h ) {
			if ( ! is_string( $h ) ) {
				continue;
			}
			$h = trim( $h );
			if ( $h !== '' ) {
				$out[] = $h;
			}
		}
		return $out;
	}

	/**
	 * Email sending statistics - optimized version
	 *
	 * @param string $key Template key
	 * @param int    $days Days to look back
	 * @return array Statistics
	 */
	public static function getEmailStats( string $key = '', int $days = 30 ): array {
		// Create cache key
		$cache_key = 'mhmrentiva_email_stats_' . md5( $key . '_' . $days );

		// Check cache
		$cached_stats = get_transient( $cache_key );
		if ( $cached_stats !== false ) {
			return $cached_stats;
		}

		// Check if logging is enabled
		if ( ! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_log_enabled() ) {
			$stats = array(
				'total_sent'   => 0,
				'successful'   => 0,
				'failed'       => 0,
				'success_rate' => 0,
			);

			// 5 minutes cache
			set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );
			return $stats;
		}

		// ⭐ Using WP_Query instead of raw SQL for better maintainability
		$date_threshold = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Build meta query
		$meta_query = array(
			array(
				'key'     => '_mhmrentiva_email_status',
				'compare' => 'EXISTS',
			),
		);

		// Filter by template key if provided
		if ( ! empty( $key ) ) {
			$meta_query[] = array(
				'key'     => '_mhmrentiva_email_key',
				'value'   => $key,
				'compare' => 'LIKE',
			);
		}

		// Query for all emails in date range
		$query = new \WP_Query(
			array(
				'post_type'      => 'mhmrentiva_email_log',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'after'     => $date_threshold,
						'inclusive' => true,
					),
				),
				'meta_query'     => $meta_query,
				'no_found_rows'  => false,
			)
		);

		// Calculate statistics from results
		$total_sent = $query->found_posts ?? 0;
		$successful = 0;
		$failed     = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$status = get_post_meta( $post_id, '_mhmrentiva_email_status', true );
				if ( $status === 'success' ) {
					++$successful;
				} elseif ( $status === 'failed' ) {
					++$failed;
				}
			}
		}

		$stats = (object) array(
			'total_sent' => $total_sent,
			'successful' => $successful,
			'failed'     => $failed,
		);

		$final_stats = array(
			'total_sent'   => (int) $stats->total_sent,
			'successful'   => (int) $stats->successful,
			'failed'       => (int) $stats->failed,
			'success_rate' => $stats->total_sent > 0 ? round( ( $stats->successful / $stats->total_sent ) * 100, 2 ) : 0,
		);

		// 15 minutes cache
		set_transient( $cache_key, $final_stats, 15 * MINUTE_IN_SECONDS );

		return $final_stats;
	}

	/**
	 * Customer communication settings helper methods
	 */
	private static function is_welcome_email_enabled(): bool {
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_customer_welcome_email', '1' ) === '1';
	}

	private static function is_booking_notifications_enabled(): bool {
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_customer_booking_notifications', '1' ) === '1';
	}

	private static function is_marketing_emails_enabled(): bool {
		return \MHMRentiva\Admin\Settings\Core\SettingsCore::get( 'mhmrentiva_customer_marketing_emails', '0' ) === '1';
	}
}
