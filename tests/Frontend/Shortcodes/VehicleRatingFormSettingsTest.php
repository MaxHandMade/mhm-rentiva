<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleRatingWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Vehicle rating form settings, spec 2026-10-10 Elementor bridge slice 3A Task 8.
 *
 * All ten dead settings are removed (dead-settings.tsv): max_photos,
 * show_photo_upload, show_category_ratings, show_recommend_toggle (no such
 * feature); require_login, min_review_length (the global Comments settings
 * decide, server side); require_booking (no verified-renter check; a block
 * toggle could not bind the AJAX handler); show_star_rating, show_text_review
 * (the core of a review -- they were never read, so the stars and the text box
 * stay); show_vehicle_preview (the form lives on the vehicle page).
 *
 * G2 (Rule 1): the default renders stay today's, recorded before the change
 * (tests/fixtures/golden/vehicle-rating-form/, MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 */
final class VehicleRatingFormSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/vehicle-rating-form';

	private const REMOVED_ATTRS = array(
		'maxPhotos',
		'minReviewLength',
		'requireBooking',
		'requireLogin',
		'showCategoryRatings',
		'showPhotoUpload',
		'showRecommendToggle',
		'showStarRating',
		'showTextReview',
		'showVehiclePreview',
	);

	private const REMOVED_KEYS = array(
		'max_photos',
		'min_review_length',
		'require_booking',
		'require_login',
		'show_category_ratings',
		'show_photo_upload',
		'show_recommend_toggle',
		'show_star_rating',
		'show_text_review',
		'show_vehicle_preview',
	);

	private int $vehicle = 0;

	private ?string $request_uri = null;

	public function setUp(): void
	{
		parent::setUp();
		// The form's referer field prints the request URI; an earlier test's request
		// must not leak into the goldens.
		$this->request_uri      = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test state.
		$_SERVER['REQUEST_URI'] = '';
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->vehicle = (int) wp_insert_post(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_status' => 'publish',
				'post_title'  => 'Rating Form Vehicle',
			)
		);
		// Exactly 210 days old: the template prints human_time_diff() against time(), so
		// a fixed date would drift ("7 months ago" -> "8 months ago"). The template parses
		// comment_date with strtotime() (UTC), hence both columns carry the UTC value.
		$review_date = gmdate('Y-m-d H:i:s', time() - 210 * DAY_IN_SECONDS);
		$comment     = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $this->vehicle,
				'comment_author'   => 'Ayse Yilmaz',
				'comment_content'  => 'A rated review of the form vehicle.',
				'comment_approved' => 1,
				'comment_date'     => $review_date,
				'comment_date_gmt' => $review_date,
			)
		);
		update_comment_meta($comment, 'mhmrentiva_rating', 4);

		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		if (null === $this->request_uri) {
			unset($_SERVER['REQUEST_URI']);
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * G2: the default render of each surface is today's, for a visitor (login
	 * prompt) and for a logged-in customer (the form with its stars and text box).
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		if (str_ends_with($name, '-logged-in')) {
			$this->log_in();
		}
		$surface = explode('-', substr($name, strlen('vehicle-rating-form-')))[0];
		$html    = $this->render_surface($surface, array());
		$this->assertStringContainsString('rv-rating-summary', $html, "$name rendered the form; the golden would pin an error.");

		$actual = $this->golden_form($html);
		$file   = self::GOLDEN_DIR . '/' . $name . '.html';

		if ('1' === getenv('MHMRENTIVA_RECORD_GOLDEN')) {
			if (! is_dir(self::GOLDEN_DIR)) {
				mkdir(self::GOLDEN_DIR, 0777, true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
			}
			file_put_contents($file, $actual); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
			$this->markTestIncomplete("Recorded golden $name.");
		}

		$this->assertFileExists($file, "No golden for $name; record it on the code before the change.");
		$this->assertSame(file_get_contents($file), $actual, "The default render of $name changed (Rule 1, spec §2.2)."); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function default_surfaces(): array
	{
		$names = array();
		foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
			$names[] = 'vehicle-rating-form-' . $surface;
			$names[] = 'vehicle-rating-form-' . $surface . '-logged-in';
		}
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * The ten keys are gone from every layer, and their saved values change
	 * nothing (Review Focus 4): the stars and the text box still render with
	 * show_star_rating / show_text_review saved off, and the login rule is the
	 * global Comments setting's, whatever require_login says.
	 */
	public function test_removed_settings_are_gone_and_inert(): void
	{
		$attributes = SettingProbe::block_json('vehicle-rating-form', array())['attributes'];
		$controls   = SettingProbe::data_controls('VehicleRatingWidget');
		$schema     = AllowlistRegistry::get_schema('rentiva_vehicle_rating_form');
		foreach (self::REMOVED_ATTRS as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		foreach (self::REMOVED_KEYS as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
			$this->assertArrayNotHasKey($gone, $schema, "schema $gone");
			$this->assertArrayNotHasKey($gone, AllowlistRegistry::ALLOWLIST, "global allowlist $gone (no other tag uses it)");
		}

		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/vehicle-rating-form/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		foreach (self::REMOVED_ATTRS as $gone) {
			$this->assertDoesNotMatchRegularExpression('/\b' . $gone . '\b/', $js, "index.js still names $gone");
		}

		$saved_block  = array_fill_keys(self::REMOVED_ATTRS, false);
		$saved_widget = array_fill_keys(self::REMOVED_KEYS, '');
		foreach (array( 'maxPhotos', 'minReviewLength' ) as $number) {
			$saved_block[ $number ] = '500';
		}
		foreach (array( 'max_photos', 'min_review_length' ) as $number) {
			$saved_widget[ $number ] = 500;
		}

		foreach (array( false, true ) as $logged_in) {
			if ($logged_in) {
				$this->log_in();
			}
			$who = $logged_in ? 'logged in' : 'visitor';
			$this->assertSame(
				RenderNormalizer::normalize($this->render_surface('block', array())),
				RenderNormalizer::normalize($this->render_surface('block', $saved_block)),
				"block, $who"
			);
			$this->assertSame(
				RenderNormalizer::normalize($this->render_surface('widget', array())),
				RenderNormalizer::normalize($this->render_surface('widget', $saved_widget)),
				"widget, $who"
			);
			$text = '';
			foreach (self::REMOVED_KEYS as $key) {
				$text .= ' ' . $key . '="' . ( in_array($key, array( 'max_photos', 'min_review_length' ), true ) ? '500' : '0' ) . '"';
			}
			$this->assertSame(
				RenderNormalizer::normalize($this->render_surface('shortcode', array())),
				RenderNormalizer::normalize($this->shortcode($text)),
				"shortcode text, $who"
			);
		}

		// Logged in: the stars and the text box are there with the old toggles off.
		$html = $this->render_surface('block', $saved_block);
		$this->assertSame(5, substr_count($html, 'name="rating"'), 'five star inputs');
		$this->assertSame(1, substr_count($html, '<textarea name="comment"'), 'the text box');

		// A visitor gets the login prompt from the global setting, whatever was saved.
		wp_set_current_user(0);
		$visitor = $this->render_surface('block', array( 'requireLogin' => false ));
		$this->assertStringContainsString('rv-rating-login-notice', $visitor);
		$this->assertStringNotContainsString('<textarea name="comment"', $visitor);
	}

	private function log_in(): void
	{
		$user = (int) wp_insert_user(
			array(
				'user_login'   => 'rating_form_customer',
				'user_pass'    => wp_generate_password(),
				'user_email'   => 'rating-form-customer@example.test',
				'display_name' => 'Rating Customer',
				'role'         => 'subscriber',
			)
		);
		if ($user <= 0) {
			$user = (int) get_user_by('login', 'rating_form_customer')->ID;
		}
		wp_set_current_user($user);
	}

	/**
	 * golden(), plus the fixture vehicle's post id in the form's hidden field.
	 */
	private function golden_form(string $html): string
	{
		$html = (string) preg_replace('#(name="vehicle_id" value=")\d+"#', '$1VEHICLE"', $html);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	/**
	 * @param array<string,mixed> $settings Block attrs or widget settings; empty for the default.
	 */
	private function render_surface(string $surface, array $settings): string
	{
		switch ($surface) {
			case 'shortcode':
				return $this->shortcode('');
			case 'block':
				return $this->block($settings);
			case 'widget':
				return $this->widget($settings);
		}
		throw new \InvalidArgumentException($surface);
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_vehicle_rating_form vehicle_id="' . $this->vehicle . '"' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(VehicleRatingWidget::class, array( 'vehicle_id' => (string) $this->vehicle ) + $settings);
		ob_start();
		try {
			$widget->render_content();
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	private function block(array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/vehicle-rating-form',
				'attrs'        => array( 'vehicleId' => (string) $this->vehicle ) + $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
