<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Shortcodes\VehiclesList;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\MyFavoritesWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleCardWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\VehiclesListWidget;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * The shared vehicle card (templates/partials/vehicle-card*.php), spec 2026-10-10
 * Elementor bridge slice 3A Task 2.
 *
 * The card's inputs: `button_text` (empty keeps today's Book Now / Unavailable,
 * spec §2.2 exception 3), `show_rating_count` (off by default) and
 * `show_availability_status` -- the only availability key the card reads
 * (§2.2 "Kart müsaitlik kapsamı"; `show_availability` stays unread).
 *
 * G2 (Rule 1): the default render of every surface that draws the card is pinned
 * to golden files recorded before the change (tests/fixtures/golden/vehicle-card/).
 * Set MHMRENTIVA_RECORD_GOLDEN=1 to re-record them -- only together with a
 * deliberate, reviewed change of the default card.
 */
final class VehicleCardSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../fixtures/golden/vehicle-card';

	/** @var list<int> */
	private array $catalog = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		$this->catalog = ShortcodeFixtures::catalog();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	public function test_button_text_overrides_book_now(): void
	{
		// The partial, as Task 3/4/7b hand it the input.
		$card = $this->card($this->catalog[0], array( 'button_text' => 'Zz Rent this car' ));
		$this->assertSame('Zz Rent this car', $this->button_label($card));

		// Vehicle card widget: the control reaches the card.
		$widget = $this->widget(
			VehicleCardWidget::class,
			array(
				'vehicle_id'  => (string) $this->catalog[0],
				'button_text' => 'Zz Widget label',
			)
		);
		$this->assertSame('Zz Widget label', $this->button_label($widget));

		// Shortcode text of the tag the card widget renders.
		$text = do_shortcode('[rentiva_vehicles_list ids="' . $this->catalog[0] . '" button_text="Zz Text label"]');
		$this->assertSame('Zz Text label', $this->button_label($text));
	}

	public function test_empty_button_text_keeps_todays_label(): void
	{
		$this->assertSame('Book Now', $this->button_label($this->card($this->catalog[0], array())));
		$this->assertSame('Book Now', $this->button_label($this->card($this->catalog[0], array( 'button_text' => '' ))));

		// booking_btn_text (the global setting's carrier) is the fallback; button_text wins.
		$this->assertSame('Zz Global', $this->button_label($this->card($this->catalog[0], array( 'booking_btn_text' => 'Zz Global' ))));
		$this->assertSame(
			'Zz Own',
			$this->button_label(
				$this->card(
					$this->catalog[0],
					array(
						'booking_btn_text' => 'Zz Global',
						'button_text'      => 'Zz Own',
					)
				)
			)
		);
		$this->assertSame('Book Now', $this->button_label($this->card($this->catalog[0], array( 'booking_btn_text' => '' ))));

		// An unavailable vehicle keeps "Unavailable" (its button is disabled), with or
		// without a custom label.
		$down = ShortcodeFixtures::vehicle();
		update_post_meta($down, MetaKeys::VEHICLE_STATUS, 'maintenance');
		$this->assertSame('Unavailable', $this->button_label($this->card($down, array())));
		$this->assertSame('Unavailable', $this->button_label($this->card($down, array( 'button_text' => 'Zz Rent this car' ))));

		// Vehicle card widget with its control left at the default.
		$widget = $this->widget(VehicleCardWidget::class, array( 'vehicle_id' => (string) $this->catalog[0] ));
		$this->assertSame('Book Now', $this->button_label($widget));
	}

	/**
	 * Spec 3A Â§2.2 exception 3 (bug fix, changelog): the global "Book Now" text
	 * setting reaches the card through the list/grid shortcodes' booking_btn_text.
	 * With the option empty the label stays Book Now (the G2 goldens).
	 */
	public function test_exception_3_global_book_now_text_reaches_the_card(): void
	{
		$ids = implode(',', $this->catalog);
		$set = static function ($settings) {
			$settings = is_array($settings) ? $settings : array();
			$settings['mhmrentiva_text_book_now'] = 'Zz Rent now';
			return $settings;
		};
		$option = 'option_' . \MHMRentiva\Admin\Settings\Core\SettingsCore::OPTION_NAME;

		$this->assertSame('Book Now', $this->button_label(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '"]')));

		add_filter($option, $set);
		try {
			$this->assertSame('Zz Rent now', $this->button_label(do_shortcode('[rentiva_vehicles_list ids="' . $ids . '"]')));
			$this->assertSame('Zz Rent now', $this->button_label(do_shortcode('[rentiva_vehicles_grid]')));
			$this->assertSame('Zz Rent now', $this->button_label($this->widget(VehicleCardWidget::class, array( 'vehicle_id' => (string) $this->catalog[0] ))));
			// The instance's own button_text still wins over the global text.
			$this->assertSame('Zz Mine', $this->button_label(do_shortcode('[rentiva_vehicles_list ids="' . $this->catalog[0] . '" button_text="Zz Mine"]')));
		} finally {
			remove_filter($option, $set);
		}
	}

	public function test_rating_count_shows_when_on(): void
	{
		// Alpha: 4.5 from 12 reviews.
		$on = $this->card($this->catalog[0], array( 'show_rating_count' => '1' ));
		$this->assertMatchesRegularExpression('#<span class="mhm-rating-count">\(12\)</span>#', $on);

		foreach (array( array(), array( 'show_rating_count' => '0' ), array( 'show_rating_count' => '' ) ) as $atts) {
			$this->assertStringNotContainsString('mhm-rating-count', $this->card($this->catalog[0], $atts));
		}

		// Rating hidden: no count either.
		$this->assertStringNotContainsString(
			'mhm-rating-count',
			$this->card(
				$this->catalog[0],
				array(
					'show_rating'       => '0',
					'show_rating_count' => '1',
				)
			)
		);

		// Widget: default off (spec §2.2, '1' -> '0'), switched on it shows.
		$widget_default = $this->widget(VehicleCardWidget::class, array( 'vehicle_id' => (string) $this->catalog[0] ));
		$this->assertStringNotContainsString('mhm-rating-count', $widget_default);
		$widget_on = $this->widget(
			VehicleCardWidget::class,
			array(
				'vehicle_id'        => (string) $this->catalog[0],
				'show_rating_count' => '1',
			)
		);
		$this->assertStringContainsString('<span class="mhm-rating-count">(12)</span>', $widget_on);
	}

	public function test_availability_status_reads_only_its_own_key(): void
	{
		$base = $this->card($this->catalog[0], array());
		$this->assertStringNotContainsString('mhm-card-availability', $base);

		// show_availability (featured/grid/list/search/card) is not read: same output.
		foreach (array( '1', 'yes', true ) as $on) {
			$this->assertSame(
				RenderNormalizer::normalize($base),
				RenderNormalizer::normalize($this->card($this->catalog[0], array( 'show_availability' => $on ))),
				'show_availability must not change the card.'
			);
		}

		$status = $this->card($this->catalog[0], array( 'show_availability_status' => '1' ));
		$this->assertMatchesRegularExpression('#<span class="mhm-card-availability is-available"[^>]*>\s*Available\s*</span>#', $status);

		$down = ShortcodeFixtures::vehicle();
		update_post_meta($down, MetaKeys::VEHICLE_STATUS, 'maintenance');
		$this->assertMatchesRegularExpression(
			'#<span class="mhm-card-availability is-unavailable"[^>]*>\s*Out of Order\s*</span>#',
			$this->card($down, array( 'show_availability_status' => '1' ))
		);

		foreach (array( '0', '', false ) as $off) {
			$this->assertStringNotContainsString('mhm-card-availability', $this->card($this->catalog[0], array( 'show_availability_status' => $off )));
		}
	}

	/**
	 * G2: the default render of each surface that draws the card is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_card_render_unchanged(string $name): void
	{
		$html = $this->render_default_surface($name);
		$this->assertStringContainsString('mhm-vehicle-card', $html, "$name rendered no card; the golden would pin an empty render.");

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
		$names = array(
			'card-widget',
			'list-shortcode',
			'list-block',
			'list-widget',
			'grid-shortcode',
			'featured-shortcode',
			'favorites-shortcode',
			'favorites-block',
			'favorites-widget',
		);
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render_default_surface(string $name): string
	{
		$ids = implode(',', $this->catalog);
		switch ($name) {
			case 'card-widget':
				return $this->widget(VehicleCardWidget::class, array( 'vehicle_id' => (string) $this->catalog[0] ));
			case 'list-shortcode':
				return do_shortcode('[rentiva_vehicles_list ids="' . $ids . '"]');
			case 'list-block':
				return $this->block('vehicles-list', array( 'ids' => $ids ));
			case 'list-widget':
				return $this->widget(VehiclesListWidget::class, array());
			case 'grid-shortcode':
				return do_shortcode('[rentiva_vehicles_grid]');
			case 'featured-shortcode':
				return do_shortcode('[rentiva_featured_vehicles]');
		}

		// Favorites: a customer who saved the catalog.
		$user = ShortcodeFixtures::customer();
		foreach ($this->catalog as $vehicle) {
			FavoritesService::add($user, $vehicle);
		}
		wp_set_current_user($user);
		switch ($name) {
			case 'favorites-shortcode':
				return do_shortcode('[rentiva_my_favorites]');
			case 'favorites-block':
				return $this->block('my-favorites', array());
			case 'favorites-widget':
				return $this->widget(MyFavoritesWidget::class, array());
		}
		throw new \InvalidArgumentException($name);
	}

	/**
	 * Normalized, with the run's post and attachment ids replaced by stable names
	 * (ids differ between databases; everything else of the fixture is fixed).
	 */
	private function golden_form(string $html): string
	{
		$map = array();
		foreach ($this->catalog as $i => $id) {
			$map[ $id ]                               = 'VEHICLE' . $i;
			$map[ (int) get_post_thumbnail_id($id) ] = 'IMAGE' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=_id=|[?&]p=)(\d+)\b/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = RenderNormalizer::normalize($html);
		// Whitespace between tags is not part of the look.
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}

	/**
	 * The card partial alone, for one vehicle, with the given inputs.
	 *
	 * @param array<string,mixed> $atts Card inputs.
	 */
	private function card(int $vehicle_id, array $atts): string
	{
		$vehicle = VehiclesList::get_vehicle_data_for_shortcode($vehicle_id, $atts);
		return (string) Templates::render(
			'partials/vehicle-card',
			array(
				'vehicle' => $vehicle,
				'layout'  => 'grid',
				'atts'    => $atts,
			),
			true
		);
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(string $class, array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make($class, $settings);
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
	private function block(string $slug, array $attrs): string
	{
		RequestSimulator::new_request();
		return render_block(
			array(
				'blockName'    => 'mhm-rentiva/' . $slug,
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}

	private function button_label(string $html): string
	{
		$this->assertSame(1, preg_match('#<a [^>]*data-testid="vehicle-book-btn"[^>]*>(.*?)</a>#s', $html, $m), 'No booking button in the render.');
		return trim(html_entity_decode(wp_strip_all_tags($m[1])));
	}
}
