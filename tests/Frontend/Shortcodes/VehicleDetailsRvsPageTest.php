<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use WP_UnitTestCase;

/**
 * Aşama 2 PR B: the vehicle page on the front-end standard -- the canvas
 * sections in order inside .rvs-shell, every flag hiding its own section,
 * unique ids per instance, and the rvs stylesheet instead of the old one.
 *
 * @covers \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails
 */
final class VehicleDetailsRvsPageTest extends WP_UnitTestCase
{
	use \MHMRentiva\Tests\Support\StyleDependencies;

	private int $vehicle_id;

	public function setUp(): void
	{
		parent::setUp();
		RequestSimulator::new_request();
		$this->vehicle_id = (int) self::factory()->post->create(array(
			'post_type'    => 'mhmrentiva_vehicle',
			'post_status'  => 'publish',
			'post_title'   => 'Hyundai Tucson',
			'post_content' => 'Hybrid SUV.',
		));
		update_post_meta($this->vehicle_id, '_mhmrentiva_price_per_day', '1000');
		update_post_meta($this->vehicle_id, '_mhmrentiva_seats', '5');
		$image = self::factory()->attachment->create_upload_object(DIR_TESTDATA . '/images/canola.jpg', $this->vehicle_id);
		set_post_thumbnail($this->vehicle_id, $image);
		update_option('mhmrentiva_settings', SettingsSanitizer::sanitize(array( 'current_active_tab' => 'vehicle' )));
	}

	public function tearDown(): void
	{
		delete_option('mhmrentiva_settings');
		RequestSimulator::new_request();
		parent::tearDown();
	}

	private function render(string $extra = ''): string
	{
		return (string) do_shortcode('[rentiva_vehicle_details vehicle_id="' . $this->vehicle_id . '"' . $extra . ']');
	}

