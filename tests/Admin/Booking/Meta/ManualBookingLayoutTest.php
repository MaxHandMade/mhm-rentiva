<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_UnitTestCase;

/**
 * New-booking markup against the approved design (canvas Yon-Yeni-Rezervasyon
 * v116): main column with three cards, sticky sidebar with payment, messages,
 * price and actions; one fixed "selected vehicle" slot; no guest field (K5).
 *
 * @covers \MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox
 */
final class ManualBookingLayoutTest extends WP_UnitTestCase
{
	private int $addon_booking;

	private int $addon_untyped;

	public function set_up(): void
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));

		$this->addon_booking = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => 'Cleaning' ));
		update_post_meta($this->addon_booking, 'mhmrentiva_addon_price', '50');
		update_post_meta($this->addon_booking, 'mhmrentiva_addon_enabled', '1');
		update_post_meta($this->addon_booking, '_mhmrentiva_addon_pricing_type', 'per_booking');

		$this->addon_untyped = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_addon', 'post_status' => 'publish', 'post_title' => 'Navigation' ));
		update_post_meta($this->addon_untyped, 'mhmrentiva_addon_price', '5');
		update_post_meta($this->addon_untyped, 'mhmrentiva_addon_enabled', '1');
	}

	private function html(): string
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'auto-draft' ));
		ob_start();
		ManualBookingMetaBox::render($post);
		return (string) ob_get_clean();
	}

	public function test_render_splits_main_and_aside(): void
	{
		$html = $this->html();

		$main  = strpos($html, 'class="mhm-mb-main"');
		$aside = strpos($html, 'class="mhm-mb-aside"');
		$this->assertNotFalse($main);
		$this->assertNotFalse($aside);
		$this->assertLessThan($aside, $main);

		$vehicle = strpos($html, 'id="mhmrentiva_manual_vehicle_id"');
		$dates   = strpos($html, 'id="mhmrentiva_manual_pickup_date"');
		$addons  = strpos($html, 'class="mhm-addon-selection"');
		foreach (array( $vehicle, $dates, $addons ) as $at) {
			$this->assertGreaterThan($main, $at);
			$this->assertLessThan($aside, $at);
		}

		$payment  = strpos($html, 'id="mhmrentiva_manual_payment_type"');
		$messages = strpos($html, 'class="mhm-booking-messages"');
		$price    = strpos($html, 'class="mhm-price-calculation');
		$actions  = strpos($html, 'class="mhm-booking-actions"');
		$this->assertGreaterThan($aside, $payment);
		$this->assertGreaterThan($payment, $messages);
		$this->assertGreaterThan($messages, $price);
		$this->assertGreaterThan($price, $actions);
		$this->assertStringContainsString('aria-live="polite"', substr($html, $messages, 120));
	}

	public function test_vehicle_info_placeholder_is_single_and_before_new_customer(): void
	{
		$html = $this->html();

		$this->assertSame(1, substr_count($html, 'id="mhm-vehicle-info"'));
		$slot     = strpos($html, 'id="mhm-vehicle-info"');
		$customer = strpos($html, 'id="mhmrentiva_manual_customer_id"');
		$new      = strpos($html, 'id="mhmrentiva_new_customer_fields"');
		$this->assertGreaterThan($customer, $slot);
		$this->assertLessThan($new, $slot);
	}

	public function test_no_guest_field(): void
	{
		$this->assertStringNotContainsString('mhmrentiva_manual_guests', $this->html());
	}

	public function test_card_headers_have_no_dashicons(): void
	{
		$this->assertStringNotContainsString('dashicons', $this->html());
	}

	public function test_addon_checkbox_carries_pricing_type(): void
	{
		$html = $this->html();

		$this->assertMatchesRegularExpression('/value="' . $this->addon_booking . '"[^>]*data-pricing-type="per_booking"/', $html);
		$this->assertMatchesRegularExpression('/value="' . $this->addon_untyped . '"[^>]*data-pricing-type="[a-z_]+"/', $html);
	}

	public function test_payment_method_note_is_described(): void
	{
		$html = $this->html();

		$this->assertStringContainsString('aria-describedby="mhmrentiva_manual_payment_method_note"', $html);
		$this->assertStringContainsString('id="mhmrentiva_manual_payment_method_note"', $html);
		$this->assertStringContainsString(__('Online: the payment link is sent separately.', 'mhm-rentiva'), $html);
	}
}
