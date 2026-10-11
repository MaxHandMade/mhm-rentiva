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
	 * my-bookings show_image (Task 7a): the Img column needs the same non-integrated
	 * page; MyBookingsSettingsTest::test_columns_follow_flags proves it on the
	 * template with that navigation, and that each surface hands the flag over.
	 */
	public const UNAVAILABLE_UNDER_WOOCOMMERCE = array(
		'block|rentiva_my_bookings|hide_nav',
		'elementor|rentiva_my_bookings|hide_nav',
		'block|rentiva_my_bookings|show_image',
		'elementor|rentiva_my_bookings|show_image',
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
	 * Ruling T6-1: block -1 / Elementor -1 more (calendar showDiscounts and its
	 * parity control; no discount data source).
	 * Task 7a (my bookings): block -5 (showCancelButton, showModifyButton,
	 * showDetailsLink, sortBy, limitResults -- its "Limit Results" control now
	 * writes limit) and Elementor -5 (own orderby select; show_cancel_btn,
	 * show_modify_btn, show_details_link and limit_results parity controls).
	 * Task 7b (payment history, my favourites): block -6 (showInvoiceDownload,
	 * showTransactionId, limitResults -- its "Limit Results" control now writes
	 * limit; showRemoveButton -- its control now writes showFavoriteButton;
	 * showAddedDate, showBadges) and Elementor -6 (parity show_invoice_download,
	 * show_transaction_id, limit_results, show_badges; own show_remove_button and
	 * show_added_date).
	 * Task 8 (testimonials, vehicle rating form): block -15 (testimonials autoplay,
	 * category, limitItems, showAuthorName, showVehicleName -- the four twins'
	 * controls now write autoRotate, limit, showCustomer and showVehicle; rating
	 * form showStarRating, showTextReview, showCategoryRatings, showPhotoUpload,
	 * showVehiclePreview, showRecommendToggle, requireLogin, requireBooking,
	 * maxPhotos, minReviewLength) and Elementor -15 (their fifteen parity controls).
	 */
	protected function gate_floors(): array
	{
		return array(
			'block'     => 203,
			'elementor' => 229,
		);
	}

	protected function gate_without_settings(): array
	{
		return array( 'user-dashboard', 'UserDashboardWidget' );
	}
}
