<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Core;

use MHMRentiva\Admin\Frontend\Shortcodes\ContactForm;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\PostTypes\Logs\AdvancedLogger;
use MHMRentiva\Admin\Setup\SetupWizard;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * One home for the wp_insert_post() failure contract, across every call site
 * in the plugin.
 *
 * WordPress documents the return as "the post ID on success. The value 0 or
 * WP_Error on failure", with $wp_error defaulting to false -- so a call that
 * does not pass true gets 0 on failure, and an is_wp_error()-only guard
 * catches nothing. The third independent review sampled two call sites
 * (WooCommerceBridge, ContactForm). Sweeping the class across the tree found
 * eight calls in total and two more broken ones the sample missed:
 *
 *   - ManualBookingMetaBox (covered by ManualBookingAtomicityTest, which owns
 *     that path's locking too): told the operator "Booking created
 *     successfully." and handed them an edit link for post 0.
 *   - AdvancedLogger: DID pass $wp_error = true, then cast the result with
 *     (int). Casting an object to int in PHP yields 1 with a warning, so a
 *     failed log insert sailed past both `is_wp_error()` (it is an int now)
 *     and `$post_id > 0`, and wrote log meta onto post ID 1 -- typically the
 *     site's first post. Passing $wp_error is not the fix by itself; what you
 *     do with the return is.
 *
 * The failure is forced with wp_insert_post_empty_content, WordPress's own
 * filter. It is applied on every insert regardless of the post's contents, so
 * these tests exercise the real failure return rather than a mock.
 */
final class InsertPostFailureContractTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		remove_filter('wp_insert_post_empty_content', '__return_true');
		parent::tearDown();
	}

	private function force_insert_failure(): void
	{
		add_filter('wp_insert_post_empty_content', '__return_true');
	}

	/**
	 * RED before the fix: (int) turns the WP_Error into 1, so the logger
	 * writes its meta onto whatever post holds ID 1.
	 */
	public function test_failed_log_insert_does_not_write_meta_onto_post_id_one(): void
	{
		$decoy = self::factory()->post->create(array(
			'post_type'   => 'post',
			'post_title'  => 'Site owner content that must not be touched',
			'post_status' => 'publish',
		));

		// The defect targets the literal ID 1, so assert on it directly as
		// well as on a post we control.
		$this->force_insert_failure();
		$result = AdvancedLogger::log(array(
			'level'    => 'error',
			'category' => 'system',
			'message'  => 'forced insert failure',
		));

		$this->assertSame(0, $result, 'A failed log insert must report 0, not a cast of the error object.');
		$this->assertSame(
			'',
			(string) get_post_meta(1, '_mhmrentiva_log_level', true),
			'A failed log insert must not write log meta onto post ID 1.'
		);
		$this->assertSame(
			'',
			(string) get_post_meta($decoy, '_mhmrentiva_log_level', true),
			'A failed log insert must not write log meta onto any existing post.'
		);
	}

	/**
	 * Negative control: without the forced failure the logger must still work,
	 * so the assertion above cannot be satisfied by a logger that never logs.
	 */
	public function test_logger_still_writes_its_meta_on_the_happy_path(): void
	{
		$log_id = AdvancedLogger::log(array(
			'level'    => 'error',
			'category' => 'system',
			'message'  => 'happy path log entry',
		));

		$this->assertGreaterThan(0, $log_id, 'The logger must still create log entries.');
		$this->assertSame('error', (string) get_post_meta($log_id, '_mhmrentiva_log_level', true));
	}

	/**
	 * Finding H2. RED before the fix: the callback returns 0, is_int(0) is
	 * true, and create_booking_from_data() hands 0 back to a caller that
	 * treats it as falsy -- so the WooCommerce order silently keeps no
	 * booking, with nothing logged.
	 */
	public function test_woocommerce_booking_creation_returns_null_when_insert_fails(): void
	{
		$vehicle_id = (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', 100);

		$method = new ReflectionMethod(WooCommerceBridge::class, 'create_booking_from_data');
		$method->setAccessible(true);

		$this->force_insert_failure();
		$result = $method->invoke(null, array(
			'vehicle_id'        => $vehicle_id,
			'pickup_date'       => gmdate('Y-m-d', strtotime('+1 day')),
			'pickup_time'       => '10:00',
			'dropoff_date'      => gmdate('Y-m-d', strtotime('+3 days')),
			'dropoff_time'      => '10:00',
			'guests'            => 2,
			'customer_user_id'  => 0,
			'customer_name'       => 'Jane Customer',
			'customer_first_name' => 'Jane',
			'customer_last_name'  => 'Customer',
			'customer_email'    => 'jane@example.com',
			'customer_phone'    => '5551234567',
			'payment_type'      => 'deposit',
			'payment_method'    => 'woocommerce',
			'payment_gateway'   => '',
			'deposit_amount'    => 50,
			'remaining_amount'  => 150,
			'deposit_type'      => 'percentage',
			'payment_display'   => 'Deposit',
			'total_price'       => 200,
			'rental_days'       => 2,
			'selected_addons'   => array(),
		), 4242);

		$this->assertNull(
			$result,
			'A failed insert must be reported as failure, not as booking id 0 -- the caller only tests truthiness and would leave the order with no booking and no log.'
		);
	}

	/**
	 * Finding M3. RED before the fix: save_contact_message() returns 0, the
	 * caller emails the site owner and tells the visitor the message was sent,
	 * while nothing was stored.
	 */
	public function test_contact_message_save_throws_when_insert_fails(): void
	{
		$method = new ReflectionMethod(ContactForm::class, 'save_contact_message');
		$method->setAccessible(true);

		$this->force_insert_failure();

		$this->expectException(\Exception::class);

		$method->invoke(null, array(
			'type'           => 'general',
			'name'           => 'Jane Visitor',
			'email'          => 'jane@example.com',
			'phone'          => '5551234567',
			'company'        => '',
			'vehicle_id'     => 0,
			'preferred_date' => '',
			'priority'       => 'normal',
			'rating'         => 0,
			'attachment'     => '',
			'ip_address'     => '127.0.0.1',
			'user_agent'     => 'phpunit',
			'timestamp'      => current_time('mysql'),
			'message'        => 'Hello',
		));
	}

	/**
	 * Negative control for the test above.
	 */
	public function test_contact_message_save_returns_an_id_on_the_happy_path(): void
	{
		$method = new ReflectionMethod(ContactForm::class, 'save_contact_message');
		$method->setAccessible(true);

		$message_id = $method->invoke(null, array(
			'type'           => 'general',
			'name'           => 'Jane Visitor',
			'email'          => 'jane@example.com',
			'phone'          => '5551234567',
			'company'        => '',
			'vehicle_id'     => 0,
			'preferred_date' => '',
			'priority'       => 'normal',
			'rating'         => 0,
			'attachment'     => '',
			'ip_address'     => '127.0.0.1',
			'user_agent'     => 'phpunit',
			'timestamp'      => current_time('mysql'),
			'message'        => 'Hello',
		));

		$this->assertGreaterThan(0, $message_id, 'The happy path must still store the message.');
	}

	/**
	 * The setup wizard's page creation is guarded by its caller's truthiness
	 * check today, so 0 is not currently user-visible -- but the method's own
	 * contract says ?int, and returning 0 for "failed" is a trap for the next
	 * caller. Locked here so the defence cannot regress silently.
	 */
	public function test_setup_wizard_page_creation_returns_null_when_insert_fails(): void
	{
		$method = new ReflectionMethod(SetupWizard::class, 'create_page');
		$method->setAccessible(true);

		$this->force_insert_failure();

		$result = $method->invoke(null, array(
			'label'     => 'Contract Test Page',
			'shortcode' => 'mhmrentiva_vehicles',
		));

		$this->assertNull($result, 'A failed page insert must be reported as null, never as page id 0.');
	}
}
