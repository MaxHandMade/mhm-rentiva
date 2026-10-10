<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\MyBookingsWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * My bookings settings, spec 2026-10-10 Elementor bridge slice 3A Task 7a.
 *
 * APPLY: show_image (the Img column was gated only by "not integrated"; it now
 * also needs the flag), show_booking_dates, show_status_toggle, show_price (column
 * toggles: header and cells), show_pagination (pager over `limit`, page from the
 * public query var mhmrentiva_page -- AccountPageVarTest).
 * REMOVE: show_cancel_btn and show_modify_btn (no such feature on the list),
 * show_details_link (the only path to detail/cancel/receipt), orderby (every
 * option collapsed to date), limit_results (twin of the live `limit`; its editor
 * control now writes `limit`, Rule 2).
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget, WooCommerce
 * endpoint) with fewer bookings than `limit` stay today's, recorded before the
 * change (tests/fixtures/golden/my-bookings/, MHMRENTIVA_RECORD_GOLDEN=1
 * re-records).
 */
final class MyBookingsSettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/my-bookings';

	private int $user = 0;

	/** @var list<int> Bookings, newest first. */
	private array $bookings = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->user = ShortcodeFixtures::customer();
		// Two upcoming and one past booking, created on three different days.
		$rows = array(
			array( 'confirmed', '700', 1, 12 ),
			array( 'pending', '1200', 3, 20 ),
			array( 'completed', '300', 6, -10 ),
		);
		foreach ($rows as $i => $row) {
			$id   = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), $row[0], $row[1], $row[3]);
			$date = gmdate('Y-m-d H:i:s', time() - $row[2] * DAY_IN_SECONDS);
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => $date,
					'post_date_gmt' => $date,
				)
			);
			$this->bookings[] = $id;
		}

		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		set_query_var('mhmrentiva_page', '');
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * G2: the default render of each surface is today's.
	 *
	 * @dataProvider default_surfaces
	 */
	public function test_default_renders_unchanged(string $name): void
	{
		$html = $this->render_surface(substr($name, strlen('my-bookings-')), array());
		$this->assertStringContainsString('rv-bookings-table', $html, "$name rendered no bookings; the golden would pin an empty render.");

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
		$names = array( 'my-bookings-shortcode', 'my-bookings-block', 'my-bookings-widget', 'my-bookings-endpoint' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * Date/Time, Status and Fee: header and cells follow their flag on every surface.
	 * The vehicle image column also needs a page that is not integrated (no
	 * WooCommerce), so it is proven on the template with the non-WooCommerce
	 * navigation, the way HideNavHasEffectTest proves hide_nav.
	 */
	public function test_columns_follow_flags(): void
	{
		$columns = array(
			'show_booking_dates' => array( 'Date/Time', 'rv-booking-date', 'showBookingDates' ),
			'show_status_toggle' => array( 'Status', 'rv-booking-status', 'showStatus' ),
			'show_price'         => array( 'Fee', 'rv-booking-price', 'showPrice' ),
		);

		foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
			$default = $this->render_surface($surface, array());
			foreach ($columns as $key => $col) {
				$this->assertSame(2, preg_match_all('#<th[^>]*>' . preg_quote($col[0], '#') . '</th>#', $default), "$surface default has the $key header in both tables.");
				$this->assertSame(3, substr_count($default, 'class="' . $col[1] . '"'), "$surface default shows $key cells.");
			}
		}

		foreach ($columns as $key => $col) {
			$off = array(
				'shortcode' => $this->shortcode($key . '="0"'),
				'block'     => $this->block(array( $col[2] => false )),
				'widget'    => $this->widget(array( $key => '' )),
			);
			foreach ($off as $surface => $html) {
				$this->assertSame(3, substr_count($html, 'class="rv-booking-id"'), "$surface rendered the bookings.");
				$this->assertSame(0, substr_count($html, 'class="' . $col[1] . '"'), "$surface hides the $key cells.");
				$this->assertSame(0, preg_match('#<th[^>]*>' . preg_quote($col[0], '#') . '</th>#', $html), "$surface hides the $key header.");
				foreach ($columns as $other => $other_col) {
					if ($other !== $key) {
						$this->assertSame(3, substr_count($html, 'class="' . $other_col[1] . '"'), "$surface: $key off leaves $other.");
					}
				}
			}
		}

		// show_image: with a navigation (no WooCommerce) the Img column shows by
		// default and the flag removes it; integrated pages never had it.
		$with_nav = function (array $atts): string {
			$data                       = AccountRenderer::get_bookings_data($atts);
			$data['data']['navigation'] = array(
				'bookings' => array(
					'title' => 'Bookings',
					'url'   => 'https://example.test/account/?endpoint=bookings',
					'icon'  => '*',
				),
			);
			return Templates::render('account/bookings', $data, true);
		};
		$this->assertSame(3, substr_count($with_nav(array()), 'class="rv-vehicle-thumb"'), 'The Img column shows by default on a non-integrated page.');
		$this->assertSame(3, substr_count($with_nav(array( 'show_image' => '1' )), 'class="rv-vehicle-thumb"'));
		$hidden = $with_nav(array( 'show_image' => '0' ));
		$this->assertStringNotContainsString('rv-vehicle-thumb', $hidden, 'show_image off hides the cells.');
		$this->assertStringNotContainsString('>Img</th>', $hidden, 'show_image off hides the header.');
		$this->assertStringNotContainsString('rv-vehicle-thumb', $this->shortcode(''), 'An integrated page has no Img column (unchanged).');

		// Each surface hands show_image over to the renderer.
		$this->assertFalse($this->received_flag('show_image', fn() => $this->block(array( 'showVehicleImage' => false ))));
		$this->assertTrue($this->received_flag('show_image', fn() => $this->block(array())));
		$this->assertFalse($this->received_flag('show_image', fn() => $this->widget(array( 'show_image' => '' ))));
		$this->assertTrue($this->received_flag('show_image', fn() => $this->widget(array())));
		$this->assertFalse($this->received_flag('show_image', fn() => $this->shortcode('show_image="0"')));
	}

	/**
	 * More bookings than `limit`: the pager links to the older ones, keeping the
	 * list's filter vars; show_pagination off is today's list (first page only).
	 */
	public function test_pagination_shows_older_bookings(): void
	{
		$newest = $this->bookings[0];
		$middle = $this->bookings[1];
		$oldest = $this->bookings[2];

		$first = $this->shortcode('limit="2"');
		$this->assertSame(array( $newest, $middle ), $this->ids($first));
		$this->assertStringContainsString('mhm-pagination', $first);
		$this->assertStringContainsString('Page 1 / 2', $first);
		$this->assertStringContainsString('mhmrentiva_page=2', $first, 'Next links to page 2.');

		set_query_var('mhmrentiva_page', '2');
		$second = $this->shortcode('limit="2"');
		$this->assertSame(array( $oldest ), $this->ids($second), 'Page 2 shows the older booking.');
		$this->assertStringContainsString('Page 2 / 2', $second);

		foreach (array( 'block' => $this->block(array( 'limit' => '2' )), 'widget' => $this->widget(array( 'limit' => 2 )), 'endpoint' => $this->endpoint_with_limit(2) ) as $surface => $html) {
			$this->assertSame(array( $oldest ), $this->ids($html), "$surface page 2.");
		}
		set_query_var('mhmrentiva_page', '');

		// The links keep the filter vars of the current URL.
		$_SERVER['REQUEST_URI'] = '/account/rentiva-bookings/?mhmrentiva_status_filter=all&mhmrentiva_search_booking=1';
		$html                   = $this->shortcode('limit="1"');
		$this->assertMatchesRegularExpression('#href="[^"]*mhmrentiva_status_filter=all[^"]*mhmrentiva_search_booking=1[^"]*mhmrentiva_page=2#', html_entity_decode($html));

		// Off: no pager, and the page var is not honoured (today's list).
		set_query_var('mhmrentiva_page', '2');
		$off = $this->shortcode('limit="2" show_pagination="0"');
		$this->assertStringNotContainsString('mhm-pagination', $off);
		$this->assertSame(array( $newest, $middle ), $this->ids($off));
		$off_block = $this->block(array( 'limit' => '2', 'showPagination' => false ));
		$this->assertStringNotContainsString('mhm-pagination', $off_block);
		$off_widget = $this->widget(array( 'limit' => 2, 'show_pagination' => '' ));
		$this->assertStringNotContainsString('mhm-pagination', $off_widget);
		set_query_var('mhmrentiva_page', '');

		// One page: no pager (the default render, as the goldens pin).
		$this->assertStringNotContainsString('mhm-pagination', $this->shortcode(''));
	}

	/**
	 * Rule 2: "Limit Results" wrote limitResults, which nothing read; it now writes
	 * the live `limit`, and one control writes it.
	 */
	public function test_limit_results_control_writes_limit(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/my-bookings/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('limitResults', $js);
		$this->assertSame(1, substr_count($js, 'setAttributes({ limit: val })'), 'One editor control writes limit.');
		$this->assertStringContainsString("__('Limit Results', 'mhm-rentiva')", $js, 'The control keeps its label.');

		$this->assertSame(1, count($this->ids($this->block(array( 'limit' => '1' )))));
	}

	/**
	 * Review Focus 4: removed controls' saved values change nothing.
	 */
	public function test_saved_value_of_removed_control_is_inert(): void
	{
		$attributes = SettingProbe::block_json('my-bookings', array())['attributes'];
		foreach (array( 'showCancelButton', 'showModifyButton', 'showDetailsLink', 'sortBy', 'limitResults' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$controls = SettingProbe::data_controls('MyBookingsWidget');
		foreach (array( 'show_cancel_btn', 'show_modify_btn', 'show_details_link', 'orderby', 'limit_results' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'show_cancel_btn'   => '',
						'show_modify_btn'   => '',
						'show_details_link' => '',
						'orderby'           => 'id',
						'limit_results'     => '1',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'showCancelButton' => false,
						'showModifyButton' => false,
						'showDetailsLink'  => false,
						'sortBy'           => 'price',
						'limitResults'     => '1',
					)
				)
			)
		);
		// The details link stays: it is the only path to detail, cancel and receipt.
		$this->assertSame(3, substr_count($this->shortcode('show_details_link="0"'), 'rv-btn-icon-only'));
	}

	/**
	 * Booking ids of the rendered rows, in order.
	 *
	 * @return list<int>
	 */
	private function ids(string $html): array
	{
		preg_match_all('#<td class="rv-booking-id">\#(\d+)</td>#', $html, $m);
		return array_map('intval', $m[1]);
	}

	/**
	 * Whether the renderer received $flag as on, read off the shortcode's _html filter.
	 */
	private function received_flag(string $flag, callable $render): bool
	{
		$seen   = null;
		$filter = static function ($html, $atts) use (&$seen) {
			$seen ??= (array) $atts;
			return $html;
		};
		add_filter('mhmrentiva_shortcodes_rentiva_my_bookings_html', $filter, 10, 2);
		try {
			$render();
		} finally {
			remove_filter('mhmrentiva_shortcodes_rentiva_my_bookings_html', $filter, 10);
		}
		$this->assertNotNull($seen, 'The shortcode _html filter never ran.');
		return ! in_array($seen[ $flag ] ?? '1', array( '0', 0, false, 'false', 'no' ), true);
	}

	/**
	 * golden(): booking ids and fixture vehicle numbers named, the pickup date
	 * (relative to the day the suite runs) masked.
	 */
	private function golden_form(string $html): string
	{
		$map = array();
		foreach ($this->bookings as $i => $id) {
			$map[ $id ] = 'BOOKING' . $i;
		}
		$html = (string) preg_replace_callback(
			'/(?<=[#=\/])(\d+)(?=[^\d])/',
			static fn(array $m): string => $map[ (int) $m[1] ] ?? $m[1],
			$html
		);
		$html = (string) preg_replace('/Fixture Vehicle \d+/', 'Fixture Vehicle N', $html);
		$html = (string) preg_replace('#(<td class="rv-booking-date">)[^<]*(</td>)#', '$1DATE$2', $html);
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
			case 'endpoint':
				RequestSimulator::new_request();
				ob_start();
				try {
					WooCommerceIntegration::render_bookings();
				} finally {
					$html = (string) ob_get_clean();
				}
				return $html;
		}
		throw new \InvalidArgumentException($surface);
	}

	/**
	 * The WooCommerce endpoint renders with hide_nav only; its page size is the
	 * default. Page 2 at limit 2 goes through the same renderer with that limit.
	 */
	private function endpoint_with_limit(int $limit): string
	{
		ob_start();
		try {
			AccountRenderer::output_bookings(
				array(
					'hide_nav' => true,
					'limit'    => $limit,
				)
			);
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_my_bookings ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(MyBookingsWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/my-bookings',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
