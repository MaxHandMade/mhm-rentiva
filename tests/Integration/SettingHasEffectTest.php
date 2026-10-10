<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingHasEffectGate;
use MHMRentiva\Tests\Support\SettingProbe;
use WP_UnitTestCase;

/**
 * Gate G1 for Lite's 16 blocks and 17 widgets (the gate itself: SettingHasEffectGate).
 *
 * The probe renders ~590 surfaces once per process (about 100 s on the dev
 * container); the results are shared by every test method of the class.
 */
final class SettingHasEffectTest extends WP_UnitTestCase
{
	use SettingHasEffectGate;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the Elementor half of the gate would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	protected function gate_blocks(): ?array
	{
		return null;
	}

	protected function gate_widgets(): ?array
	{
		return null;
	}

	protected function gate_dir(): string
	{
		return __DIR__;
	}

	/**
	 * hide_nav: AccountRenderer::get_navigation() returns [] whenever WooCommerce is
	 * loaded, so these rows can not render an effect here; HideNavHasEffectTest
	 * proves it link by link. Pinned by equality (SettingHasEffectGate).
	 */
	public const UNAVAILABLE_UNDER_WOOCOMMERCE = array(
		'block|rentiva_my_bookings|hide_nav',
		'elementor|rentiva_my_bookings|hide_nav',
		'block|rentiva_payment_history|hide_nav',
		'elementor|rentiva_payment_history|hide_nav',
	);

	protected function gate_unavailable(): array
	{
		return self::UNAVAILABLE_UNDER_WOOCOMMERCE;
	}

	/**
	 * Surface counts on 2026-10-10 (Task 0: 278 / 313). Lower them only together
	 * with a deliberate removal of settings.
	 * Task 1 (contact): -7 / -7, the seven REMOVE keys (block attribute and its
	 * Elementor parity control each).
	 * Task 2 (card): Elementor -7, the vehicle card widget's button_style,
	 * custom_rating, enable_animation, rating_position, price_format, show_badges
	 * and show_availability controls.
	 * Task 3 (list, grid, featured): block -10 (list priceFormat, showBadges,
	 * showAvailability; grid showAvailability, showBadges, showDescription; featured
	 * priceFormat, showAvailability, showBadges, title) and Elementor -11 (their ten
	 * parity controls, plus featured show_book_button rebound to show_booking_button,
	 * which replaces that key's parity control).
	 * Task 4 (details, comparison): block -11 (details showAvailability,
	 * showBreadcrumb, showShareButtons, showTechnicalSpecs; comparison showCategory,
	 * showFeatures, showFuelType, showRating, showSeats, showTechnicalSpecs,
	 * showTransmission) and Elementor -11 (ten parity controls plus the comparison
	 * "Show Features" select). Ruling T4-1: block -1 / Elementor -1 more
	 * (details showBookingForm and its parity control; the editor toggle now
	 * writes showBooking, which Elementor already has as its own switcher).
	 * Task 5 (search results, unified search): block -7 (search-results sortBy,
	 * sortOrder, showAvailability; unified-search filter_categories, layout,
	 * minwidth, maxwidth) and Elementor -7 (search results orderby and order, which
	 * one own default_sort select replaces in place of its parity control, and the
	 * show_availability parity control; unified search "Layout Style" rebound from
	 * layout to search_layout, which replaces that key's parity control, and the
	 * filter_categories, minwidth and maxwidth parity controls).
	 * Task 6 (availability calendar, booking form): block -12 (calendar
	 * showBookingLinks, showTodayButton, showWeekNumbers, startDate,
	 * showSeasonalPrices, integratePricing; booking form show_insurance,
	 * show_payment_options, show_date_picker, minDays, maxDays, defaultPayment) and
	 * Elementor -13 (the calendar's six parity controls plus its `height` parity
	 * control, now excluded -- a widget has no BlockRegistry wrapper; booking form
	 * own min_days, max_days, default_payment, show_payment_options and the
	 * show_insurance, show_date_picker parity controls).
	 */
	protected function gate_floors(): array
	{
		return array(
			'block'     => 230,
			'elementor' => 256,
		);
	}

	protected function gate_without_settings(): array
	{
		return array( 'user-dashboard', 'UserDashboardWidget' );
	}
}
