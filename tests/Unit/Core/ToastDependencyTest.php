<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * T8 F06-F08 (independent audit): 8 shipped JS files make 57 unguarded
 * `MHMRentivaToast.*` calls (assets/js/frontend/{vehicle-rating-form,my-account,
 * availability-calendar,vehicle-interactions,search-results,booking-form,
 * vehicles-grid,account-privacy}.js), but toast.js's own actual page-print used
 * to depend entirely on whichever consumer's registered script declared
 * 'mhm-rentiva-toast' in its deps -- WordPress only auto-loads a registered
 * dependency where some enqueued handle lists it. Where a consumer's deps array
 * omitted it, `window.MHMRentivaToast` was undefined on that page and every
 * MHMRentivaToast.* call threw ReferenceError, silently killing all user
 * feedback (success/error toasts) on that screen.
 *
 * Root cause re-measured against the brief's premise: AssetManager's own
 * registration of the 'mhm-rentiva-toast' handle (:286-294, inside
 * register_common_assets()) is ALREADY unconditional -- register_common_assets()
 * is hooked directly on `init` (AssetManager::init():139), never gated behind
 * should_load_assets()'s post_content grep. Git blame confirms this is original
 * code (commit 9a322156, 2026-03-24), not something a prior T8 task added. So
 * "register the handle unconditionally" was already true; the actual gap is
 * entirely in the 8 files' enqueue sites, most of which never listed the toast
 * handle in their own deps array. There is also no double-registration risk:
 * 'mhm-rentiva-toast' has exactly one wp_register_script() call site in the
 * whole plugin (AssetManager.php:287-293).
 *
 * The map (re-measured, not trusted from the brief) found MORE enqueue sites
 * than "8 files = 8 sites": two files are carried by more than one registered
 * handle, and two handles were already correct before this task touched
 * anything --
 *
 * | site (trigger key)          | handle                             | file:line (pre-fix deps)                                          | pre-fix state |
 * |------------------------------|-------------------------------------|--------------------------------------------------------------------|---------------|
 * | vehicle_rating_form          | mhm-rentiva-vehicle-rating-form     | AbstractShortcode.php:367-370 (base default; VehicleRatingForm does NOT override get_js_dependencies()) | already had toast |
 * | my_account_via_controller    | mhm-rentiva-my-account              | AccountController.php:362-368 (['jquery'])                        | MISSING |
 * | my_account_via_shortcode     | mhm-rentiva-my-account (same handle)| AbstractAccountShortcode.php:43-49 (['jquery'])                   | MISSING |
 * | availability_calendar        | mhm-rentiva-availability-calendar   | AvailabilityCalendar.php:253-256 get_js_dependencies() (['jquery']) | MISSING |
 * | vehicle_interactions         | mhm-rentiva-vehicle-interactions    | AssetManager.php:275-283 (['jquery','mhm-rentiva-core-js','mhm-rentiva-toast']) | already had toast |
 * | search_results               | mhm-rentiva-search-results-js       | SearchResults.php:296-302 inline (['jquery','mhm-rentiva-vehicle-interactions']) | MISSING (direct) |
 * | booking_form                 | mhm-rentiva-booking-form            | BookingForm.php:180-183 get_js_dependencies() (['jquery','jquery-ui-datepicker']) | MISSING |
 * | vehicles_grid                | mhm-rentiva-vehicles-grid           | VehiclesGrid.php:334-340 inline, LIVE (['jquery'])                 | MISSING |
 * | my_favorites                 | mhm-rentiva-my-favorites            | MyFavorites.php:126-129 get_js_dependencies(), reuses vehicles-grid.js (['jquery','mhm-rentiva-vehicle-interactions']) | MISSING (direct) |
 * | account_privacy              | mhm-rentiva-account-privacy         | AccountController.php:239-245, GDPR-addon-gated (['jquery'])       | MISSING |
 *
 * `my-account.js` has TWO independent live registration sites for the SAME
 * handle name (AccountController's wp_enqueue_scripts hook vs
 * AbstractAccountShortcode's per-shortcode-render path) -- both had to be
 * fixed, because "first wins" for wp_register_script(), and either can run
 * first depending on which loads the page. `vehicles-grid.js` is reused
 * verbatim under a SECOND shortcode (MyFavorites::get_js_files() override) with
 * its own handle and its own (also-missing) deps array. VehiclesGrid.php ALSO
 * carries a third, textually-present get_js_dependencies() override (:539-542)
 * that is provably DEAD CODE -- VehiclesGrid::enqueue_assets() (:323-364) is a
 * full override that never calls enqueue_scripts()/get_js_dependencies() at
 * all, so nothing in the live request path ever reads it. Fixed anyway for
 * consistency (see test_vehicles_grid_dead_get_js_dependencies_override_also_lists_toast
 * below) since it is a one-line, same-file, zero-risk edit that stops the
 * method from lying about what the class actually does -- covered by its own
 * narrow reflection test, deliberately NOT part of the dataProvider below
 * (which is scoped to REGISTERED handles on the live path, per the brief).
 *
 * @covers \MHMRentiva\Admin\Core\AssetManager::register_common_assets
 * @covers \MHMRentiva\Admin\Frontend\Account\AccountController::enqueue_assets
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode::get_js_dependencies
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Account\AbstractAccountShortcode::enqueue_assets
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::get_js_dependencies
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::enqueue_assets
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::get_js_dependencies
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::enqueue_assets
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::get_js_dependencies
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\Account\MyFavorites::get_js_dependencies
 */
final class ToastDependencyTest extends WP_UnitTestCase {

	private const TOAST_HANDLE = 'mhm-rentiva-toast';

	/** Every handle any trigger() branch below can register. */
	private const CONSUMER_HANDLES = array(
		'mhm-rentiva-vehicle-rating-form',
		'mhm-rentiva-my-account',
		'mhm-rentiva-availability-calendar',
		'mhm-rentiva-vehicle-interactions',
		'mhm-rentiva-search-results-js',
		'mhm-rentiva-booking-form',
		'mhm-rentiva-vehicles-grid',
		'mhm-rentiva-my-favorites',
		'mhm-rentiva-account-privacy',
	);

	protected function setUp(): void {
		parent::setUp();
		$this->reset_handles();
		\MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode::reset_enqueued_assets_for_tests();
	}

	protected function tearDown(): void {
		$this->reset_handles();
		remove_action( 'wp_ajax_mhmrentiva_data_export', '__return_true' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * The map, exercised: one row per real enqueue site. Each site's trigger is
	 * whatever a real page load actually does to reach it (do_shortcode() for
	 * shortcode-rendered consumers, direct method calls for the two
	 * AccountController-driven handles and the AssetManager registration site).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function consumer_site_provider(): array {
		return array(
			'vehicle_rating_form -- AbstractShortcode base default (no override)'        => array( 'vehicle_rating_form', 'mhm-rentiva-vehicle-rating-form' ),
			'my_account via AccountController::enqueue_assets() (:362-368)'              => array( 'my_account_via_controller', 'mhm-rentiva-my-account' ),
			'my_account via AbstractAccountShortcode::enqueue_assets() (:43-49)'         => array( 'my_account_via_shortcode', 'mhm-rentiva-my-account' ),
			'availability_calendar -- AvailabilityCalendar::get_js_dependencies()'       => array( 'availability_calendar', 'mhm-rentiva-availability-calendar' ),
			'vehicle_interactions -- AssetManager::register_common_assets()'             => array( 'vehicle_interactions', 'mhm-rentiva-vehicle-interactions' ),
			'search_results -- SearchResults::enqueue_assets() inline'                   => array( 'search_results', 'mhm-rentiva-search-results-js' ),
			'booking_form -- BookingForm::get_js_dependencies()'                         => array( 'booking_form', 'mhm-rentiva-booking-form' ),
			'vehicles_grid -- VehiclesGrid::enqueue_assets() inline, live path'          => array( 'vehicles_grid', 'mhm-rentiva-vehicles-grid' ),
			'my_favorites -- MyFavorites::get_js_dependencies(), reuses vehicles-grid.js' => array( 'my_favorites', 'mhm-rentiva-my-favorites' ),
			'account_privacy -- AccountController::enqueue_assets(), GDPR-addon-gated'   => array( 'account_privacy', 'mhm-rentiva-account-privacy' ),
		);
	}

	/**
	 * @dataProvider consumer_site_provider
	 */
	public function test_consumer_handle_declares_toast_dependency( string $site, string $handle ): void {
		$this->trigger( $site );

		$this->assertArrayHasKey(
			$handle,
			wp_scripts()->registered,
			"Premise failed: triggering '$site' did not register '$handle' at all -- fix the trigger, not the assertion below."
		);

		$this->assertContains(
			self::TOAST_HANDLE,
			wp_scripts()->registered[ $handle ]->deps,
			"'$handle' (site: $site) ships a file that calls MHMRentivaToast.* but does not declare '" . self::TOAST_HANDLE . "' as a script dependency -- WordPress will not auto-load toast.js on any page where this is the only handle pulling it in, so every MHMRentivaToast.* call throws ReferenceError there."
		);
	}

	/**
	 * VehiclesGrid::get_js_dependencies() (:539-542) is DEAD -- see class docblock.
	 * Fixed for consistency; verified directly via reflection since nothing in the
	 * live request path ever calls it (that is exactly why it needs its own test
	 * instead of a dataProvider row: no do_shortcode() trigger can reach it).
	 */
	public function test_vehicles_grid_dead_get_js_dependencies_override_also_lists_toast(): void {
		$method = new ReflectionMethod( VehiclesGrid::class, 'get_js_dependencies' );
		$method->setAccessible( true );

		$this->assertContains( self::TOAST_HANDLE, $method->invoke( null ) );
	}

	private function trigger( string $site ): void {
		switch ( $site ) {
			case 'vehicle_rating_form':
				do_shortcode( '[rentiva_vehicle_rating_form]' );
				break;

			case 'availability_calendar':
				do_shortcode( '[rentiva_availability_calendar]' );
				break;

			case 'booking_form':
				do_shortcode( '[rentiva_booking_form]' );
				break;

			case 'search_results':
				do_shortcode( '[rentiva_search_results]' );
				break;

			case 'vehicles_grid':
				do_shortcode( '[rentiva_vehicles_grid]' );
				break;

			case 'my_favorites':
				$this->log_in_subscriber();
				do_shortcode( '[rentiva_my_favorites]' );
				break;

			case 'my_account_via_controller':
				AccountController::enqueue_assets();
				break;

			case 'my_account_via_shortcode':
				// PaymentHistory adds no extra CSS/JS of its own on top of
				// AbstractAccountShortcode::enqueue_assets(), so a pass here can only
				// be attributed to that one shared parent method.
				$this->log_in_subscriber();
				do_shortcode( '[rentiva_payment_history]' );
				break;

			case 'account_privacy':
				// AccountController::enqueue_assets() only enqueues account-privacy.js
				// when the GDPR add-on's AJAX handler is registered (has_action check,
				// AccountController.php:238) -- faked here since Lite alone never
				// registers it.
				add_action( 'wp_ajax_mhmrentiva_data_export', '__return_true' );
				AccountController::enqueue_assets();
				break;

			case 'vehicle_interactions':
				AssetManager::register_common_assets();
				break;

			default:
				self::fail( "Unknown trigger site: $site" );
		}
	}

	private function log_in_subscriber(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
	}

	private function reset_handles(): void {
		foreach ( self::CONSUMER_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}
	}
}
