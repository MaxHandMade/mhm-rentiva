<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode Service Provider
 *
 * Manages all shortcode registrations, dependency checks, and access control.
 *
 * @package MHMRentiva\Admin\Core
 * @since 3.0.1
 */
final class ShortcodeServiceProvider {

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Tracks successfully registered shortcodes
	 *
	 * @var array<string, array>
	 */
	private array $registered_shortcodes = array();

	/**
	 * Tracks initialized classes to prevent double registration
	 *
	 * @var array<string, bool>
	 */
	private array $initialized_classes = array();

	/**
	 * Cache for class instances
	 *
	 * @var array<string, object>
	 */
	private array $class_instances = array();

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Private constructor to enforce singleton pattern
	 */
	private function __construct() {
		// Protected for singleton
	}

	/**
	 * Boots the service provider and registers all shortcodes
	 *
	 * @return void
	 */
	public static function register(): void {
		self::instance()->register_all_shortcodes();
	}

	/**
	 * Returns the complete shortcode registry with filters
	 *
	 * @return array<string, array<string, array>>
	 */
	private function get_shortcode_registry(): array {
		$registry = array(
			'reservation' => array(
				'rentiva_booking_form'          => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::class,
					'dependencies'  => array( 'deposit' ),
					'requires_auth' => false,
				),
				'rentiva_availability_calendar' => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_booking_confirmation'  => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\BookingConfirmation::class,
					'dependencies'  => array( 'booking' ),
					'requires_auth' => false,
				),
			),
			'vehicle'     => array(
				'rentiva_vehicle_details'    => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_vehicles_list'      => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_vehicles_grid'      => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_search'             => array(
					'class'         => \MHMRentiva\Admin\Vehicle\Frontend\VehicleSearch::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_search_results'     => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_vehicle_comparison' => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehicleComparison::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
			),
			'account'     => array(
				'rentiva_my_bookings'     => array(
					'class'         => \MHMRentiva\Admin\Frontend\Account\AccountController::class,
					'method'        => 'render_my_bookings',
					'requires_auth' => true,
				),
				'rentiva_my_favorites'    => array(
					'class'         => \MHMRentiva\Admin\Frontend\Account\AccountController::class,
					'method'        => 'render_my_favorites',
					'requires_auth' => true,
				),
				'rentiva_payment_history' => array(
					'class'         => \MHMRentiva\Admin\Frontend\Account\AccountController::class,
					'method'        => 'render_payment_history',
					'requires_auth' => true,
				),

			),
			'transfer'    => array(
				'mhm_rentiva_transfer_search' => array(
					'class'         => \MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes::class,
					'method'        => 'render_search_shortcode',
					'requires_auth' => false,
				),
			),
			'support'     => array(
				'rentiva_contact'             => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\ContactForm::class,
					'dependencies'  => array( 'email' ),
					'requires_auth' => false,
				),
				'rentiva_testimonials'        => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::class,
					'dependencies'  => array( 'booking' ),
					'requires_auth' => false,
				),
				'rentiva_vehicle_rating_form' => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehicleRatingForm::class,
					'dependencies'  => array( 'booking' ),
					'requires_auth' => false,
				),
				'rentiva_messages'            => array(
					'class'         => \MHMRentiva\Admin\Frontend\Account\AccountController::class,
					'method'        => 'render_messages',
					'requires_auth' => true,
				),
			),
		);

		return (array) apply_filters( 'mhm_rentiva_shortcodes', $registry );
	}

	/**
	 * Iterates over the registry and registers each shortcode
	 *
	 * @return void
	 */
	private function register_all_shortcodes(): void {
		$registry = $this->get_shortcode_registry();
		foreach ( $registry as $group => $shortcodes ) {
			foreach ( $shortcodes as $tag => $config ) {
				$this->process_registration( $tag, $config );
			}
		}
	}

	/**
	 * Performs validation and calls WordPress add_shortcode
	 *
	 * @param string $tag    The shortcode tag.
	 * @param array  $config Configuration for the shortcode.
	 * @return void
	 */
	private function process_registration( string $tag, array $config ): void {
		$class = $config['class'] ?? '';

		// Skip if class is not provided or doesn't exist
		if ( empty( $class ) || ! class_exists( $class ) ) {
			$this->log_error( sprintf( 'Shortcode class not found: %s', (string) $class ) );
			return;
		}

		// Check defined dependencies
		if ( ! empty( $config['dependencies'] ) && ! $this->check_dependencies( $config['dependencies'] ) ) {
			$this->log_error( sprintf( 'Shortcode dependencies not met for tag: %s', $tag ) );
			return;
		}

		// Handle classes with their own register method (Legacy compatibility)
		if ( method_exists( $class, 'register' ) && ! isset( $this->initialized_classes[ $class ] ) ) {
			$class::register();
			$this->initialized_classes[ $class ] = true;
			// Note: We don't return here because we want to track it in $registered_shortcodes
		}

		// Determine callback
		$callback = $this->resolve_callback( $class, $config );

		if ( $callback && is_callable( $callback ) ) {
			// Wrap callback for security/auth checks
			add_shortcode(
				$tag,
				function ( $atts, $content = null ) use ( $tag, $callback, $config ) {
					return $this->handle_shortcode_execution( $tag, $callback, $config, $atts, $content );
				}
			);

			$this->registered_shortcodes[ $tag ] = $config;
		} else {
			$this->log_error( sprintf( 'No valid callback found for shortcode: %s', $tag ) );
		}
	}

	/**
	 * Resolves the proper callback for the shortcode
	 *
	 * @param string $class
	 * @param array  $config
	 * @return callable|null
	 */
	private function resolve_callback( string $class, array $config ): ?callable {
		$method = $config['method'] ?? 'render'; // Default to 'render' if not specified

		if ( ! method_exists( $class, $method ) ) {
			return null;
		}

		try {
			$reflection = new \ReflectionMethod( $class, $method );
			if ( $reflection->isStatic() ) {
				return array( $class, $method );
			}

			// Singleton or Instance injection
			if ( ! isset( $this->class_instances[ $class ] ) ) {
				$this->class_instances[ $class ] = method_exists( $class, 'instance' ) ? $class::instance() : new $class();
			}

			return array( $this->class_instances[ $class ], $method );
		} catch ( \ReflectionException $e ) {
			$this->log_error( sprintf( 'Reflection error for %s::%s: %s', $class, $method, $e->getMessage() ) );
			return null;
		}
	}

	/**
	 * Wraps the shortcode execution for global logic (e.g., auth check)
	 *
	 * @param string      $tag
	 * @param callable    $callback
	 * @param array       $config
	 * @param mixed       $atts
	 * @param string|null $content
	 * @return string
	 */
	private function handle_shortcode_execution( string $tag, callable $callback, array $config, $atts, ?string $content ): string {
		// Check authentication if required
		if ( ! empty( $config['requires_auth'] ) && ! is_user_logged_in() ) {
			return (string) apply_filters( 'mhm_rentiva_shortcode_auth_error', __( 'Please login to view this content.', 'mhm-rentiva' ), $tag );
		}

		ob_start();
		$output   = call_user_func( $callback, $atts, $content, $tag );
		$buffered = ob_get_clean();

		return $output ?? $buffered;
	}

	/**
	 * Validates if required classes for dependencies are available
	 *
	 * @param string[] $dependencies List of dependency keys.
	 * @return bool
	 */
	private function check_dependencies( array $dependencies ): bool {
		$map = array(
			'deposit' => \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::class,
			'booking' => \MHMRentiva\Admin\Booking\Core\Handler::class,
			'email'   => \MHMRentiva\Admin\Emails\Core\EmailTemplates::class,
		);

		foreach ( $dependencies as $dependency ) {
			if ( isset( $map[ $dependency ] ) ) {
				if ( ! class_exists( $map[ $dependency ] ) ) {
					return false;
				}
			} else {
				$this->log_error( sprintf( 'Unknown dependency check requested: %s', $dependency ) );
			}
		}

		return true;
	}

	/**
	 * Get all registered shortcodes
	 *
	 * @return array
	 */
	public function get_registered_shortcodes(): array {
		return $this->registered_shortcodes;
	}

	/**
	 * Returns grouped shortcode data for UI/Admin use
	 *
	 * @return array
	 */
	public static function get_shortcode_groups(): array {
		$groups   = array();
		$instance = self::instance();
		$registry = $instance->get_shortcode_registry();

		foreach ( $registry as $group => $shortcodes ) {
			$groups[ $group ] = array(
				'name'       => $instance->get_group_name( $group ),
				'shortcodes' => array_keys( $shortcodes ),
				'count'      => count( $shortcodes ),
			);
		}

		return $groups;
	}

	/**
	 * Returns translated group names
	 *
	 * @param string $group
	 * @return string
	 */
	private function get_group_name( string $group ): string {
		$names = array(
			'reservation' => __( 'Booking', 'mhm-rentiva' ),
			'vehicle'     => __( 'Vehicle Display', 'mhm-rentiva' ),
			'account'     => __( 'Account Management', 'mhm-rentiva' ),
			'support'     => __( 'Support and Contact', 'mhm-rentiva' ),
			'transfer'    => __( 'Transfer Services', 'mhm-rentiva' ),
		);

		return $names[ $group ] ?? ucfirst( $group );
	}

	/**
	 * Logs errors if WP_DEBUG is enabled
	 *
	 * @param string $message
	 * @return void
	 */
	private function log_error( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'MHM Rentiva Error: %s', $message ) );
		}
	}

	/**
	 * Returns the count of all available shortcodes in registry
	 *
	 * @return int
	 */
	public static function get_total_count(): int {
		$count    = 0;
		$registry = self::instance()->get_shortcode_registry();
		foreach ( $registry as $shortcodes ) {
			$count += count( $shortcodes );
		}
		return $count;
	}
}
