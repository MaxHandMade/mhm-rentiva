<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Public/legacy hook names kept stable for compatibility.

use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;



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
	 * Seam tags this build ships but the licence does not currently allow.
	 *
	 * Distinct from a seam whose class is absent entirely: those tags never
	 * existed on this site, but these ones did (the licence lapsed), so real
	 * pages out there still contain them. See render_unlicensed_seams().
	 *
	 * @var array<int, string>
	 */
	private array $unlicensed_seam_tags = array();

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function instance(): self
	{
		return self::$instance ??= new self();
	}

	/**
	 * Private constructor to enforce singleton pattern
	 */
	private function __construct()
	{
		// Protected for singleton
	}

	/**
	 * Boots the service provider and registers all shortcodes
	 *
	 * @return void
	 */
	public static function register(): void
	{
		self::instance()->register_all_shortcodes();
	}

	/**
	 * Returns the complete shortcode registry with filters
	 *
	 * @return array<string, array<string, array>>
	 */
	private function get_shortcode_registry(): array
	{
		$registry = $this->get_raw_shortcode_registry();

		// POC shortcodes are intentionally disabled in production unless explicitly enabled.
		if (! self::is_home_poc_enabled()) {
			unset($registry['support']['rentiva_home_poc']);
		}

		$registry = $this->drop_absent_pro_seams($registry);

		return (array) apply_filters('mhm_rentiva_shortcodes', $registry);
	}

	/**
	 * The registry as DECLARED, before any seam is dropped.
	 *
	 * Split out from get_shortcode_registry() so the seam metadata can be audited
	 * as data. The declared table is the only place that says which licence feature
	 * each seam needs, and get_shortcode_registry() returns the table with those
	 * seams already REMOVED -- so a test asking it "does every seam declare a
	 * feature?" sees an empty set and passes vacuously. That is not hypothetical:
	 * SeamFeatureKeyCoverageTest's premise guard caught exactly that.
	 *
	 * @return array<string, array<string, array>>
	 */
	private function get_raw_shortcode_registry(): array
	{
		return array(
			'reservation' => array(
				'rentiva_booking_form'          => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::class,
					'dependencies'  => array( 'deposit' ),
					'requires_auth' => false,
				),
				'rentiva_availability_calendar' => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::class,
					'dependencies'  => array( 'booking_form' ),
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
				'rentiva_featured_vehicles'  => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
				'rentiva_vehicles_grid'      => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::class,
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
				'rentiva_unified_search'     => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\UnifiedSearch::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
			),
			/*
			 * Every entry here is Vendor Marketplace, so every entry carries
			 * `pro_feature`. They all shipped WITHOUT one, which did not merely
			 * mislabel them -- it handed the feature away: `pro_seam` alone routes
			 * to Mode::allowsSeam(null), which returns true by contract, so on a
			 * Pro-installed-but-unlicensed site the class existed, the licence
			 * check passed, and the vendor shortcodes registered with their REAL
			 * renderers. Confirmed at runtime with isPro=false before the fix.
			 */
			'vendor'      => array(
				'rentiva_vendor_apply'     => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorApply',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
					'requires_auth' => false,
				),
				'rentiva_vehicle_submit'   => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VehicleSubmit',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
					'requires_auth' => false,
				),
				'rentiva_vendor_bookings'  => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Account\VendorBookings',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
					'requires_auth' => true,
				),
				'rentiva_vendor_profile'   => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
					'requires_auth' => false,
				),
				'rentiva_vendor_directory' => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
					'requires_auth' => false,
				),
			),
			'account'     => array(
				'rentiva_user_dashboard'      => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\Account\UserDashboard::class,
					'method'        => 'render',
					'requires_auth' => false,
				),
				'rentiva_my_bookings'         => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\Account\MyBookings::class,
					'method'        => 'render',
					'requires_auth' => true,
				),
				'rentiva_my_favorites'        => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\Account\MyFavorites::class,
					'method'        => 'render',
					'requires_auth' => true,
				),
				'rentiva_payment_history'     => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\Account\PaymentHistory::class,
					'method'        => 'render',
					'requires_auth' => true,
				),
				// Commission is a Vendor Marketplace concept: no vendors, nothing to
				// resolve a commission for. Same missing-key bug as the vendor group.
				'rentiva_commission_resolver' => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\CommissionResolver',
					'method'        => 'render',
					'requires_auth' => false,
					'pro_seam'      => true,
					'pro_feature'   => 'vendor_marketplace',
				),
			),
			'transfer'    => array(
				'rentiva_transfer_search'  => array(
					'class'         => 'MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes',
					'dependencies'  => array(),
					'pro_seam'      => true,
					'pro_feature'   => 'pro',
					'requires_auth' => false,
				),
				'rentiva_transfer_results' => array(
					'class'         => 'MHMRentiva\Admin\Transfer\Frontend\TransferResults',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'pro',
					'requires_auth' => false,
				),
				'rentiva_popular_routes'   => array(
					'class'         => 'MHMRentiva\Admin\Transfer\Frontend\PopularRoutesShortcode',
					'method'        => 'render',
					'pro_seam'      => true,
					'pro_feature'   => 'pro',
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
				// Messaging is its own licence feature (Mode::canUseMessages()), and
				// the menu entry has always asked for it -- only this registry
				// entry forgot, so the admin screen was gated while the public
				// shortcode was not.
				'rentiva_messages'            => array(
					'class'         => 'MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages',
					'method'        => 'render',
					'requires_auth' => true,
					'pro_seam'      => true,
					'pro_feature'   => 'messaging',
				),
				'rentiva_home_poc'            => array(
					'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\HomePoc::class,
					'dependencies'  => array(),
					'requires_auth' => false,
				),
			),
		);
	}

	/**
	 * Drops Pro seam entries whose class this build does not ship.
	 *
	 * Lite carves the Pro shortcodes out entirely, so their registry entries must
	 * disappear with them. Filtering here -- at the single source of the registry
	 * -- keeps every consumer honest at once: process_registration() would
	 * otherwise log a "class not found" error on every request for a feature this
	 * build was never meant to have, and get_total_count()/get_groups() would
	 * advertise shortcodes that cannot render.
	 *
	 * Shipping the class is necessary but NOT sufficient: with Pro installed but
	 * unlicensed the class exists, so a presence-only check handed the full Pro UI
	 * to an unlicensed site for free. Entries carrying a `pro_feature` key must
	 * therefore satisfy the licence gate as well.
	 *
	 * @param array<string, array<string, array>> $registry Full registry.
	 * @return array<string, array<string, array>> Registry without absent seams.
	 */
	private function drop_absent_pro_seams(array $registry): array
	{
		$this->unlicensed_seam_tags = array();

		foreach ($registry as $group => $shortcodes) {
			foreach ($shortcodes as $tag => $config) {
				if (empty($config['pro_seam'])) {
					continue;
				}

				$class_present = class_exists( (string) ( $config['class'] ?? '' ) );

				/*
				 * FAIL CLOSED. A `pro_seam` entry with no `pro_feature` used to fall
				 * through to Mode::allowsSeam(null), which returns true by contract --
				 * so declaring something a Pro seam and forgetting its feature key
				 * GAVE IT AWAY. Seven entries shipped exactly that way (the whole
				 * vendor group, commission_resolver and messages): on a
				 * Pro-installed-but-unlicensed site they registered with their real
				 * renderers and worked for free.
				 *
				 * The default is now the whole edition ('pro'), so the failure mode of
				 * a forgotten key is a closed feature rather than a free one. Explicit
				 * keys still refine it to a specific licence feature.
				 */
				$licensed = \MHMRentiva\Admin\Licensing\Mode::allowsSeam(
					isset($config['pro_feature']) ? (string) $config['pro_feature'] : 'pro'
				);

				if ($class_present && $licensed) {
					continue;
				}

				// Dropping is what keeps the feature closed: process_registration()
				// calls $class::register(), so merely swapping the render callback
				// would still hand over the class's own hooks and AJAX handlers.
				unset($registry[ $group ][ $tag ]);

				if ($class_present && ! $licensed) {
					$this->unlicensed_seam_tags[] = $tag;
				}
			}
		}

		return $registry;
	}

	/**
	 * Silence the tags a lapsed licence just closed.
	 *
	 * An unregistered shortcode does not vanish -- WordPress prints its literal
	 * source text. A Lite-only build never had these pages, so dropping the tag is
	 * enough there. But a site whose Pro licence lapsed still has real pages
	 * carrying [rentiva_transfer_search], and those pages would start showing that
	 * raw string to visitors. Registering a no-op renderer keeps them silent while
	 * the feature itself stays closed: nothing here loads or touches the Pro class.
	 *
	 * @return void
	 */
	private function render_unlicensed_seams(): void
	{
		foreach ($this->unlicensed_seam_tags as $tag) {
			add_shortcode($tag, '__return_empty_string');
		}
	}

	/**
	 * Determine if Home POC shortcode registration is enabled.
	 *
	 * Enable conditions:
	 * - WP_DEBUG is enabled, OR
	 * - MHM_RENTIVA_ENABLE_HOME_POC constant is explicitly true.
	 */
	private static function is_home_poc_enabled(): bool
	{
		if (defined('MHM_RENTIVA_ENABLE_HOME_POC')) {
			return (bool) constant('MHM_RENTIVA_ENABLE_HOME_POC');
		}

		return defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * Iterates over the registry and registers each shortcode
	 *
	 * @return void
	 */
	private function register_all_shortcodes(): void
	{
		$registry = $this->get_shortcode_registry();
		foreach ($registry as $group => $shortcodes) {
			foreach ($shortcodes as $tag => $config) {
				$this->process_registration($tag, $config);
			}
		}

		$this->render_unlicensed_seams();
	}

	/**
	 * Performs validation and calls WordPress add_shortcode
	 *
	 * @param string $tag    The shortcode tag.
	 * @param array  $config Configuration for the shortcode.
	 * @return void
	 */
	private function process_registration(string $tag, array $config): void
	{
		$class = $config['class'] ?? '';

		// Skip if class is not provided or doesn't exist
		if (empty($class) || ! class_exists($class)) {
			$this->log_error(sprintf('Shortcode class not found: %s', (string) $class));
			return;
		}

		// Check defined dependencies
		if (! empty($config['dependencies']) && ! $this->check_dependencies($config['dependencies'])) {
			$this->log_error(sprintf('Shortcode dependencies not met for tag: %s', $tag));
			return;
		}

		// Register class-internal hooks and AJAX handlers
		if (method_exists($class, 'register') && ! isset($this->initialized_classes[ $class ])) {
			$this->initialized_classes[ $class ] = true;
			$class::register();
		}

		// Register the shortcode
		$callback = $this->resolve_callback($class, $config);

		if ($callback && is_callable($callback)) {
			$this->register_tag($tag, $callback, $config);
			$this->registered_shortcodes[ $tag ] = $config;
		} else {
			$this->log_error(sprintf('No valid callback found for shortcode: %s', $tag));
		}
	}

	/**
	 * Internal helper to actually call add_shortcode with safety wrapper
	 */
	private function register_tag(string $tag, callable $callback, array $config): void
	{
		add_shortcode(
			$tag,
			function ($atts, $content = null) use ($tag, $callback, $config) {
				return $this->handle_shortcode_execution($tag, $callback, $config, $atts, $content);
			}
		);
	}

	/**
	 * Resolves the proper callback for the shortcode
	 *
	 * @param string $class
	 * @param array  $config
	 * @return callable|null
	 */
	private function resolve_callback(string $class, array $config): ?callable
	{
		$method = $config['method'] ?? 'render'; // Default to 'render' if not specified

		if (! method_exists($class, $method)) {
			return null;
		}

		try {
			$reflection = new \ReflectionMethod($class, $method);
			if ($reflection->isStatic()) {
				return array( $class, $method );
			}

			// Singleton or Instance injection
			if (! isset($this->class_instances[ $class ])) {
				$this->class_instances[ $class ] = method_exists($class, 'instance') ? $class::instance() : new $class();
			}

			return array( $this->class_instances[ $class ], $method );
		} catch (\ReflectionException $e) {
			$this->log_error(sprintf('Reflection error for %s::%s: %s', $class, $method, $e->getMessage()));
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
	private function handle_shortcode_execution(string $tag, callable $callback, array $config, $atts, ?string $content): string
	{
		// Check authentication if required
		if (! empty($config['requires_auth']) && ! is_user_logged_in()) {
			return (string) apply_filters('mhm_rentiva_shortcode_auth_error', __('Please login to view this content.', 'mhm-rentiva'), $tag);
		}

		ob_start();
		$output   = call_user_func($callback, $atts, $content, $tag);
		$buffered = ob_get_clean();

		return $output ?? $buffered;
	}

	/**
	 * Validates if required classes for dependencies are available
	 *
	 * @param string[] $dependencies List of dependency keys.
	 * @return bool
	 */
	private function check_dependencies(array $dependencies): bool
	{
		$map = array(
			'deposit'      => \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::class,
			'booking'      => \MHMRentiva\Admin\Booking\Core\Handler::class,
			'email'        => \MHMRentiva\Admin\Emails\Core\EmailTemplates::class,
			'booking_form' => \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::class,
		);

		foreach ($dependencies as $dependency) {
			if (isset($map[ $dependency ])) {
				if (! class_exists($map[ $dependency ])) {
					return false;
				}
			} else {
				$this->log_error(sprintf('Unknown dependency check requested: %s', $dependency));
			}
		}

		return true;
	}

	/**
	 * Get all registered shortcodes
	 *
	 * @return array
	 */
	public function get_registered_shortcodes(): array
	{
		return $this->registered_shortcodes;
	}

	/**
	 * Returns grouped shortcode data for UI/Admin use
	 *
	 * @return array
	 */
	public static function get_shortcode_groups(): array
	{
		$groups   = array();
		$instance = self::instance();
		$registry = $instance->get_shortcode_registry();

		foreach ($registry as $group => $shortcodes) {
			$groups[ $group ] = array(
				'name'       => $instance->get_group_name($group),
				'shortcodes' => array_keys($shortcodes),
				'count'      => count($shortcodes),
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
	private function get_group_name(string $group): string
	{
		$names = array(
			'reservation' => __('Booking', 'mhm-rentiva'),
			'vehicle'     => __('Vehicle Display', 'mhm-rentiva'),
			'account'     => __('Account Management', 'mhm-rentiva'),
			'support'     => __('Support and Contact', 'mhm-rentiva'),
			'transfer'    => __('Transfer Services', 'mhm-rentiva'),
		);

		return $names[ $group ] ?? ucfirst($group);
	}

	/**
	 * Logs errors if WP_DEBUG is enabled
	 *
	 * @param string $message
	 * @return void
	 */
	private function log_error(string $message): void
	{
		if (class_exists('\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger')) {
			\MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger::error('Shortcode Error', array( 'message' => $message ));
		}
	}

	/**
	 * Returns the count of all available shortcodes in registry
	 *
	 * @return int
	 */
	public static function get_total_count(): int
	{
		$count    = 0;
		$registry = self::instance()->get_shortcode_registry();
		foreach ($registry as $shortcodes) {
			$count += count($shortcodes);
		}
		return $count;
	}
}
