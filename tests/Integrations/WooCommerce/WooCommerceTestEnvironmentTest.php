<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integrations\WooCommerce;

use WP_UnitTestCase;

/**
 * WooCommerce must be present, installed and in the same mode as the dev site.
 *
 * Until this round the suite ran without WooCommerce at all: `WP_TESTS_DIR`
 * points at an installation separate from the dev site and `tests/bootstrap.php`
 * loaded only mhm-rentiva, so `class_exists('WooCommerce')` was false in every
 * test. Five test files said `markTestSkipped('WooCommerce not loaded')` and the
 * refund and checkout paths -- 26 source files touch WooCommerce -- could not be
 * measured behaviourally at all. The booking-to-order sweep had to be pinned at
 * source level for exactly this reason.
 *
 * This file is the environment's own gate. It asserts what the other WooCommerce
 * tests are entitled to assume, so that when they fail it is the plugin failing
 * and not the harness.
 */
final class WooCommerceTestEnvironmentTest extends WP_UnitTestCase
{
	public function test_woocommerce_is_loaded(): void
	{
		$this->assertTrue(
			class_exists( 'WooCommerce' ),
			'WooCommerce is not loaded in the test environment; every WC-dependent test is measuring an absence.'
		);
	}

	public function test_woocommerce_tables_are_installed_under_the_test_prefix(): void
	{
		global $wpdb;

		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not loaded.' );

		// Loading the plugin is not installing it: WC_Install::install() is what
		// creates the tables, and a suite that skips it fails on the first order
		// with a SQL error rather than a readable assertion.
		$sessions = $wpdb->prefix . 'woocommerce_sessions';

		$this->assertSame(
			$sessions,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions ) ),
			'WooCommerce tables are missing from the test database.'
		);
	}

	/**
	 * The dev site runs HPOS (`woocommerce_custom_orders_table_enabled = yes`,
	 * measured 2026-08-18). The order store decides where WooCommerceBridge's
	 * writes land, so a suite in the other mode would prove nothing about
	 * production behaviour while looking perfectly green.
	 */
	public function test_order_storage_mode_matches_the_dev_site(): void
	{
		$this->assertTrue( class_exists( 'WooCommerce' ), 'WooCommerce is not loaded.' );

		$this->assertTrue(
			class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
				&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
			'The test environment is not using HPOS, but the dev site is.'
		);
	}

	/**
	 * A behavioural round trip, not a class_exists() check: an order that can be
	 * created, saved and read back is the capability every finance test needs.
	 */
	public function test_an_order_round_trips_through_the_active_store(): void
	{
		$this->assertTrue( function_exists( 'wc_create_order' ), 'WooCommerce order API is unavailable.' );

		$order = wc_create_order();
		$order->set_total( '123.45' );
		$order->update_meta_data( '_mhmrentiva_probe', 'round-trip' );
		$order->save();

		$reloaded = wc_get_order( $order->get_id() );

		$this->assertInstanceOf( \WC_Order::class, $reloaded );
		$this->assertSame( '123.45', $reloaded->get_total() );
		$this->assertSame( 'round-trip', $reloaded->get_meta( '_mhmrentiva_probe' ) );
	}
}