	public function test_renders_in_shell_with_sections_in_canvas_order(): void
	{
		$html = $this->render();

		$this->assertStringContainsString('rvs-shell', $html);
		$order = array( 'rvs-vd__crumbs', 'rvs-vd__head', 'rvs-vd__gallery', '-highlights"', '-conditions"', '-availability"', '-reviews-title"', 'rvs-vd__booking' );
		$last  = -1;
		foreach ($order as $needle) {
			$pos = strpos($html, $needle);
			$this->assertNotFalse($pos, $needle);
			$this->assertGreaterThan($last, $pos, $needle . ' is out of order');
			$last = (int) $pos;
		}
		$this->assertStringContainsString('<h1 class="rvs-vd__title">Hyundai Tucson</h1>', $html);
		$this->assertStringContainsString('data-rvs-vd-continue', $html);
		$this->assertStringContainsString('data-rvs-calendar', $html);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function flags(): array
	{
		return array(
			'gallery'   => array( 'show_gallery', 'rvs-vd__gallery' ),
			'features'  => array( 'show_features', '-highlights"' ),
			'calendar'  => array( 'show_calendar', 'data-rvs-vd-months' ),
			// Owner request (PR B review): a site can drop the whole section.
			'conditions' => array( 'show_conditions', '-conditions"' ),
			'reviews'   => array( 'show_reviews', '-reviews-title"' ),
			'booking'   => array( 'show_booking', 'rvs-vd__booking' ),
			'button'    => array( 'show_booking_button', 'rvs-vd__booking' ),
			'price'     => array( 'show_price', 'data-rvs-vd-total' ),
			'pricing'   => array( 'show_pricing', 'data-rvs-vd-breakdown' ),
			'favorite'  => array( 'show_favorite_button', 'mhm-vehicle-favorite-btn' ),
			'compare'   => array( 'show_compare_button', 'mhm-vehicle-compare-btn' ),
		);
	}

	/**
	 * @dataProvider flags
	 */
	public function test_flags_hide_their_sections(string $flag, string $needle): void
	{
		$this->assertStringContainsString($needle, $this->render());
		RequestSimulator::new_request();
		$this->assertStringNotContainsString($needle, $this->render(' ' . $flag . '="0"'));
	}

	public function test_show_conditions_reaches_block_and_widget(): void
	{
		$block = (string) do_blocks('<!-- wp:mhm-rentiva/vehicle-details {"vehicleId":"' . $this->vehicle_id . '","showConditions":false} /-->');
		$this->assertStringContainsString('rvs-vd__layout', $block);
		$this->assertStringNotContainsString('-conditions"', $block);

		$widget   = \MHMRentiva\Tests\Support\WidgetFactory::make(\MHMRentiva\Admin\Frontend\Widgets\Elementor\VehicleDetailsWidget::class);
		$controls = $widget->get_controls();
		$this->assertArrayHasKey('show_conditions', $controls);
		$atts = ( new \ReflectionMethod($widget, 'prepare_shortcode_attributes') );
		$atts->setAccessible(true);
		$this->assertSame('0', (string) ( $atts->invoke($widget, array( 'show_conditions' => '' ))['show_conditions'] ?? '' ));
	}

	public function test_rating_line_follows_show_rating(): void
	{
		$comment = self::factory()->comment->create(array( 'comment_post_ID' => $this->vehicle_id, 'comment_type' => 'review', 'comment_approved' => '1' ));
		update_comment_meta($comment, 'mhmrentiva_rating', '5');
		\MHMRentiva\Admin\Vehicle\Helpers\RatingHelper::recalculate_and_save($this->vehicle_id);

		$this->assertStringContainsString('rvs-vd__rating', $this->render());
		RequestSimulator::new_request();
		$this->assertStringNotContainsString('rvs-vd__rating', $this->render(' show_rating="0"'));
	}

	public function test_favourite_and_compare_are_icon_buttons_with_labels(): void
	{
		$html = $this->render();

		// One set of buttons (two copies would let their states drift apart); the
		// label is its own span so the phone layout can show the icon alone.
		$this->assertSame(1, substr_count($html, 'mhm-vehicle-favorite-btn'));
		$this->assertSame(1, substr_count($html, 'mhm-vehicle-compare-btn'));
		$this->assertMatchesRegularExpression('/mhm-vehicle-favorite-btn[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<span class="rvs-vd__action-label">Favorite<\/span>/s', $html);
		$this->assertMatchesRegularExpression('/mhm-vehicle-compare-btn[^>]*>\s*<svg[^>]*>.*?<\/svg>\s*<span class="rvs-vd__action-label">Compare<\/span>/s', $html);
	}

	public function test_phone_puts_the_actions_on_the_photo(): void
	{
		$css = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/css/rvs/rvs-vehicle.css'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test reads a shipped asset.
		$phone = substr($css, (int) strpos($css, '@media (max-width: 768px)'));

		$this->assertMatchesRegularExpression('/\.rvs-vd__actions \{[^}]*position: absolute;/', $phone);
		$this->assertMatchesRegularExpression('/\.rvs-vd__action-label \{[^}]*clip: rect\(0, 0, 0, 0\);/', $phone);
		$this->assertMatchesRegularExpression('/\.rvs-vd__actions \.rvs-btn \{[^}]*width: 44px;/', $phone);
	}

	public function test_ids_unique_with_two_instances(): void
	{
		// The nested rating form keeps its own per-vehicle ids until surface 6
		// (PR F) redraws it, so the second instance leaves the reviews out.
		$html = $this->render() . $this->render(' show_similar_vehicles="0" show_reviews="0"');
		preg_match_all('/\sid="([^"]+)"/', $html, $m);
		$this->assertSame(count($m[1]), count(array_unique($m[1])), 'Duplicate ids: ' . implode(', ', array_diff_assoc($m[1], array_unique($m[1]))));
	}

	public function test_old_stylesheet_not_enqueued_on_rvs_page(): void
	{
		$this->render();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-vehicle', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-vehicle-details', 'enqueued'));
		$this->assertFalse(wp_script_is('mhm-rentiva-vehicle-details', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-rvs-dialog', 'enqueued'));
		$this->assertTrue(wp_script_is('mhm-rentiva-rvs-vehicle-js', 'enqueued'));
		$this->assertContains('mhm-rentiva-rvs-dialog', wp_scripts()->registered['mhm-rentiva-rvs-vehicle-js']->deps);
		// PR S: the booking card's date popup wears the rvs skin.
		$this->assertTrue(self::style_needs('mhm-rentiva-rvs-vehicle', 'mhm-rentiva-rvs-datepicker'), 'The vehicle page loads the rvs datepicker skin.');
	}

	public function test_block_render_uses_rvs_style(): void
	{
		do_blocks('<!-- wp:mhm-rentiva/vehicle-details {"vehicleId":"' . $this->vehicle_id . '"} /-->');

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-vehicle', 'enqueued'));
		$this->assertFalse(wp_style_is('mhm-rentiva-vehicle-details', 'enqueued'));
	}

	public function test_transfer_only_vehicle_has_no_booking_card(): void
	{
		update_post_meta($this->vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');

		$this->assertStringNotContainsString('rvs-vd__booking', $this->render());
	}

	/**
	 * A link with the return before pickup shows the message
	 * with the total and days hidden; the script learns the minimum rental.
	 */
	public function test_dates_in_the_wrong_order_show_no_total(): void
	{
		set_query_var('mhmrentiva_pickup_date', '2027-07-20');
		set_query_var('mhmrentiva_return_date', '2027-07-15');
		$html = $this->render();
		set_query_var('mhmrentiva_pickup_date', '');
		set_query_var('mhmrentiva_return_date', '');

		$this->assertSame(2, preg_match_all('/<[^>]*data-rvs-vd-priced[^>]*\shidden[\s>]/', $html), 'Card total and bar total are hidden.');
		$this->assertMatchesRegularExpression('/data-rvs-vd-message role="status">[^<]+</', $html);

		$ordered = $this->render();
		$this->assertSame(2, preg_match_all('/data-rvs-vd-priced/', $ordered));
		$this->assertSame(0, preg_match_all('/<[^>]*data-rvs-vd-priced[^>]*\shidden[\s>]/', $ordered));

		$config = (string) wp_scripts()->get_data('mhm-rentiva-rvs-vehicle-js', 'data');
		$this->assertStringContainsString('"min_days":"' . \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::min_rental_days() . '"', $config);
	}

	/**
	 * The card's dates show the site format from the first
	 * paint; the hidden field after each posts Y-m-d.
	 */
	public function test_card_dates_render_in_the_site_format_and_post_iso(): void
	{
		$saved = get_option('date_format');
		update_option('date_format', 'd/m/Y');
		set_query_var('mhmrentiva_pickup_date', '2027-07-12');
		set_query_var('mhmrentiva_return_date', '2027-07-15');
		try {
			$html = $this->render();
		} finally {
			update_option('date_format', $saved);
			set_query_var('mhmrentiva_pickup_date', '');
			set_query_var('mhmrentiva_return_date', '');
		}

		// Named on the visible field until the script hands the name to the
		// hidden Y-m-d field (datepicker-init.js); the server reads either.
		$this->assertMatchesRegularExpression('/<input id="[^"]+-pickup" type="text" class="rvs-input js-datepicker" name="pickup_date" value="12\/07\/2027"[^>]*>\s*<input type="hidden" value="2027-07-12" data-rvs-date-value>/', $html);
		$this->assertMatchesRegularExpression('/<input id="[^"]+-return" type="text" class="rvs-input js-datepicker" name="return_date" value="15\/07\/2027"[^>]*>\s*<input type="hidden" value="2027-07-15" data-rvs-date-value>/', $html);
	}

	public function test_unavailable_window_disables_continue(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
		set_query_var('mhmrentiva_pickup_date', '2027-07-12');
		set_query_var('mhmrentiva_return_date', '2027-07-15');

		$html = $this->render();
		set_query_var('mhmrentiva_pickup_date', '');
		set_query_var('mhmrentiva_return_date', '');

		$this->assertMatchesRegularExpression('/data-rvs-vd-continue[^>]*aria-disabled="true"/', $html);
		$this->assertMatchesRegularExpression('/data-rvs-vd-message role="status">[^<]+</', $html);
	}

	private function free_car(string $title): int
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => $title,
		));
		update_post_meta($id, '_mhmrentiva_price_per_day', '900');
		update_post_meta($id, '_mhmrentiva_vehicle_status', 'active');
		update_post_meta($id, '_mhmrentiva_seats', '5');
		return $id;
	}

	private function render_window(string $pickup, string $return): string
	{
		set_query_var('mhmrentiva_pickup_date', $pickup);
		set_query_var('mhmrentiva_return_date', $return);
		try {
			return $this->render();
		} finally {
			set_query_var('mhmrentiva_pickup_date', '');
			set_query_var('mhmrentiva_return_date', '');
		}
	}

	/**
	 * The suggestions box between the message and "Continue"; '' when absent.
	 */
	private function alternatives_box(string $html): ?string
	{
		if (1 !== preg_match('/<div class="rvs-vd__alternatives" data-rvs-vd-alternatives role="status">(.*?)<\/div>\s*<a [^>]*data-rvs-vd-continue/s', $html, $m)) {
			return null;
		}
		return $m[1];
	}

	/**
	 * Coordinator ruling (2026-11-02): a page opened with taken dates in the URL
	 * (from search) shows the suggestions under the message on first load --
	 * the same partial the quote endpoint answers with.
	 */
	public function test_first_load_with_taken_dates_shows_the_suggestions(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
		$other = $this->free_car('Renault Clio');
		$this->free_car('Free B');
		$this->free_car('Free C');

		$html = $this->render_window('2027-07-12', '2027-07-15');

		$this->assertMatchesRegularExpression('/data-rvs-vd-message role="status">[^<]+</', $html, 'The red message stays.');
		$box = $this->alternatives_box($html);
		$this->assertNotNull($box, 'The box sits between the message and Continue.');
		$this->assertSame(2, substr_count( (string) $box, 'class="rvs-vd__alt"'), 'At most two suggestions.');
		$this->assertSame(2, preg_match_all('/<a [^>]*class="rvs-btn rvs-btn--ghost rvs-vd__alt-link"/', (string) $box), 'One link each.');
		$this->assertStringContainsString('Renault Clio', (string) $box);
		$this->assertStringContainsString('href="' . esc_url(get_permalink($other)), (string) $box);
		$this->assertStringContainsString('mhmrentiva_pickup_date=2027-07-12', (string) $box);
		$this->assertMatchesRegularExpression('/aria-describedby="(rvs-vd-alt-' . $this->vehicle_id . '-' . $other . ')"/', (string) $box);
	}

	public function test_first_load_with_free_dates_leaves_the_box_empty(): void
	{
		$this->free_car('Renault Clio');

		$html = $this->render_window('2027-07-12', '2027-07-15');

		$this->assertSame('', $this->alternatives_box($html), 'Empty, without even whitespace (the card CSS keys on :empty).');
	}

	private function book_the_car(): void
	{
		$booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($booking, '_mhmrentiva_vehicle_id', (string) $this->vehicle_id);
		update_post_meta($booking, '_mhmrentiva_pickup_date', '2027-07-13');
		update_post_meta($booking, '_mhmrentiva_dropoff_date', '2027-07-14');
		update_post_meta($booking, '_mhmrentiva_status', 'confirmed');
	}

	private function with_deposit(): void
	{
		$settings                                   = (array) get_option('mhmrentiva_settings', array());
		$settings['mhmrentiva_rental_deposit_type'] = 'percentage';
		$settings['mhmrentiva_rental_deposit_rate'] = 10;
		update_option('mhmrentiva_settings', $settings);
	}

	private function daily_html(): string
	{
		return (string) \MHMRentiva\Admin\Booking\Pricing\PriceDisplay::for_shop(\MHMRentiva\Admin\Booking\Pricing\RentalPricing::quote_for_days($this->vehicle_id, 1, 0)->rental_amount)['html'];
	}

	/**
	 * Owner decision (2026-11-03): while the car is taken for the window, the
	 * card shows no total block (total, deposit, due at pickup, breakdown) and
	 * the header and the phone bar show the car's daily price instead.
	 */
	public function test_first_load_with_taken_dates_shows_the_daily_price_and_no_total(): void
	{
		$this->with_deposit();
		$this->book_the_car();

		$html = $this->render_window('2027-07-12', '2027-07-15');

		$this->assertStringNotContainsString('rvs-breakdown__total', $html, 'No "Total" row.');
		$this->assertStringNotContainsString('rvs-breakdown__deposit', $html, 'No deposit row.');
		$this->assertStringNotContainsString('rvs-breakdown__pickup', $html, 'No "Due at pickup" row.');
		$this->assertStringNotContainsString('rvs-breakdown__summary', $html, 'No "Price breakdown" link.');
		$this->assertMatchesRegularExpression('/<button [^>]*data-rvs-vd-breakdown-open[^>]*\shidden>/', $html, 'The phone bar opens no breakdown.');
		$this->assertSame(0, preg_match_all('/<div [^>]*data-rvs-vd-breakdown(?![-\w])(?![^>]*\shidden[\s>])[^>]*>/', $html), 'Both breakdown boxes are hidden.');
		$this->assertSame(0, preg_match_all('/<[^>]*data-rvs-vd-priced(?![^>]*\shidden[\s>])[^>]*>/', $html), 'Every period total is hidden.');

		// Card header and phone bar: the daily price, "/day" after it.
		$daily = $this->daily_html();
		$this->assertNotSame('', $daily);
		$this->assertSame(2, preg_match_all('/<[^>]*data-rvs-vd-daily(?![-\w])(?![^>]*\shidden[\s>])[^>]*>\s*<(?:span|strong)[^>]*data-rvs-vd-daily-amount[^>]*>' . preg_quote($daily, '/') . '<\/(?:span|strong)>\s*<span[^>]*>\/day<\/span>/', $html), 'Header and bar show the daily price.');
		$this->assertMatchesRegularExpression('/data-rvs-vd-message role="status">[^<]+</', $html, 'The red message stays.');
	}

	public function test_first_load_with_free_dates_keeps_the_total_rows(): void
	{
		$this->with_deposit();

		$html = $this->render_window('2027-07-12', '2027-07-15');

		$this->assertStringContainsString('rvs-breakdown__total', $html);
		$this->assertStringContainsString('rvs-breakdown__deposit', $html);
		$this->assertStringContainsString('rvs-breakdown__pickup', $html);
		$this->assertStringContainsString('data-rvs-vd-breakdown-open', $html);
		$this->assertSame(0, preg_match_all('/<[^>]*data-rvs-vd-priced[^>]*\shidden[\s>]/', $html));
		$this->assertMatchesRegularExpression('/data-rvs-vd-days>3 days total</', $html);
		// The daily twins are there for a later taken quote, hidden and empty.
		$this->assertSame(2, preg_match_all('/<[^>]*data-rvs-vd-daily(?![-\w])[^>]*\shidden[\s>]/', $html));
	}

	/**
	 * A booking-rule refusal (past date) keeps today's card: the total stays.
	 */
	public function test_a_rule_refusal_keeps_the_total(): void
	{
		$html = $this->render_window('2020-01-10', '2020-01-13');

		$this->assertMatchesRegularExpression('/data-rvs-vd-message role="status">[^<]+</', $html);
		$this->assertStringContainsString('rvs-breakdown__total', $html);
		$this->assertSame(0, preg_match_all('/<[^>]*data-rvs-vd-priced[^>]*\shidden[\s>]/', $html));
	}

	/**
	 * Fable B7: without the booking card there is nothing to suggest into, so
	 * the finder does not run.
	 *
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function no_card(): array
	{
		return array(
			'show_booking'        => array( array( 'show_booking' => '0' ) ),
			'show_booking_button' => array( array( 'show_booking_button' => 'false' ) ),
		);
	}

	/**
	 * @dataProvider no_card
	 * @param array<string, string> $atts
	 */
	public function test_no_booking_card_runs_no_finder(array $atts): void
	{
		$this->book_the_car();
		$this->free_car('Renault Clio');
		$window = array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2027-07-15' );
		$view   = \MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView::build($this->vehicle_id, array(), $window);
		$this->assertNotSame(array(), $view['booking']['alternatives'], 'With the card the finder runs.');

		$view = \MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView::build($this->vehicle_id, $atts, $window);
		$this->assertSame(array(), $view['booking']['alternatives']);
	}

	public function test_a_transfer_only_car_runs_no_finder(): void
	{
		$this->book_the_car();
		$this->free_car('Renault Clio');
		update_post_meta($this->vehicle_id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_SERVICE_TYPE, 'transfer');

		$view = \MHMRentiva\Admin\Frontend\Shortcodes\Rvs\VehicleDetailsView::build($this->vehicle_id, array(), array( 'pickup_date' => '2027-07-12', 'pickup_time' => '10:00', 'return_date' => '2027-07-15' ));
		$this->assertSame(array(), $view['booking']['alternatives']);
	}

	/**
	 * Fable B2: a suggestion's title id carries the page's vehicle and the
	 * suggested one, so two vehicle roots on one page cannot collide.
	 */
	public function test_suggestion_ids_carry_both_vehicles(): void
	{
		$alts = array(
			array(
				'id'        => 55,
				'title'     => 'Renault Clio',
				'permalink' => 'http://example.org/clio/',
			),
		);
		$a = \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::alternatives_html($alts, 10);
		$b = \MHMRentiva\Admin\Frontend\Shortcodes\VehicleDetails::alternatives_html($alts, 11);

		$this->assertStringContainsString('id="rvs-vd-alt-10-55"', $a);
		$this->assertStringContainsString('aria-describedby="rvs-vd-alt-10-55"', $a);
		$this->assertStringContainsString('id="rvs-vd-alt-11-55"', $b);
	}
}
