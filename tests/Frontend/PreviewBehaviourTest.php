<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Services\CompareService;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a, behaviour per widget class (spec §2.1, §3 PreviewBehaviourTest).
 *
 * The "no real data" check is falsifiable: the fixture seeds at least four
 * active published vehicles (the sample set is the lowest three ids), then a
 * sentinel vehicle OUTSIDE that set (largest id, unique title) goes into the
 * administrator's favourites and comparison list, and a booking with a unique
 * reference is made for the administrator. Every preview must print neither;
 * the same seed rendered on the front end must print it (positive control).
 *
 * The provider is keyed by widget; later tasks append their rows.
 */
final class PreviewBehaviourTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	public const BADGE_SAMPLE_DATA = 'Preview: sample data. Logged-in customers see their own records.';
	public const BADGE_NOTICE      = 'Preview: the notice a customer will see.';
	public const BADGE_SAMPLE_VEH  = 'Preview: sample vehicle. On a vehicle page it shows that vehicle.';
	public const BADGE_INVALID_VEH = 'Preview: invalid vehicle. Check the vehicle ID in the widget settings.';

	/** Fragments a preview must not print (nonces and tokens). */
	private const FORBIDDEN = array(
		'name="nonce"',
		'_wpnonce',
		'data-nonce',
		'recipient_token',
	);

	private int $admin = 0;

	private int $sentinel = 0;

	private string $sentinel_title = '';

	private int $booking = 0;

	private string $reference = '';

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; a preview cannot be entered.');
		RenderContext::reset();
		PreviewSamples::reset();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		RequestSimulator::new_request();

		// At least four active published vehicles before the sentinel: the sample
		// set (lowest three ids) can never reach it.
		for ($i = 0; $i < 4; $i++) {
			ShortcodeFixtures::vehicle();
		}

		$this->sentinel_title = 'RV-SENTINEL-' . uniqid();
		$this->sentinel       = ShortcodeFixtures::vehicle();
		wp_update_post(
			array(
				'ID'         => $this->sentinel,
				'post_title' => $this->sentinel_title,
			)
		);

		$this->admin = (int) self::factory()->user->create(array( 'role' => 'administrator' ));
		wp_set_current_user($this->admin);

		FavoritesService::add($this->admin, $this->sentinel);
		self::forget_compare_cache();
		// The comparison needs two vehicles to draw its table on the front end.
		CompareService::add($this->sentinel);
		CompareService::add(ShortcodeFixtures::vehicle());

		$this->reference = 'RV-REF-' . uniqid();
		$this->booking   = ShortcodeFixtures::booking($this->admin, $this->sentinel);
		wp_update_post(
			array(
				'ID'         => $this->booking,
				'post_title' => $this->reference,
			)
		);
	}

	public function tear_down(): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode(false);
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		self::forget_compare_cache();
		$this->reset_context_and_tear_down();
	}

	/**
	 * CompareService keeps the list in a static for the request; a new test is a
	 * new request with a new user.
	 */
	private static function forget_compare_cache(): void
	{
		$property = new \ReflectionProperty(CompareService::class, 'cached_list');
		$property->setAccessible(true);
		$property->setValue(null, null);
	}

	/**
	 * Widget => [shortcode tag, attributes, badge text ('' = no badge), what the
	 * front end shows of the seed: 'sentinel' (vehicle title), 'reference'
	 * (booking title) or '' (shows no seeded record), class]. The class is
	 * 'account' (always samples), 'context' (a sample only without a vehicle) or
	 * 'catalog' (real public data, no badge). 5 + 2 + 10 = the 17 Lite widgets.
	 *
	 * @return array<string,array{0:string,1:array<string,string>,2:string,3:string,4:string}>
	 */
	public function widgets(): array
	{
		return array(
			'comparison'        => array( 'rentiva_vehicle_comparison', array(), self::BADGE_SAMPLE_DATA, 'sentinel', 'account' ),
			'my bookings'       => array( 'rentiva_my_bookings', array(), self::BADGE_SAMPLE_DATA, 'sentinel', 'account' ),
			'my favorites'      => array( 'rentiva_my_favorites', array(), self::BADGE_SAMPLE_DATA, 'sentinel', 'account' ),
			'payment history'   => array( 'rentiva_payment_history', array(), self::BADGE_SAMPLE_DATA, 'reference', 'account' ),
			'user dashboard'    => array( 'rentiva_user_dashboard', array(), self::BADGE_NOTICE, '', 'account' ),
			'vehicle details'   => array( 'rentiva_vehicle_details', array(), self::BADGE_SAMPLE_VEH, '', 'context' ),
			'rating form'       => array( 'rentiva_vehicle_rating_form', array(), self::BADGE_SAMPLE_VEH, '', 'context' ),
			'unified search'    => array( 'rentiva_unified_search', array(), '', '', 'catalog' ),
			'search results'    => array( 'rentiva_search_results', array(), '', '', 'catalog' ),
			'vehicles list'     => array( 'rentiva_vehicles_list', array(), '', '', 'catalog' ),
			'vehicles grid'     => array( 'rentiva_vehicles_grid', array(), '', '', 'catalog' ),
			'featured vehicles' => array( 'rentiva_featured_vehicles', array(), '', '', 'catalog' ),
			'vehicle card'      => array( 'rentiva_vehicles_list', array( 'limit' => '1', 'columns' => '1', '_marker' => 'data-testid="vehicle-card"' ), '', '', 'catalog' ),
			'availability'      => array( 'rentiva_availability_calendar', array(), '', '', 'catalog' ),
			'booking form'      => array( 'rentiva_booking_form', array(), '', '', 'catalog' ),
			'testimonials'      => array( 'rentiva_testimonials', array(), '', '', 'catalog' ),
			'contact'           => array( 'rentiva_contact', array(), '', '', 'catalog' ),
		);
	}

	/**
	 * Attributes for a render: the fixture's own for the catalog rows (so there
	 * is real data to find), the row's on top.
	 *
	 * @param array<string,string> $atts
	 * @return array{0:array<string,string>,1:string}
	 */
	private function fixture_atts(string $tag, array $atts, string $class): array
	{
		if ('catalog' !== $class) {
			return array( $atts, '' );
		}
		$fixture = ShortcodeFixtures::for_tag($tag);
		// A row that narrows the list (the card shows one vehicle) names its own marker.
		$marker = $atts['_marker'] ?? (string) $fixture['marker'];
		unset($atts['_marker']);
		return array( array_merge($fixture['atts'], $atts), $marker );
	}

	/**
	 * @param array<string,string> $atts
	 * @dataProvider widgets
	 */
	public function test_preview_shows_samples_not_real_data(string $tag, array $atts, string $badge, string $front_seed, string $class): void
	{
		$this->assert_sentinel_outside_sample_set();

		[ $atts, $marker ] = $this->fixture_atts($tag, $atts, $class);
		$this->set_preview(true);
		$html = $this->shortcode($tag, $atts);

		$this->assertSame(1, substr_count($html, 'data-rv-render="preview"'), "$tag: expected exactly one preview root marker.");
		$this->assertStringStartsWith('<div class="mhm-rv-preview" data-rv-render="preview">', $html, "$tag: the root marker must be the outermost element.");

		if ('' === $badge) {
			$this->assertSame(0, substr_count($html, 'mhm-rv-preview-badge'), "$tag: no badge expected.");
		} else {
			$this->assertSame(1, substr_count($html, 'mhm-rv-preview-badge'), "$tag: expected one badge.");
			$this->assertStringContainsString(esc_html($badge), $html, "$tag: wrong badge text.");
		}

		foreach (self::FORBIDDEN as $needle) {
			$this->assertSame(0, substr_count($html, $needle), "$tag: preview prints `$needle`.");
		}

		if ('catalog' === $class) {
			// Real public data, not an empty state: the fixture's marker is on the page.
			$this->assertStringContainsString($marker, $html, "$tag: the catalog preview does not show real data.");
			return;
		}

		$this->assertSame(0, substr_count($html, $this->sentinel_title), "$tag: preview prints the administrator's sentinel vehicle.");
		$this->assertSame(0, substr_count($html, $this->reference), "$tag: preview prints the administrator's booking reference.");
		$this->assertSame(0, substr_count($html, '#' . $this->booking . '<'), "$tag: preview prints the administrator's booking id.");
	}

	/**
	 * Positive control: the same seed reaches the front-end output, so the
	 * preview's zero counts above are not vacuous.
	 *
	 * @param array<string,string> $atts
	 * @dataProvider widgets
	 */
	public function test_front_shows_the_seed(string $tag, array $atts, string $badge, string $front_seed, string $class): void
	{
		unset($badge);
		[ $atts, $marker ] = $this->fixture_atts($tag, $atts, $class);
		$html              = $this->shortcode($tag, $atts);

		if ('catalog' === $class) {
			$this->assertStringContainsString($marker, $html, "$tag: the front end lost its real data.");
		}

		$this->assertStringNotContainsString('data-rv-render', $html, "$tag: front output carries the preview marker.");
		$this->assertStringNotContainsString('mhm-rv-preview-badge', $html, "$tag: front output carries a badge.");
		$this->assertNotSame('', trim($html), "$tag rendered nothing on the front end.");

		if ('sentinel' === $front_seed) {
			$this->assertStringContainsString($this->sentinel_title, $html, "$tag: the seeded sentinel is missing on the front end.");
		} elseif ('reference' === $front_seed) {
			$this->assertStringContainsString($this->reference, $html, "$tag: the seeded reference is missing on the front end.");
		}
	}

	/**
	 * An explicit `vehicle_ids` attribute is the editor's own choice: those
	 * vehicles, no badge (spec §2.1).
	 */
	public function test_comparison_with_explicit_ids_shows_them_without_badge(): void
	{
		$a = ShortcodeFixtures::vehicle();
		$b = ShortcodeFixtures::vehicle();

		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_comparison', array( 'vehicle_ids' => $a . ',' . $b ));

		$this->assertStringContainsString('data-rv-render="preview"', $html);
		$this->assertStringNotContainsString('mhm-rv-preview-badge', $html);
		$this->assertStringContainsString(get_the_title($a), $html);
		$this->assertStringContainsString(get_the_title($b), $html);
		$this->assertStringNotContainsString($this->sentinel_title, $html);
	}

	/**
	 * The favourites preview cards do not reveal the editing user's own lists:
	 * a sample vehicle the administrator saved and compares looks like the
	 * others -- every heart active (as on the real "My favourites" page), no
	 * compare button active.
	 */
	public function test_favorites_preview_cards_do_not_show_the_editors_state(): void
	{
		$samples = PreviewSamples::vehicle_ids(3);
		$this->assertCount(3, $samples);
		FavoritesService::add($this->admin, $samples[0]);
		CompareService::add($samples[0]);
		$this->assertTrue(CompareService::is_in_compare($samples[0]), 'Fixture: the sample vehicle is in the comparison list.');

		$this->set_preview(true);
		$html = $this->shortcode('rentiva_my_favorites', array());

		preg_match_all('/<button\b[^>]*\bmhm-vehicle-favorite-btn\b[^>]*>/', $html, $hearts);
		$this->assertCount(3, $hearts[0], 'One favourite button per sample card.');
		foreach ($hearts[0] as $heart) {
			$this->assertStringContainsString('is-active', $heart, "Heart not active: $heart");
			$this->assertStringContainsString('aria-pressed="true"', $heart);
		}

		// The front-end standard card's compare control is a checkbox (Aşama 2 PR 2).
		preg_match_all('/<input\b[^>]*\bdata-rvs-compare\b[^>]*>/', $html, $compares);
		$this->assertCount(3, $compares[0], 'One compare control per sample card.');
		foreach ($compares[0] as $compare) {
			$this->assertStringNotContainsString('checked', $compare, "Compare control checked: $compare");
		}

		// Control: the catalog grid still shows the editor's real state.
		$grid = $this->shortcode('rentiva_vehicles_grid', array( 'ids' => implode(',', $samples) ));
		$this->assertMatchesRegularExpression('/<input\b[^>]*\bdata-rvs-compare\b[^>]*\bchecked\b/', $grid, 'Catalog cards keep the real compare state.');
	}

	/**
	 * Sample rows are drawn: the preview is not an empty state.
	 */
	public function test_previews_draw_sample_rows(): void
	{
		$this->set_preview(true);

		$samples = PreviewSamples::vehicle_ids(3);

		$bookings = $this->shortcode('rentiva_my_bookings', array());
		foreach (PreviewSamples::bookings_rows() as $row) {
			$this->assertStringContainsString('#' . $row['id'] . '<', $bookings);
			$this->assertStringContainsString(esc_html($row['vehicle_title']), $bookings);
		}
		$this->assertStringNotContainsString('rv-no-bookings', $bookings);

		$payments = $this->shortcode('rentiva_payment_history', array());
		$this->assertSame(count(PreviewSamples::payment_rows()), substr_count($payments, 'data-testid="payment-item"'));
		// The upload control is inert in a preview.
		preg_match_all('/<input\b[^>]*class="mhm-upload-receipt"[^>]*>/', $payments, $inputs);
		$this->assertNotEmpty($inputs[0]);
		foreach ($inputs[0] as $input) {
			$this->assertMatchesRegularExpression('/\bdisabled\b/', $input, "upload control not disabled: $input");
		}

		$favorites = $this->shortcode('rentiva_my_favorites', array());
		foreach ($samples as $id) {
			$this->assertStringContainsString(esc_html(get_the_title($id)), $favorites);
		}

		$comparison = $this->shortcode('rentiva_vehicle_comparison', array());
		$this->assertStringContainsString('rv-comparison-table', $comparison);
		foreach ($samples as $id) {
			$this->assertStringContainsString(esc_html(get_the_title($id)), $comparison);
		}

		$dashboard = $this->shortcode('rentiva_user_dashboard', array());
		$this->assertStringContainsString('mhm-rentiva-retired-dashboard', $dashboard);
		$this->assertStringNotContainsString('mhm-rentiva-retired-dashboard__admin', $dashboard, 'The preview shows the customer notice, not the administrator one.');
	}

	/**
	 * A customer without any records, and a guest, still get the samples in a
	 * preview (bookings rows, dashboard notice).
	 */
	public function test_preview_shows_samples_for_a_user_without_records(): void
	{
		wp_set_current_user(ShortcodeFixtures::customer());
		$this->set_preview(true);

		$bookings = $this->shortcode('rentiva_my_bookings', array());
		$this->assertStringContainsString(esc_html(self::BADGE_SAMPLE_DATA), $bookings);
		$this->assertStringContainsString('#' . PreviewSamples::bookings_rows()[0]['id'] . '<', $bookings);

		$dashboard = $this->shortcode('rentiva_user_dashboard', array());
		wp_set_current_user(0);
		$guest = $this->shortcode('rentiva_user_dashboard', array());
		$this->assertStringContainsString(esc_html(self::BADGE_NOTICE), $dashboard);
		$this->assertStringContainsString('mhm-rentiva-retired-dashboard', $guest, 'A preview always shows the notice, even with nobody logged in.');
	}

	/**
	 * Vehicle details without any context: the lowest sample vehicle and its badge.
	 */
	public function test_details_in_preview_without_context_shows_the_sample_vehicle(): void
	{
		$sample = PreviewSamples::vehicle_id();
		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_details', array());

		$this->assertSame(1, substr_count($html, 'data-rv-render="preview"'));
		$this->assertSame(1, substr_count($html, 'mhm-rv-preview-badge'));
		$this->assertStringContainsString(esc_html(self::BADGE_SAMPLE_VEH), $html);
		$this->assertStringContainsString(esc_html(get_the_title($sample)), $html);
		$this->assertStringContainsString('rv-vehicle-details-wrapper', $html);
		$this->assertStringNotContainsString($this->sentinel_title, $html);
	}

	/**
	 * On a vehicle page the page's vehicle shows, without a badge.
	 */
	public function test_details_on_a_vehicle_page_shows_that_vehicle_without_badge(): void
	{
		$GLOBALS['post'] = get_post($this->sentinel);
		setup_postdata($GLOBALS['post']);

		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_details', array());

		$this->assertStringContainsString(esc_html($this->sentinel_title), $html);
		$this->assertSame(0, substr_count($html, 'mhm-rv-preview-badge'));
	}

	public function test_details_with_an_explicit_valid_id_shows_it_without_badge(): void
	{
		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_details', array( 'vehicle_id' => (string) $this->sentinel ));

		$this->assertStringContainsString(esc_html($this->sentinel_title), $html);
		$this->assertSame(0, substr_count($html, 'mhm-rv-preview-badge'));
	}

	/**
	 * An explicit id that is not a readable vehicle is rejected, never replaced
	 * by the page's vehicle or a sample.
	 */
	public function test_details_with_an_explicit_invalid_id_is_rejected(): void
	{
		$draft = ShortcodeFixtures::vehicle();
		wp_update_post(array( 'ID' => $draft, 'post_status' => 'draft' ));
		$GLOBALS['post'] = get_post($this->sentinel);
		setup_postdata($GLOBALS['post']);

		$this->set_preview(true);
		foreach (array( '999999', (string) $draft ) as $bad) {
			$html = $this->shortcode('rentiva_vehicle_details', array( 'vehicle_id' => $bad ));
			$this->assertSame(1, substr_count($html, 'mhm-rv-preview-badge'), "id $bad");
			$this->assertStringContainsString(esc_html(self::BADGE_INVALID_VEH), $html, "id $bad");
			$this->assertStringNotContainsString('rv-vehicle-details-wrapper', $html, "id $bad");
			$this->assertStringNotContainsString($this->sentinel_title, $html, "id $bad");
			$this->assertStringNotContainsString('No vehicle selected', $html, "id $bad: the badge already says what to check.");
			$this->assertStringNotContainsString('rv-vehicle-details-empty', $html, "id $bad");
		}

		// Front end: the visitor's neutral state, no badge, no wrapper, no editor text.
		$this->set_preview(false);
		$front = $this->shortcode('rentiva_vehicle_details', array( 'vehicle_id' => '999999' ));
		$this->assertNotSame('', trim($front));
		$this->assertStringNotContainsString('mhm-rv-preview', $front);
		$this->assertStringNotContainsString('rv-vehicle-details-wrapper', $front);
		$this->assertStringNotContainsString('widget settings', $front);
	}

	/**
	 * Front end without a vehicle context: a helpful neutral state, no internal path.
	 */
	public function test_details_on_the_front_without_context_is_a_neutral_empty_state(): void
	{
		wp_set_current_user(0);
		$html = $this->shortcode('rentiva_vehicle_details', array());

		$this->assertNotSame('', trim($html));
		$this->assertStringNotContainsString('rv-vehicle-details-wrapper', $html);
		$this->assertStringNotContainsString('data-rv-render', $html);
		$this->assertStringNotContainsString('widget settings', $html, 'The visitor gets no editor instruction.');
		foreach (array( 'shortcodes/', 'Template:', 'Template file not found' ) as $needle) {
			$this->assertStringNotContainsString($needle, $html);
		}
	}

	/**
	 * No vehicle to sample either: the preview says what to do, still wrapped.
	 */
	public function test_details_in_preview_with_no_vehicle_gives_the_instruction(): void
	{
		$ids = get_posts(array( 'post_type' => 'mhmrentiva_vehicle', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ));
		foreach ($ids as $id) {
			wp_delete_post((int) $id, true);
		}
		PreviewSamples::reset();

		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_details', array());

		$this->assertSame(1, substr_count($html, 'data-rv-render="preview"'));
		$this->assertSame(0, substr_count($html, 'mhm-rv-preview-badge'));
		$this->assertStringContainsString('widget settings', $html);
		$this->assertStringNotContainsString('rv-vehicle-details-wrapper', $html);
	}

	/**
	 * Details nests the rating form and the featured list: one root marker, and
	 * the inner form carries no nonce.
	 */
	public function test_nested_preview_wraps_once_and_inner_form_inert(): void
	{
		wp_set_current_user(ShortcodeFixtures::customer());
		// The nested featured list is drawn only when a featured vehicle other than the shown one exists.
		update_post_meta(ShortcodeFixtures::vehicle(), \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_FEATURED, '1');
		$this->set_preview(true);
		$html = $this->shortcode('rentiva_vehicle_details', array( 'vehicle_id' => (string) PreviewSamples::vehicle_id() ));

		$this->assertSame(1, substr_count($html, 'data-rv-render="preview"'));
		$this->assertStringContainsString('rv-rating-summary', $html, 'The nested rating form is drawn.');
		$this->assertStringContainsString('mhm-rentiva-featured-wrapper', $html, 'The nested featured list is drawn.');
		foreach (self::FORBIDDEN as $needle) {
			$this->assertSame(0, substr_count($html, $needle), "nested preview prints `$needle`.");
		}
		$this->assertSame(0, substr_count($html, 'name="rating_nonce"'));
	}

	/**
	 * Elementor skips the server render when content_template() prints anything,
	 * so these two stay empty (spec 2.4).
	 *
	 * @dataProvider content_template_widgets
	 */
	public function test_content_template_stays_empty(string $class): void
	{
		$method = new \ReflectionMethod($class, 'content_template');
		$lines  = array_slice((array) file((string) $method->getFileName()), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
		$code   = '';
		foreach (token_get_all('<?php ' . implode('', $lines)) as $token) {
			if (is_array($token) && in_array($token[0], array( T_COMMENT, T_DOC_COMMENT, T_WHITESPACE, T_OPEN_TAG ), true)) {
				continue;
			}
			$code .= is_array($token) ? $token[1] : $token;
		}
		$this->assertSame('protectedfunctioncontent_template():void{}', str_replace(' ', '', $code));
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function content_template_widgets(): array
	{
		return array(
			'booking form' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\BookingFormWidget::class ),
			'vehicle card' => array( \MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget::class ),
		);
	}

	private function assert_sentinel_outside_sample_set(): void
	{
		$samples = PreviewSamples::vehicle_ids(3);
		$this->assertCount(3, $samples, 'The fixture must leave three sample vehicles.');
		$this->assertNotContains($this->sentinel, $samples, 'The sentinel must be outside the sample set.');
		$this->assertSame($this->sentinel, max(array_merge($samples, array( $this->sentinel ))));
	}

	private function set_preview(bool $on): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode($on);
	}

	/**
	 * @param array<string,string> $atts
	 */
	private function shortcode(string $tag, array $atts): string
	{
		RequestSimulator::new_request();
		$pairs = '';
		foreach ($atts as $key => $value) {
			$pairs .= sprintf(' %s="%s"', $key, esc_attr($value));
		}
		return do_shortcode('[' . $tag . $pairs . ']');
	}
}
