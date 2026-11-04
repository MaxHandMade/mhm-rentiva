<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Core;

use MHMRentiva\Admin\Booking\Core\PaymentView;
use WP_UnitTestCase;

/**
 * The booking's payment view (spec PR 3a, plan r4 R2/R5/R6/R7): one derived
 * status from status + payment type + remaining amount, and token scopes that
 * write one history row when the view changed between the first open token
 * and the last close.
 *
 * @covers \MHMRentiva\Admin\Booking\Core\PaymentView
 */
final class PaymentViewTest extends WP_UnitTestCase
{
	private int $booking;

	public function set_up(): void
	{
		parent::set_up();
		PaymentView::reset();
		$this->booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));
	}

	public function tear_down(): void
	{
		PaymentView::reset();
		parent::tear_down();
	}

	private function view(string $status, string $type = 'full', string $remaining = '0'): void
	{
		update_post_meta($this->booking, '_mhmrentiva_payment_status', $status);
		update_post_meta($this->booking, '_mhmrentiva_payment_type', $type);
		update_post_meta($this->booking, '_mhmrentiva_remaining_amount', $remaining);
	}

	/** @return list<array<string, mixed>> */
	private function payment_rows(): array
	{
		$history = get_post_meta($this->booking, '_mhmrentiva_booking_history', true);
		return array_values(array_filter(
			is_array($history) ? $history : array(),
			static fn (array $e): bool => 'payment_update' === ( $e['type'] ?? '' )
		));
	}

	/**
	 * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function statuses(): iterable
	{
		yield 'deposit paid with a balance'   => array( 'paid', 'deposit', '50', 'partially_paid' );
		yield 'deposit paid in full'          => array( 'paid', 'deposit', '0', 'paid' );
		yield 'full payment, stray remaining' => array( 'paid', 'full', '50', 'paid' );
		yield 'pending'                       => array( 'pending', 'deposit', '50', 'pending' );
		yield 'legacy completed'              => array( 'completed', 'full', '0', 'paid' );
		yield 'legacy processing'             => array( 'processing', 'full', '0', 'pending' );
		yield 'legacy on-hold'                => array( 'on-hold', 'full', '0', 'pending' );
		yield 'nothing stored'                => array( '', 'full', '0', 'unpaid' );
	}

	/**
	 * @dataProvider statuses
	 */
	public function test_status_is_derived(string $status, string $type, string $remaining, string $expected): void
	{
		$this->view($status, $type, $remaining);
		$this->assertSame($expected, PaymentView::status($this->booking));
	}

	public function test_the_new_key_wins_over_the_legacy_one(): void
	{
		$this->view('paid');
		update_post_meta($this->booking, '_mhmrentiva_booking_payment_status', 'unpaid');
		$this->assertSame('paid', PaymentView::status($this->booking));

		delete_post_meta($this->booking, '_mhmrentiva_payment_status');
		$this->assertSame('unpaid', PaymentView::status($this->booking));
	}

	public function test_labels(): void
	{
		$this->assertSame('Partially paid', PaymentView::label('partially_paid'));
		$this->assertSame('Partially refunded', PaymentView::label('partially_refunded'));
		$this->assertSame('Other', PaymentView::label('mystery'));
	}

	public function test_a_change_inside_a_scope_writes_one_row(): void
	{
		$this->view('pending', 'deposit', '50');
		$t = PaymentView::begin($this->booking);
		$this->view('paid', 'deposit', '50');
		PaymentView::end($t);

		$rows = $this->payment_rows();
		$this->assertCount(1, $rows);
		$this->assertSame('pending', $rows[0]['payment_from']);
		$this->assertSame('partially_paid', $rows[0]['payment_to']);
		$this->assertSame('Payment: Pending → Partially paid', $rows[0]['note']);
	}

	public function test_no_change_no_row(): void
	{
		$this->view('paid');
		PaymentView::end(PaymentView::begin($this->booking));
		$this->assertSame(array(), $this->payment_rows());
	}

	public function test_a_change_and_its_undo_write_nothing(): void
	{
		$this->view('pending');
		$t = PaymentView::begin($this->booking);
		$this->view('paid');
		$this->view('pending');
		PaymentView::end($t);
		$this->assertSame(array(), $this->payment_rows());
	}

	public function test_nested_scopes_write_one_row_and_a_double_inner_end_keeps_the_outer_open(): void
	{
		$this->view('pending', 'deposit', '50');
		$outer = PaymentView::begin($this->booking);
		$inner = PaymentView::begin($this->booking);
		$this->view('paid', 'deposit', '50');
		PaymentView::end($inner);
		PaymentView::end($inner); // JSON-early end, then finally.
		$this->assertSame(array(), $this->payment_rows(), 'The outer scope is still open.');

		$this->view('paid', 'deposit', '0');
		PaymentView::end($outer);
		PaymentView::end($outer);

		$rows = $this->payment_rows();
		$this->assertCount(1, $rows);
		$this->assertSame('pending', $rows[0]['payment_from']);
		$this->assertSame('paid', $rows[0]['payment_to']);
	}

	public function test_two_scopes_in_a_row_write_two_rows_and_an_old_token_closes_nothing(): void
	{
		$this->view('pending');
		$first = PaymentView::begin($this->booking);
		$this->view('paid');
		PaymentView::end($first);

		$second = PaymentView::begin($this->booking);
		$this->view('refunded');
		PaymentView::end($first); // stale
		$this->assertCount(1, $this->payment_rows());
		PaymentView::end($second);

		$rows = $this->payment_rows();
		$this->assertCount(2, $rows);
		$this->assertSame(array( 'paid', 'refunded' ), array( $rows[1]['payment_from'], $rows[1]['payment_to'] ));
	}

	public function test_an_unknown_token_is_ignored(): void
	{
		PaymentView::end('nope');
		$this->assertSame(array(), $this->payment_rows());
	}

	public function test_shutdown_closes_what_is_still_open(): void
	{
		$this->view('pending');
		PaymentView::begin($this->booking);
		PaymentView::begin($this->booking);
		$this->assertSame(0, has_action('shutdown', array( PaymentView::class, 'flush' )));

		// What `shutdown` runs (the hook is asserted above). Not do_action():
		// core's own shutdown callbacks close the test's output buffers.
		$this->view('paid');
		PaymentView::flush();
		$this->assertCount(1, $this->payment_rows());

		PaymentView::flush();
		$this->assertCount(1, $this->payment_rows(), 'Nothing is left open after a flush.');
	}

	public function test_two_unknown_statuses_still_count_as_a_change(): void
	{
		$this->view('mystery-a');
		$t = PaymentView::begin($this->booking);
		$this->view('mystery-b');
		PaymentView::end($t);
		$this->assertCount(1, $this->payment_rows());
	}

	public function test_booking_meta_registers_the_update_scope_around_its_writers(): void
	{
		// register() runs once per process (static guard); reset it so this
		// test sees what the plugin wires at bootstrap.
		$flag = new \ReflectionProperty(\MHMRentiva\Admin\Booking\Meta\BookingMeta::class, 'registered');
		$flag->setAccessible(true);
		$flag->setValue(null, false);
		\MHMRentiva\Admin\Booking\Meta\BookingMeta::register();

		$this->assertSame(1, has_action('save_post', array( PaymentView::class, 'open_update_scope' )));
		$this->assertSame(99, has_action('save_post', array( PaymentView::class, 'close_update_scope' )));
		$this->assertSame(10, has_action('save_post', array( \MHMRentiva\Admin\Booking\Meta\BookingMeta::class, 'save_meta' )));
	}

	public function test_an_update_scope_surrounds_the_generic_save_post_writers(): void
	{
		// BookingEditMetaBox (5) and BookingMeta::save_meta (10) write on the
		// GENERIC save_post, which core fires after save_post_{type}; a
		// re-pricing that clears the balance must leave one row.
		add_action('save_post', array( PaymentView::class, 'open_update_scope' ), 1, 2);
		add_action('save_post', array( PaymentView::class, 'close_update_scope' ), 99, 2);
		$this->view('paid', 'deposit', '50');
		$reprice = function (int $post_id): void {
			if ($post_id === $this->booking) {
				update_post_meta($this->booking, '_mhmrentiva_remaining_amount', '0');
			}
		};
		add_action('save_post', $reprice, 5);

		wp_update_post(array( 'ID' => $this->booking, 'post_title' => 'Updated' ));

		$rows = $this->payment_rows();
		$this->assertCount(1, $rows);
		$this->assertSame(array( 'partially_paid', 'paid' ), array( $rows[0]['payment_from'], $rows[0]['payment_to'] ));
	}

	public function test_a_nested_update_inside_the_save_writes_one_row(): void
	{
		// BookingEditMetaBox::save_booking_details() calls wp_update_post()
		// from inside save_post (a vehicle change): save_post runs again.
		add_action('save_post', array( PaymentView::class, 'open_update_scope' ), 1, 2);
		add_action('save_post', array( PaymentView::class, 'close_update_scope' ), 99, 2);
		$this->view('paid', 'deposit', '50');
		$nested = 0;
		$inner  = function (int $post_id) use (&$nested): void {
			if ($post_id !== $this->booking || $nested++ > 0) {
				return;
			}
			wp_update_post(array( 'ID' => $this->booking, 'post_excerpt' => 'vehicle changed' ));
			update_post_meta($this->booking, '_mhmrentiva_remaining_amount', '0');
		};
		add_action('save_post', $inner, 5);

		wp_update_post(array( 'ID' => $this->booking, 'post_title' => 'Updated' ));

		$this->assertCount(1, $this->payment_rows());
	}

	public function test_an_update_of_another_post_type_opens_no_scope(): void
	{
		add_action('save_post', array( PaymentView::class, 'open_update_scope' ), 1, 2);
		add_action('save_post', array( PaymentView::class, 'close_update_scope' ), 99, 2);
		$page = (int) self::factory()->post->create(array( 'post_type' => 'page' ));
		wp_update_post(array( 'ID' => $page, 'post_title' => 'x' ));
		$this->assertFalse(has_action('shutdown', array( PaymentView::class, 'flush' )), 'No scope was opened.');
	}

	public function test_an_autosave_revision_writes_no_row(): void
	{
		add_action('save_post', array( PaymentView::class, 'open_update_scope' ), 1, 2);
		add_action('save_post', array( PaymentView::class, 'close_update_scope' ), 99, 2);
		$this->view('paid', 'deposit', '50');
		$revision = (int) _wp_put_post_revision(get_post($this->booking), true);
		$this->assertGreaterThan(0, $revision);
		$this->assertSame(array(), $this->payment_rows());
	}

	public function test_the_list_column_and_the_deposit_box_show_the_derived_view(): void
	{
		$this->view('paid', 'deposit', '50');

		ob_start();
		\MHMRentiva\Admin\Booking\ListTable\BookingColumns::render('mhmrentiva_booking_payment', $this->booking);
		$column = (string) ob_get_clean();
		$this->assertStringContainsString('payment-status-partially_paid', $column);
		$this->assertStringContainsString('>Partially paid<', $column);

		ob_start();
		\MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox::render_deposit_management(get_post($this->booking));
		$box = (string) ob_get_clean();
		$this->assertMatchesRegularExpression('/payment-status-indicator partially_paid">\s*Partially paid/', $box);
	}

	public function test_a_payment_row_never_reaches_the_customer_timeline(): void
	{
		$this->view('pending');
		$t = PaymentView::begin($this->booking);
		$this->view('paid');
		PaymentView::end($t);

		$row = $this->payment_rows()[0];
		$this->assertArrayNotHasKey('status_to', $row);
		$this->assertArrayNotHasKey('status_from', $row);
	}
}
