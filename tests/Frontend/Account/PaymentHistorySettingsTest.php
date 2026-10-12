<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\PaymentHistoryWidget;
use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingProbe;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Payment history settings, spec 2026-10-10 Elementor bridge slice 3A Task 7b.
 *
 * APPLY: limit (the page size was a literal 10), orderby (date | amount) and
 * order, status (the normalized payment statuses), show_pagination, show_date,
 * show_payment_method, show_booking_link (title links to the booking view; off by
 * default -- Rule 1, the title was never a link).
 * REMOVE: show_invoice_download (no invoice feature), show_transaction_id (low
 * value), limit_results (twin of `limit`; its editor control now writes `limit`).
 * Exception 5 (spec §2.2): the pager moves from core `paged` -- which
 * redirect_canonical() moves into the path on a WooCommerce endpoint -- to
 * mhmrentiva_payments_page.
 *
 * G2 (Rule 1): the default renders (shortcode text, block, widget, WooCommerce
 * endpoint) with fewer payments than the page size stay today's, recorded before
 * the change (tests/fixtures/golden/payment-history/, MHMRENTIVA_RECORD_GOLDEN=1
 * re-records).
 */
final class PaymentHistorySettingsTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/payment-history';

	private int $user = 0;

	/**
	 * The three payments: [id, amount, normalized status], by pickup date newest first.
	 *
	 * @var array<string,int>
	 */
	private array $ids = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->user = ShortcodeFixtures::customer();
		// Pickup dates (the list's date) out of step with amount and status.
		$rows = array(
			'middle' => array( '700', 12, 'paid' ),
			'newest' => array( '1200', 20, 'pending_verification' ),
			'oldest' => array( '300', -10, 'refunded' ),
		);
		foreach ($rows as $name => $row) {
			$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', $row[0], $row[1]);
			update_post_meta($id, '_mhmrentiva_payment_status', $row[2]);
			$this->ids[ $name ] = $id;
		}

		wp_set_current_user($this->user);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		set_query_var('mhmrentiva_payments_page', '');
		set_query_var('paged', '');
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
		$html = $this->render_surface(substr($name, strlen('payment-history-')), array());
		$this->assertSame(3, substr_count($html, 'data-testid="payment-item"'), "$name rendered the three payments; the golden would pin an empty render.");

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
		$names = array( 'payment-history-shortcode', 'payment-history-block', 'payment-history-widget', 'payment-history-endpoint' );
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	/**
	 * `limit` is the page size; its default is today's literal 10 on every surface
	 * (Rule 1: the block's '20' was pulled to '10').
	 */
	public function test_limit_sets_page_size(): void
	{
		$this->more_payments(9); // 12 in all.

		foreach (array( 'shortcode', 'block', 'widget', 'endpoint' ) as $surface) {
			$html = $this->render_surface($surface, array());
			$this->assertCount(10, $this->rows($html), "$surface: 10 per page by default.");
			$this->assertStringContainsString('Page 1 / 2', $html, "$surface pager.");
		}

		$five = array(
			'shortcode' => $this->shortcode('limit="5"'),
			'block'     => $this->block(array( 'limit' => '5' )),
			'widget'    => $this->widget(array( 'limit' => '5' )),
		);
		foreach ($five as $surface => $html) {
			$this->assertCount(5, $this->rows($html), "$surface: limit 5.");
			$this->assertStringContainsString('Page 1 / 3', $html, "$surface: 12 payments, 3 pages.");
		}

		$block_json = SettingProbe::block_json('payment-history', array())['attributes'];
		$this->assertSame('10', $block_json['limit']['default'], 'Rule 1: the block default is today\'s page size.');
	}

	/**
	 * T10a item 5: a limit of 0, empty or negative is not a page size of 1 row;
	 * it falls back to today's 10.
	 */
	public function test_non_positive_limit_falls_back_to_ten(): void
	{
		$this->more_payments(9); // 12 in all.

		$renders = array(
			'shortcode 0'     => $this->shortcode('limit="0"'),
			'shortcode empty' => $this->shortcode('limit=""'),
			'shortcode -3'    => $this->shortcode('limit="-3"'),
			'block 0'         => $this->block(array( 'limit' => '0' )),
			'widget empty'    => $this->widget(array( 'limit' => '' )),
		);
		foreach ($renders as $surface => $html) {
			$this->assertCount(10, $this->rows($html), "$surface: 10 per page.");
			$this->assertStringContainsString('Page 1 / 2', $html, "$surface pager.");
		}
	}

	/**
	 * T10a item 6 (plan ruling "missing values last"): a payment without an
	 * amount goes last in both amount orders, as an undated one does by date.
	 */
	public function test_missing_amount_goes_last_in_both_orders(): void
	{
		$unpriced = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '50', -2);
		delete_post_meta($unpriced, '_mhmrentiva_total_price');
		$this->assertSame('', get_post_meta($unpriced, '_mhmrentiva_total_price', true));

		foreach (array( 'desc', 'asc' ) as $order) {
			$rows = $this->rows($this->shortcode('orderby="amount" order="' . $order . '"'));
			$this->assertCount(4, $rows);
			$this->assertSame($unpriced, end($rows), "amount $order: the payment without an amount is last.");
		}
	}

	/**
	 * Exception 5: the pager reads and writes mhmrentiva_payments_page; core `paged` (which
	 * redirect_canonical() moves into the endpoint path) is not read any more.
	 * show_pagination off is today's first page without a pager.
	 */
	public function test_pager_uses_page_var(): void
	{
		$this->more_payments(9); // 12 in all, limit 10: 2 on page 2.

		$first = $this->shortcode('');
		$this->assertStringContainsString('mhmrentiva_payments_page=2', $first, 'Next links to mhmrentiva_payments_page=2.');
		$this->assertStringNotContainsString('paged=', $first, 'No core paged link.');

		set_query_var('mhmrentiva_payments_page', '2');
		foreach (array( 'shortcode', 'block', 'widget', 'endpoint' ) as $surface) {
			$html = $this->render_surface($surface, array());
			$this->assertCount(2, $this->rows($html), "$surface page 2.");
			$this->assertStringContainsString('Page 2 / 2', $html, "$surface page 2 label.");
			$this->assertStringNotContainsString('mhmrentiva_payments_page=1', $html, "$surface: Previous goes to the bare URL.");
		}

		// Past the end: the last page (Review Focus 2).
		set_query_var('mhmrentiva_payments_page', '999');
		$this->assertStringContainsString('Page 2 / 2', $this->shortcode(''));
		set_query_var('mhmrentiva_payments_page', '99999999999999999999');
		$this->assertStringContainsString('Page 2 / 2', $this->shortcode(''));

		// Core paged is not the page any more.
		set_query_var('mhmrentiva_payments_page', '');
		set_query_var('paged', 2);
		$this->assertStringContainsString('Page 1 / 2', $this->shortcode(''));
		set_query_var('paged', '');

		// The links keep the other vars of the current URL.
		$_SERVER['REQUEST_URI'] = '/account/odeme-gecmisi/?zz_keep=1';
		$this->assertMatchesRegularExpression('#href="[^"]*zz_keep=1[^"]*mhmrentiva_payments_page=2#', html_entity_decode($this->shortcode('')));

		// Off: no pager, and the page var is ignored (today's first page).
		set_query_var('mhmrentiva_payments_page', '2');
		$off = array(
			'shortcode' => $this->shortcode('show_pagination="0"'),
			'block'     => $this->block(array( 'showPagination' => false )),
			'widget'    => $this->widget(array( 'show_pagination' => '' )),
		);
		foreach ($off as $surface => $html) {
			$this->assertStringNotContainsString('mhm-pagination', $html, "$surface: show_pagination off.");
			$this->assertCount(10, $this->rows($html), "$surface: the first page only.");
		}
		set_query_var('mhmrentiva_payments_page', '');
	}

	/**
	 * orderby date | amount, both orders, on every surface; sorted before paging; a
	 * payment without a pickup date goes last in both date orders.
	 */
	public function test_sort_by_amount_and_date_both_orders(): void
	{
		$m = $this->ids['middle'];
		$n = $this->ids['newest'];
		$o = $this->ids['oldest'];

		$cases = array(
			array( 'date', 'desc', array( $n, $m, $o ) ),
			array( 'date', 'asc', array( $o, $m, $n ) ),
			array( 'amount', 'desc', array( $n, $m, $o ) ),
			array( 'amount', 'asc', array( $o, $m, $n ) ),
		);
		// Amount and date agree above; one more payment tells them apart: the
		// highest amount with the oldest date.
		$big = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '5000', -30);
		$cases[0][2][] = $big;
		array_unshift($cases[1][2], $big);
		array_unshift($cases[2][2], $big);
		$cases[3][2][] = $big;

		foreach ($cases as $case) {
			list( $orderby, $order, $expected ) = $case;
			$renders = array(
				'shortcode' => $this->shortcode('orderby="' . $orderby . '" order="' . $order . '"'),
				'block'     => $this->block(
					array(
						'sortBy'    => $orderby,
						'sortOrder' => $order,
					)
				),
				'widget'    => $this->widget(
					array(
						'orderby' => $orderby,
						'order'   => $order,
					)
				),
			);
			foreach ($renders as $surface => $html) {
				$this->assertSame($expected, $this->rows($html), "$surface: $orderby $order.");
			}
		}

		// Sorted before paging: page 2 of amount asc at limit 2 holds the two largest.
		set_query_var('mhmrentiva_payments_page', '2');
		$this->assertSame(array( $n, $big ), $this->rows($this->shortcode('orderby="amount" order="asc" limit="2"')));
		set_query_var('mhmrentiva_payments_page', '');

		// Missing pickup date: last in both date orders.
		$undated = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '50', 1);
		foreach (array( '_mhmrentiva_pickup_date', '_mhmrentiva_start_time', '_mhmrentiva_pickup_time', '_mhmrentiva_booking_pickup_time' ) as $meta) {
			delete_post_meta($undated, $meta); // A time alone would date it today.
		}
		foreach (array( 'desc', 'asc' ) as $order) {
			$rows = $this->rows($this->shortcode('orderby="date" order="' . $order . '"'));
			$this->assertSame($undated, end($rows), "date $order: the undated payment is last.");
		}

		// The editor offers the schema's values (G3), 'status' is gone.
		$schema = AllowlistRegistry::get_schema('rentiva_payment_history');
		$this->assertSame(array( 'date', 'amount' ), $schema['orderby']['values']);
		$this->assertSame(array( 'date', 'amount' ), SettingProbe::block_json('payment-history', array())['attributes']['sortBy']['enum']);
	}

	/**
	 * status filters by the normalized payment status, before paging, so the rows,
	 * the total and the page count agree. 'all' is no filter.
	 */
	public function test_status_filter_values(): void
	{
		$schema = AllowlistRegistry::get_schema('rentiva_payment_history');
		$this->assertSame(array( '', 'pending', 'completed', 'cancelled' ), $schema['status']['values']);
		$this->assertSame(array( 'all', 'pending', 'completed', 'cancelled' ), SettingProbe::block_json('payment-history', array())['attributes']['filterStatus']['enum']);

		$cases = array(
			'completed' => array( $this->ids['middle'] ),
			'pending'   => array( $this->ids['newest'] ),
			'cancelled' => array( $this->ids['oldest'] ),
			'all'       => array( $this->ids['newest'], $this->ids['middle'], $this->ids['oldest'] ),
			''          => array( $this->ids['newest'], $this->ids['middle'], $this->ids['oldest'] ),
		);
		foreach ($cases as $status => $expected) {
			$this->assertSame($expected, $this->rows($this->shortcode('status="' . $status . '"')), "shortcode status '$status'.");
			$this->assertSame($expected, $this->rows($this->block(array( 'filterStatus' => $status ))), "block filterStatus '$status'.");
			$this->assertSame($expected, $this->rows($this->widget(array( 'status' => $status ))), "widget status '$status'.");
		}
		$this->assertStringContainsString('1 payments total', $this->shortcode('status="pending"'), 'The total counts the filtered list.');

		// A saved value of a removed option (paid/refunded/failed) is no filter, as today.
		$this->assertCount(3, $this->rows($this->block(array( 'filterStatus' => 'refunded' ))));

		// Before paging: 12 pending among 15, limit 10 -> 2 pages of pending only.
		$this->more_payments(11, 'pending');
		$first = $this->shortcode('status="pending"');
		$this->assertCount(10, $this->rows($first));
		$this->assertStringContainsString('Page 1 / 2', $first);
		set_query_var('mhmrentiva_payments_page', '2');
		$second = $this->shortcode('status="pending"');
		$this->assertCount(2, $this->rows($second));
		$this->assertStringContainsString('Page 2 / 2', $second);
		$this->assertStringContainsString('12 payments total', $second);
		set_query_var('mhmrentiva_payments_page', '');
		$this->assertStringNotContainsString('mhm-pagination', $this->shortcode('status="completed"'), 'One completed payment, no pager.');
	}

	/**
	 * show_date, show_payment_method and show_booking_link follow their flag on
	 * every surface; the booking link is off by default (the title never linked).
	 */
	public function test_columns_follow_flags(): void
	{
		foreach (array( 'shortcode', 'block', 'widget', 'endpoint' ) as $surface) {
			$html = $this->render_surface($surface, array());
			$this->assertSame(3, substr_count($html, 'class="payment-date"'), "$surface default shows the date.");
			$this->assertSame(3, substr_count($html, 'class="payment-method"'), "$surface default shows the method.");
			$this->assertStringNotContainsString('payment-booking-link', $html, "$surface default: the title is not a link (Rule 1).");
		}

		$flags = array(
			'show_date'           => array( 'class="payment-date"', 'showDate' ),
			'show_payment_method' => array( 'class="payment-method"', 'showPaymentMethod' ),
		);
		foreach ($flags as $key => $flag) {
			$off = array(
				'shortcode' => $this->shortcode($key . '="0"'),
				'block'     => $this->block(array( $flag[1] => false )),
				'widget'    => $this->widget(array( $key => '' )),
			);
			foreach ($off as $surface => $html) {
				$this->assertCount(3, $this->rows($html), "$surface rendered the payments.");
				$this->assertStringNotContainsString($flag[0], $html, "$surface: $key off.");
				foreach ($flags as $other => $other_flag) {
					if ($other !== $key) {
						$this->assertSame(3, substr_count($html, $other_flag[0]), "$surface: $key off leaves $other.");
					}
				}
			}
		}
		$this->assertStringNotContainsString('Payment Method:', $this->shortcode('show_payment_method="0"'), 'The label goes with the value.');

		$on = array(
			'shortcode' => $this->shortcode('show_booking_link="1"'),
			'block'     => $this->block(array( 'showBookingLink' => true )),
			'widget'    => $this->widget(array( 'show_booking_link' => 'yes' )),
		);
		foreach ($on as $surface => $html) {
			$this->assertSame(3, substr_count($html, 'class="payment-booking-link"'), "$surface: show_booking_link on.");
			$this->assertStringContainsString('href="' . esc_url(AccountController::get_booking_view_url($this->ids['newest'])) . '"', $html, "$surface links to the booking view.");
		}
		$this->assertFalse(SettingProbe::block_json('payment-history', array())['attributes']['showBookingLink']['default'], 'Rule 1: off by default.');
	}

	/**
	 * Rule 2: "Limit Results" wrote limitResults, which nothing read; it now writes
	 * `limit`. Review Focus 4: saved values of the removed settings change nothing.
	 */
	public function test_removed_settings_are_inert(): void
	{
		$js = (string) file_get_contents(dirname(__DIR__, 3) . '/assets/blocks/payment-history/index.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local asset.
		$this->assertStringNotContainsString('limitResults', $js);
		$this->assertSame(1, substr_count($js, 'setAttributes({ limit: val })'), 'One editor control writes limit.');
		$this->assertStringContainsString("__('Limit Results', 'mhm-rentiva')", $js, 'The control keeps its label.');

		$attributes = SettingProbe::block_json('payment-history', array())['attributes'];
		foreach (array( 'showInvoiceDownload', 'showTransactionId', 'limitResults' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $attributes, "block.json $gone");
		}
		$controls = SettingProbe::data_controls('PaymentHistoryWidget');
		foreach (array( 'show_invoice_download', 'show_transaction_id', 'limit_results' ) as $gone) {
			$this->assertArrayNotHasKey($gone, $controls, "Elementor $gone");
		}

		$this->assertSame(
			RenderNormalizer::normalize($this->widget(array())),
			RenderNormalizer::normalize(
				$this->widget(
					array(
						'show_invoice_download' => '',
						'show_transaction_id'   => '',
						'limit_results'         => '1',
					)
				)
			)
		);
		$this->assertSame(
			RenderNormalizer::normalize($this->block(array())),
			RenderNormalizer::normalize(
				$this->block(
					array(
						'showInvoiceDownload' => false,
						'showTransactionId'   => false,
						'limitResults'        => '1',
					)
				)
			)
		);
	}

	/**
	 * $n more payments for the fixture customer, older than the three.
	 *
	 * @return list<int>
	 */
	private function more_payments(int $n, string $payment_status = 'paid'): array
	{
		$out = array();
		for ($i = 0; $i < $n; $i++) {
			$id = ShortcodeFixtures::booking($this->user, ShortcodeFixtures::vehicle(), 'confirmed', '100', -20 - $i);
			update_post_meta($id, '_mhmrentiva_payment_status', $payment_status);
			$out[] = $id;
		}
		return $out;
	}

	/**
	 * Booking ids of the rendered payment rows, in order.
	 *
	 * @return list<int>
	 */
	private function rows(string $html): array
	{
		preg_match_all('#class="mhm-upload-receipt" data-booking-id="(\d+)"#', $html, $m);
		return array_map('intval', $m[1]);
	}

	/**
	 * golden(): fixture vehicle numbers named, the pickup date (relative to the day
	 * the suite runs) masked.
	 */
	private function golden_form(string $html): string
	{
		$html = (string) preg_replace('#(<span class="payment-date">)[^<]*(</span>)#', '$1DATE$2', $html);
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
					WooCommerceIntegration::render_payment_history();
				} finally {
					$html = (string) ob_get_clean();
				}
				return $html;
		}
		throw new \InvalidArgumentException($surface);
	}

	private function shortcode(string $atts): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_payment_history ' . $atts . ']');
	}

	/**
	 * @param array<string,mixed> $settings Saved widget settings.
	 */
	private function widget(array $settings): string
	{
		RequestSimulator::new_request();
		$widget = WidgetFactory::make(PaymentHistoryWidget::class, $settings);
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
				'blockName'    => 'mhm-rentiva/payment-history',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(),
			)
		);
	}
}
