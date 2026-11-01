<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\BookingSummaryPage;
use MHMRentiva\Admin\Payment\WooCommerce\CheckoutJourney;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR D Task 5: the printable booking summary (K14, R12, r3 y12).
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\BookingSummaryPage
 */
final class BookingSummaryPageTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private int $customer_id;

	private int $booking_id;

	/** @var list<int> */
	private array $statuses = array();

	public function setUp(): void
	{
		parent::setUp();
		$this->require_woocommerce();

		$this->customer_id = (int) self::factory()->user->create(array( 'role' => 'customer', 'display_name' => 'Ayse Yilmaz' ));
		$vehicle_id        = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_vehicle', 'post_title' => 'Fiat Egea', 'post_status' => 'publish' ));

		$this->booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'publish' ));
		update_post_meta($this->booking_id, '_mhmrentiva_customer_user_id', $this->customer_id);
		update_post_meta($this->booking_id, '_mhmrentiva_customer_name', 'Ayse Yilmaz');
		update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', $vehicle_id);
		update_post_meta($this->booking_id, '_mhmrentiva_status', 'confirmed');
		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'full');
		update_post_meta($this->booking_id, '_mhmrentiva_total_price', '1234.00');
		update_post_meta($this->booking_id, '_mhmrentiva_rental_days', '3');
		update_post_meta($this->booking_id, '_mhmrentiva_pickup_date', gmdate('Y-m-d', time() + 5 * DAY_IN_SECONDS));
		update_post_meta($this->booking_id, '_mhmrentiva_start_time', '10:00');

		// Styles print once per request: start each test with a fresh queue.
		$GLOBALS['wp_styles'] = null;
		add_filter('status_header', array( $this, 'capture_status' ), 10, 2);
	}

	public function tearDown(): void
	{
		remove_filter('status_header', array( $this, 'capture_status' ), 10);
		remove_all_filters('wp_redirect');
		$GLOBALS['wp_styles'] = null;
		$GLOBALS['wp_query']->init();
		parent::tearDown();
	}

	/**
	 * @param string $header Status line.
	 * @param int    $code   Status code.
	 */
	public function capture_status($header, $code)
	{
		$this->statuses[] = (int) $code;

		return $header;
	}

	private function ask(int $booking_id, string $value = '1'): void
	{
		$GLOBALS['wp_query']->init();
		$GLOBALS['wp_query']->set(BookingSummaryPage::QUERY_VAR, $value);
		$GLOBALS['wp_query']->set(\MHMRentiva\Admin\Frontend\Account\AccountController::get_endpoint_slug('view_booking', 'view-vehicle-booking'), (string) $booking_id);
		$this->statuses = array();
	}

	private function document(): string
	{
		$file = BookingSummaryPage::template_include('/theme/index.php');
		$this->assertStringEndsWith('booking-summary.php', $file);

		ob_start();
		include $file;

		return (string) ob_get_clean();
	}

	public function test_owner_gets_document(): void
	{
		wp_set_current_user($this->customer_id);
		$this->ask($this->booking_id);

		BookingSummaryPage::maybe_render();
		$html = $this->document();

		$this->assertStringContainsString((string) mhmrentiva_get_display_id($this->booking_id), $html);
		$this->assertStringContainsString('Booking summary', $html);
		$this->assertStringContainsString('Fiat Egea', $html);
		$this->assertStringContainsString('Ayse Yilmaz', $html);
		$this->assertStringContainsString('1,234', $html, 'The total from the breakdown.');
		$this->assertStringContainsString('This summary is not an invoice.', $html);
		$this->assertStringContainsString('data-rvs-print', $html);
		$this->assertNotContains(404, $this->statuses);
		$this->assertFalse(is_404());
	}

	public function test_other_customer_gets_404(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'customer' )));
		$this->ask($this->booking_id);

		BookingSummaryPage::maybe_render();

		$this->assertContains(404, $this->statuses);
		$this->assertTrue(is_404());
		$this->assertNotSame('', BookingSummaryPage::template_include('/theme/index.php'));
		$this->assertStringNotContainsString('booking-summary.php', BookingSummaryPage::template_include('/theme/index.php'));
		$this->assertSame(array(), BookingSummaryPage::current());
	}

	public function test_missing_booking_gets_404(): void
	{
		wp_set_current_user($this->customer_id);
		$this->ask(999999);

		BookingSummaryPage::maybe_render();

		$this->assertContains(404, $this->statuses);
		$this->assertTrue(is_404());
	}

	public function test_logged_out_redirected(): void
	{
		wp_set_current_user(0);
		$this->ask($this->booking_id);

		$target = '';
		add_filter(
			'wp_redirect',
			static function (string $location) use (&$target): string {
				$target = $location;
				throw new \RuntimeException('redirected');
			}
		);

		try {
			BookingSummaryPage::maybe_render();
			$this->fail('A logged-out request must be redirected.');
		} catch (\RuntimeException $e) {
			$this->assertSame('redirected', $e->getMessage());
		}

		$this->assertSame((string) wc_get_page_permalink('myaccount'), $target);
	}

	public function test_admin_can_open(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->ask($this->booking_id);

		BookingSummaryPage::maybe_render();

		$this->assertNotContains(404, $this->statuses);
		$this->assertStringContainsString('This summary is not an invoice.', $this->document());
	}

	public function test_real_template_redirect_chain_keeps_the_404(): void
	{
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'customer' )));
		$this->ask($this->booking_id);
		// What the live request looks like: the account page's slug is the
		// "name" core guesses a 404 against.
		self::factory()->post->create(array( 'post_type' => 'page', 'post_name' => 'hesabim', 'post_status' => 'publish' ));
		$GLOBALS['wp_query']->set('name', 'hesabim');
		$_SERVER['REQUEST_URI'] = '/hesabim/?mhmrentiva_summary=1';
		$redirected             = '';
		add_filter(
			'wp_redirect',
			static function (string $location) use (&$redirected): string {
				$redirected = $location;
				// redirect_canonical() exits after wp_redirect(): end the call cleanly.
				throw new \RuntimeException('canonical redirect to ' . $location);
			}
		);
		add_action('template_redirect', array( BookingSummaryPage::class, 'maybe_render' ), 1);
		add_action('template_redirect', 'redirect_canonical');

		try {
			do_action('template_redirect');
		} catch (\RuntimeException $e) {
			$this->fail($e->getMessage());
		}

		remove_action('template_redirect', array( BookingSummaryPage::class, 'maybe_render' ), 1);
		add_action('template_redirect', 'redirect_canonical');
		$this->assertSame('', $redirected, 'No canonical 301 for a refused summary.');
		$this->assertContains(404, $this->statuses);
		$this->assertTrue(is_404());
	}

	public function test_vehicle_author_can_open(): void
	{
		$vendor = (int) self::factory()->user->create(array( 'role' => 'customer' ));
		wp_update_post(
			array(
				'ID'          => (int) get_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', true),
				'post_author' => $vendor,
			)
		);
		wp_set_current_user($vendor);
		$this->ask($this->booking_id);

		BookingSummaryPage::maybe_render();

		$this->assertNotContains(404, $this->statuses);
		$this->assertStringContainsString('This summary is not an invoice.', $this->document());
	}

	public function test_bare_id_form_is_checked_like_the_endpoint_form(): void
	{
		$GLOBALS['wp_query']->init();
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'customer' )));
		$GLOBALS['wp_query']->set(BookingSummaryPage::QUERY_VAR, (string) $this->booking_id);
		BookingSummaryPage::maybe_render();
		$this->assertTrue(is_404(), 'A foreign customer with the bare id form.');

		$GLOBALS['wp_query']->init();
		wp_set_current_user($this->customer_id);
		$GLOBALS['wp_query']->set(BookingSummaryPage::QUERY_VAR, (string) $this->booking_id);
		BookingSummaryPage::maybe_render();
		$this->assertStringContainsString('This summary is not an invoice.', $this->document());
	}

	public function test_url_forms(): void
	{
		$this->assertStringContainsString('mhmrentiva_summary=1', BookingSummaryPage::url($this->booking_id, true));
		$this->assertStringContainsString('mhmrentiva_summary=' . $this->booking_id, BookingSummaryPage::url($this->booking_id, false));
		$this->assertStringNotContainsString('mhmrentiva_summary=1&', BookingSummaryPage::url($this->booking_id, false) . '&');
	}

	public function test_due_at_pickup_is_not_printed_for_a_cancelled_booking(): void
	{
		update_post_meta($this->booking_id, '_mhmrentiva_payment_type', 'deposit');
		update_post_meta($this->booking_id, '_mhmrentiva_deposit_amount', '300.00');
		update_post_meta($this->booking_id, '_mhmrentiva_remaining_amount', '934.00');
		wp_set_current_user($this->customer_id);

		$this->ask($this->booking_id);
		BookingSummaryPage::maybe_render();
		$this->assertStringContainsString('934', $this->document(), 'An open booking names the amount due.');

		update_post_meta($this->booking_id, '_mhmrentiva_status', 'cancelled');
		$this->ask($this->booking_id);
		BookingSummaryPage::maybe_render();
		$this->assertStringNotContainsString('934', $this->document());
	}

	public function test_other_requests_are_untouched(): void
	{
		wp_set_current_user($this->customer_id);
		$GLOBALS['wp_query']->init();

		BookingSummaryPage::maybe_render();

		$this->assertSame('/theme/index.php', BookingSummaryPage::template_include('/theme/index.php'));
		$this->assertSame(array(), $this->statuses);
	}

	public function test_no_theme_header(): void
	{
		wp_set_current_user($this->customer_id);
		$this->ask($this->booking_id);
		$fired = 0;
		add_action(
			'wp_head',
			static function () use (&$fired): void {
				++$fired;
			}
		);

		BookingSummaryPage::maybe_render();
		$html = $this->document();

		$this->assertSame(0, $fired, 'wp_head must not run.');
		$this->assertStringContainsString('<!doctype html>', $html);
		$this->assertSame(1, substr_count($html, '<head>'));
		$this->assertStringContainsString('rvs-summary.css', $html, 'Its own style is printed.');
		$this->assertStringNotContainsString('wp-block-library', $html);
		$this->assertStringContainsString('window.print()', $html);
	}

	public function test_thankyou_summary_links_to_summary(): void
	{
		$product = $this->ensure_booking_product('100');
		wp_set_current_user($this->customer_id);

		$order = wc_create_order(array( 'customer_id' => $this->customer_id ));
		$item  = new \WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_quantity(1);
		$item->set_total(200.0);
		$item->add_meta_data(
			'_mhmrentiva_booking_data',
			array(
				'vehicle_id'   => (int) get_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', true),
				'pickup_date'  => '2026-11-06',
				'dropoff_date' => '2026-11-09',
				'total_price'  => 1000.0,
				'rental_days'  => 3,
			)
		);
		$order->add_item($item);
		$order->calculate_totals();
		$order->update_meta_data('_mhmrentiva_booking_id', $this->booking_id);
		$order->save();

		ob_start();
		CheckoutJourney::render_thankyou($order->get_id());
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('href="' . esc_url(BookingSummaryPage::url($this->booking_id)) . '"', $html);
		$this->assertStringContainsString('Booking summary', $html);
		$this->assertStringContainsString('mhmrentiva_summary=1', BookingSummaryPage::url($this->booking_id));

		// Another logged-in user gets no link.
		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'customer' )));
		ob_start();
		CheckoutJourney::render_thankyou($order->get_id());
		$this->assertStringNotContainsString('mhmrentiva_summary', (string) ob_get_clean());
	}
}
