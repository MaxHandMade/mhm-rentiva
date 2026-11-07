<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\BookingMeta;
use MHMRentiva\Admin\Booking\Meta\BookingStatusBox;
use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_UnitTestCase;

/**
 * "Rezervasyon durumu" is the core publish box (submitdiv) re-registered with
 * a summary and the cancel button on top (canvas Yon-Rezervasyon-Duzenle v135,
 * spec PR 3b "Rezervasyon durumu = çekirdek submitdiv").
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingStatusBox
 * @covers \MHMRentiva\Admin\Booking\Meta\BookingDepositMetaBox
 */
final class BookingStatusBoxTest extends WP_UnitTestCase
{
	private int $vehicle;

	public function setUp(): void
	{
		parent::setUp();
		require_once ABSPATH . 'wp-admin/includes/meta-boxes.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->vehicle = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
			'post_title'  => 'Toyota Corolla 1.6',
		));
		BookingStatusBox::register();
	}

	public function tearDown(): void
	{
		global $wp_meta_boxes, $post;
		$wp_meta_boxes = array();
		$post          = null;
		$_POST         = array();
		parent::tearDown();
	}

	/** @param array<string, string> $meta */
	private function booking(array $meta = array(), string $status = 'publish'): \WP_Post
	{
		$id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => $status,
		));
		foreach ($meta + array(
			'_mhmrentiva_vehicle_id'     => (string) $this->vehicle,
			'_mhmrentiva_rental_days'    => '3',
			'_mhmrentiva_total_price'    => '5850',
			'_mhmrentiva_status'         => 'confirmed',
			'_mhmrentiva_payment_status' => 'paid',
			'_mhmrentiva_payment_type'   => 'deposit',
		) as $key => $value) {
			update_post_meta($id, $key, $value);
		}
		return get_post($id);
	}

	private function seed_core_submitdiv(): void
	{
		add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', 'mhmrentiva_booking', 'side', 'core');
	}

	private function render_status_box(\WP_Post $post): string
	{
		// post.php sets the global before any box renders; core's
		// post_submit_meta_box() -> touch_time() reads it, not its argument.
		$GLOBALS['post'] = $post;
		ob_start();
		BookingStatusBox::render($post, array( 'args' => array() ));
		return (string) ob_get_clean();
	}

	private function render_deposit_box(\WP_Post $post): string
	{
		ob_start();
		BookingDepositMetaBox::render_deposit_management($post);
		return (string) ob_get_clean();
	}

	public function test_submitdiv_is_reregistered_as_booking_status_in_side_high(): void
	{
		global $wp_meta_boxes;
		$post = $this->booking();
		$this->seed_core_submitdiv();

		do_action('add_meta_boxes_mhmrentiva_booking', $post);

		$box = $wp_meta_boxes['mhmrentiva_booking']['side']['high']['submitdiv'] ?? null;
		$this->assertIsArray($box);
		$this->assertSame('Booking Status', $box['title']);
		$this->assertSame(array( BookingStatusBox::class, 'render' ), $box['callback']);
		$this->assertArrayNotHasKey('submitdiv', $wp_meta_boxes['mhmrentiva_booking']['side']['core'] ?? array());
	}

	public function test_new_booking_screen_does_not_get_submitdiv_back(): void
	{
		global $wp_meta_boxes;
		$post = $this->booking(array(), 'auto-draft');
		$this->seed_core_submitdiv();
		$GLOBALS['pagenow'] = 'post-new.php';
		$GLOBALS['post']    = $post;
		ManualBookingMetaBox::remove_default_meta_boxes();

		do_action('add_meta_boxes_mhmrentiva_booking', $post);

		$this->assertFalse($wp_meta_boxes['mhmrentiva_booking']['side']['core']['submitdiv'] ?? null);
		// remove_meta_box() writes false into every priority; no box may come back.
		$this->assertFalse($wp_meta_boxes['mhmrentiva_booking']['side']['high']['submitdiv'] ?? false);
	}

	public function test_render_prints_summary_then_core_publish_box(): void
	{
		$html = $this->render_status_box($this->booking());

		$this->assertStringContainsString('Toyota Corolla 1.6', $html);
		$this->assertStringContainsString('Days', $html);
		$this->assertStringContainsString('Total', $html);
		$this->assertStringContainsString('id="submitpost"', $html);
		$this->assertStringContainsString('name="save"', $html);
		$this->assertLessThan(strpos($html, 'id="submitpost"'), strpos($html, 'Toyota Corolla 1.6'));
	}

	public function test_cancel_button_is_in_the_status_box_and_not_in_the_deposit_box(): void
	{
		$post = $this->booking();

		$this->assertSame(1, substr_count($this->render_status_box($post), 'id="cancel-booking"'));
		$this->assertSame(0, substr_count($this->render_deposit_box($post), 'id="cancel-booking"'));
	}

	public function test_no_cancel_button_without_a_payment_type(): void
	{
		// The deposit box returned before any button on a pre-deposit booking.
		$post = $this->booking(array( '_mhmrentiva_payment_type' => '' ));

		$this->assertStringNotContainsString('id="cancel-booking"', $this->render_status_box($post));
	}

	public function test_no_cancel_button_on_a_completed_booking(): void
	{
		$post = $this->booking(array( '_mhmrentiva_status' => 'completed' ));

		$this->assertStringNotContainsString('id="cancel-booking"', $this->render_status_box($post));
	}

	/**
	 * @dataProvider visibilities
	 *
	 * @param array<string, string> $postarr
	 */
	public function test_private_and_password_bookings_keep_their_visibility_on_update(array $postarr, string $status, string $password): void
	{
		$id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ) + $postarr);

		$_POST = array(
			'post_ID'       => (string) $id,
			'post_type'     => 'mhmrentiva_booking',
			'_wpnonce'      => wp_create_nonce('update-post_' . $id),
			'original_post_status' => $status,
			'post_status'   => $status,
			'visibility'    => '' !== $password ? 'password' : ( 'private' === $status ? 'private' : 'public' ),
			'post_password' => $password,
			'hidden_post_status' => $status,
			'hidden_post_visibility' => '' !== $password ? 'password' : ( 'private' === $status ? 'private' : 'public' ),
			'save'          => 'Update',
		);
		edit_post();

		$this->assertSame($status, get_post_status($id));
		$this->assertSame($password, get_post($id)->post_password);
	}

	/** @return iterable<string, array{0: array<string, string>, 1: string, 2: string}> */
	public static function visibilities(): iterable
	{
		yield 'publish' => array( array( 'post_status' => 'publish' ), 'publish', '' );
		yield 'private' => array( array( 'post_status' => 'private' ), 'private', '' );
		yield 'password' => array( array( 'post_status' => 'publish', 'post_password' => 'x' ), 'publish', 'x' );
	}

	public function test_status_is_not_overwritten_when_the_field_is_absent(): void
	{
		// Both savers run on save_post (5 and 10). Without the edit box's status
		// field neither may write a status: the edit box defaulted to 'pending'.
		$post = $this->booking();
		add_action('save_post', array( BookingEditMetaBox::class, 'save_booking_details' ), 5);
		add_action('save_post', array( BookingMeta::class, 'save_meta' ), 10, 2);
		$_POST = array(
			'mhmrentiva_booking_edit_meta_nonce' => wp_create_nonce('mhmrentiva_booking_edit_action'),
			'_wpnonce'                           => wp_create_nonce('update-post_' . $post->ID),
			'mhmrentiva_edit_vehicle_id'         => (string) $this->vehicle,
			'mhmrentiva_edit_pickup_date'        => '2099-05-01',
			'mhmrentiva_edit_dropoff_date'       => '2099-05-04',
		);

		wp_update_post(array( 'ID' => $post->ID, 'post_title' => 'Updated' ));

		$this->assertSame('confirmed', get_post_meta($post->ID, '_mhmrentiva_status', true));
	}
	/** Codex plan audit r1: core's slug box is not on the canvas. */
	public function test_slug_box_is_removed_and_the_slug_survives_an_update(): void
	{
		global $wp_meta_boxes;
		$post = $this->booking();
		wp_update_post(array( 'ID' => $post->ID, 'post_name' => 'kept-slug' ));
		add_meta_box('slugdiv', __('Slug'), 'post_slug_meta_box', 'mhmrentiva_booking', 'normal', 'core');

		do_action('add_meta_boxes_mhmrentiva_booking', get_post($post->ID));

		$this->assertFalse($wp_meta_boxes['mhmrentiva_booking']['normal']['core']['slugdiv'] ?? null);

		// The form now posts no post_name; the stored slug stands.
		$_POST = array(
			'post_ID'     => (string) $post->ID,
			'post_type'   => 'mhmrentiva_booking',
			'post_status' => 'publish',
			'post_title'  => 'Updated',
			'_wpnonce'    => wp_create_nonce('update-post_' . $post->ID),
		);
		edit_post();

		$this->assertSame('kept-slug', get_post($post->ID)->post_name);
	}
	public function test_cancel_button_says_what_it_does(): void
	{
		$html = $this->render_status_box($this->booking());
		$this->assertStringContainsString('Cancelling starts the refund of the amount paid and sends the customer a notification.', $html);
		$this->assertLessThan(strpos($html, 'mhm-cancel-hint'), strpos($html, 'id="cancel-booking"'));
	}

	public function test_no_cancel_hint_without_the_button(): void
	{
		$html = $this->render_status_box($this->booking(array( '_mhmrentiva_payment_type' => '' )));
		$this->assertStringNotContainsString('mhm-cancel-hint', $html);
	}
}
