<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Admin\Settings\Groups\VehicleManagementSettings;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * PR 0: tax is WooCommerce's job. Rentiva's own cart tax hooks (one of them
 * on a filter WooCommerce never fires) and its own tax settings are gone.
 *
 * @coversNothing
 */
final class NoRentivaTaxHooksTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	public function test_bridge_registers_no_tax_hooks(): void
	{
		WooCommerceBridge::register();

		$this->assertFalse(method_exists(WooCommerceBridge::class, 'adjust_tax_calculation'));
		$this->assertFalse(method_exists(WooCommerceBridge::class, 'adjust_cart_item_taxes'));
		$this->assertFalse(has_filter('woocommerce_cart_item_get_taxes'));

		global $wp_filter;
		$fees = isset($wp_filter['woocommerce_cart_calculate_fees']) ? $wp_filter['woocommerce_cart_calculate_fees']->callbacks : array();
		foreach ($fees as $callbacks) {
			foreach ($callbacks as $callback) {
				$fn = $callback['function'];
				$this->assertFalse(
					is_array($fn) && WooCommerceBridge::class === ( is_object($fn[0]) ? get_class($fn[0]) : $fn[0] ),
					'WooCommerceBridge must not hook the cart fee pass.'
				);
			}
		}
	}

	public function test_cart_item_has_no_rentiva_total_key(): void
	{
		$this->require_woocommerce();
		$product = $this->ensure_booking_product('1');
		wc_load_cart();
		WC()->cart->empty_cart();

		$key = WC()->cart->add_to_cart($product->get_id(), 1, 0, array(), array(
			'mhmrentiva_booking_data'    => array( 'total_price' => 3000, 'payment_type' => 'deposit' ),
			'mhmrentiva_booking_price'   => 300.0,
			'mhmrentiva_booking_pending' => true,
		));
		WooCommerceBridge::calculate_totals(WC()->cart);

		$this->assertArrayNotHasKey('mhmrentiva_booking_total_price', WC()->cart->get_cart()[ $key ]);
		WC()->cart->empty_cart();
	}

	public function test_vehicle_tab_renders_no_tax_fields(): void
	{
		$this->assertFalse(method_exists(VehicleManagementSettings::class, 'render_tax_inclusive_field'));
		$this->assertFalse(method_exists(VehicleManagementSettings::class, 'render_tax_rate_field'));
		$this->assertArrayNotHasKey('mhmrentiva_vehicle_tax_rate', VehicleManagementSettings::get_default_settings());
		$this->assertArrayNotHasKey('mhmrentiva_vehicle_tax_inclusive', VehicleManagementSettings::get_default_settings());
	}

	public function test_addon_tax_readers_removed(): void
	{
		$this->assertFalse(method_exists(AddonManager::class, 'is_tax_inclusive'));
		$this->assertFalse(method_exists(AddonManager::class, 'get_tax_rate'));
	}
}
