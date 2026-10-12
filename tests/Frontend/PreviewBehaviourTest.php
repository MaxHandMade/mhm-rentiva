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
	 * (booking title) or '' (shows no seeded record)].
	 *
	 * @return array<string,array{0:string,1:array<string,string>,2:string,3:string}>
	 */
	public function widgets(): array
	{
		return array(
			'comparison'      => array( 'rentiva_vehicle_comparison', array(), self::BADGE_SAMPLE_DATA, 'sentinel' ),
			'my bookings'     => array( 'rentiva_my_bookings', array(), self::BADGE_SAMPLE_DATA, 'sentinel' ),
			'my favorites'    => array( 'rentiva_my_favorites', array(), self::BADGE_SAMPLE_DATA, 'sentinel' ),
			'payment history' => array( 'rentiva_payment_history', array(), self::BADGE_SAMPLE_DATA, 'reference' ),
			'user dashboard'  => array( 'rentiva_user_dashboard', array(), self::BADGE_NOTICE, '' ),
		);
	}

	/**
	 * @param array<string,string> $atts
	 * @dataProvider widgets
	 */
	public function test_preview_shows_samples_not_real_data(string $tag, array $atts, string $badge, string $front_seed): void
	{
		$this->assert_sentinel_outside_sample_set();

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

		$this->assertSame(0, substr_count($html, $this->sentinel_title), "$tag: preview prints the administrator's sentinel vehicle.");
		$this->assertSame(0, substr_count($html, $this->reference), "$tag: preview prints the administrator's booking reference.");
		$this->assertSame(0, substr_count($html, '#' . $this->booking . '<'), "$tag: preview prints the administrator's booking id.");
		foreach (self::FORBIDDEN as $needle) {
			$this->assertSame(0, substr_count($html, $needle), "$tag: preview prints `$needle`.");
		}
	}

	/**
	 * Positive control: the same seed reaches the front-end output, so the
	 * preview's zero counts above are not vacuous.
	 *
	 * @param array<string,string> $atts
	 * @dataProvider widgets
	 */
	public function test_front_shows_the_seed(string $tag, array $atts, string $badge, string $front_seed): void
	{
		unset($badge);
		$html = $this->shortcode($tag, $atts);

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

		preg_match_all('/<button\b[^>]*\bmhm-vehicle-compare-btn\b[^>]*>/', $html, $compares);
		$this->assertCount(3, $compares[0], 'One compare button per sample card.');
		foreach ($compares[0] as $compare) {
			$this->assertStringNotContainsString('is-active', $compare, "Compare button active: $compare");
			$this->assertStringContainsString('aria-pressed="false"', $compare);
		}

		// Control: the catalog grid still shows the editor's real state.
		$grid = $this->shortcode('rentiva_vehicles_grid', array( 'ids' => implode(',', $samples) ));
		$this->assertMatchesRegularExpression('/<button\b[^>]*\bmhm-vehicle-compare-btn\b[^>]*\bis-active\b/', $grid, 'Catalog cards keep the real compare state.');
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
