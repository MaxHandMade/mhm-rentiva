<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge;
use WP_UnitTestCase;

/**
 * Pure bridge: widget settings -> canonical shortcode attributes, no Elementor.
 *
 * @covers \MHMRentiva\Admin\Frontend\Widgets\Base\WidgetAttributeBridge
 */
final class WidgetAttributeBridgeTest extends WP_UnitTestCase {

	private const TAG = 'rentiva_vehicles_list';

	public function test_camelcase_and_alias_resolve(): void {
		$out = WidgetAttributeBridge::to_canonical(
			self::TAG,
			array(
				'showPrice'        => 'yes',
				'show_booking_btn' => '',
			),
			array()
		);
		$this->assertSame( '1', $out['show_price'] );
		$this->assertSame( '0', $out['show_booking_button'] );
	}

	public function test_elementor_internal_settings_never_reach_atts(): void {
		$out = WidgetAttributeBridge::to_canonical(
			self::TAG,
			array(
				'_title'                  => 'x',
				'_transform_scale_effect' => '1',
				'show_price'              => 'yes',
			),
			array()
		);
		foreach ( array_keys( $out ) as $key ) {
			if ( '_canonical' === $key ) {
				continue;
			}
			$this->assertStringStartsNotWith( '_', (string) $key );
		}
	}

	public function test_null_is_dropped_for_bool(): void {
		$out = WidgetAttributeBridge::filter_empty( self::TAG, array( 'show_price' => null ) );
		$this->assertArrayNotHasKey( 'show_price', $out );
	}

	public function test_empty_dropped_for_int_kept_for_string_and_bool(): void {
		$out = WidgetAttributeBridge::filter_empty(
			self::TAG,
			array(
				'limit'      => '',
				'class'      => '',
				'show_price' => '',
			)
		);
		$this->assertArrayNotHasKey( 'limit', $out );
		$this->assertSame( '', $out['class'] );
		$this->assertSame( '', $out['show_price'] );
	}

	public function test_exact_canonical_key_beats_alias_twin(): void {
		$out = WidgetAttributeBridge::to_canonical(
			self::TAG,
			array(
				'showPrice'  => 'yes',
				'show_price' => '',
			),
			array()
		);
		$this->assertSame( '0', $out['show_price'] );

		// Order must not matter.
		$out = WidgetAttributeBridge::to_canonical(
			self::TAG,
			array(
				'show_price' => '',
				'showPrice'  => 'yes',
			),
			array()
		);
		$this->assertSame( '0', $out['show_price'] );
	}

	public function test_explicit_mapping_beats_raw_alias(): void {
		$out = WidgetAttributeBridge::to_canonical( self::TAG, array( 'showPrice' => 'yes' ), array( 'show_price' => '0' ) );
		$this->assertSame( '0', $out['show_price'] );
	}

	public function test_url_control_flattened(): void {
		$out = WidgetAttributeBridge::flatten_controls(
			array(
				'viewAllUrl' => array(
					'url'         => 'https://x.test/a',
					'is_external' => '',
				),
				'other'      => array( 'a' => 1 ),
				'plain'      => 'v',
			)
		);
		$this->assertSame( 'https://x.test/a', $out['viewAllUrl'] );
		$this->assertSame( array( 'a' => 1 ), $out['other'] );
		$this->assertSame( 'v', $out['plain'] );
	}

	public function test_canonical_flag_set(): void {
		$out = WidgetAttributeBridge::to_canonical( self::TAG, array( 'show_price' => 'yes' ), array() );
		$this->assertTrue( $out['_canonical'] );
	}
}
