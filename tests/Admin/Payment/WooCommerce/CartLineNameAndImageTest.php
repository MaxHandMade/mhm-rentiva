<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Payment\WooCommerce;

use Automattic\WooCommerce\StoreApi\SchemaController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge;
use MHMRentiva\Tests\Support\WooCommerceFixtures;
use WP_UnitTestCase;

/**
 * Every booking rides on one hidden WooCommerce product, so every cart line,
 * order line and email used to read that product's stored title ("Vehicle
 * Rental Booking", frozen in whatever language the site had when the product
 * was created). The block cart also showed WooCommerce's placeholder image:
 * the Store API image filter returned arrays where WooCommerce reads objects.
 *
 * Measurement: docs/superpowers/audits/2026-10-20-sinif-supurmesi/video-sepet-adi-gorsel.md
 *
 * These tests go through WooCommerce's own consumers (the cart, the Store API
 * cart item schema, WC_Checkout::create_order_line_items) rather than calling
 * the bridge methods in isolation, because both defects were invisible from
 * the bridge side: each method "worked", WooCommerce just never used it.
 *
 * @covers \MHMRentiva\Admin\Payment\WooCommerce\WooCommerceBridge
 */
final class CartLineNameAndImageTest extends WP_UnitTestCase
{
	use WooCommerceFixtures;

	private \WC_Product $product;

	private string $stored_name;

	private int $vehicle_id;

	private int $image_id;

	public function setUp(): void
	{
		parent::setUp();

		$this->require_woocommerce();

		$this->vehicle_id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_title'  => 'Mercedes-Benz Vito',
				'post_status' => 'publish',
			)
		);
		$this->image_id   = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $this->vehicle_id );
		set_post_thumbnail( $this->vehicle_id, $this->image_id );

		$this->product = $this->ensure_booking_product( '100' );
		if ( $this->product->get_name() === '' ) {
			$this->product->set_name( 'Vehicle Rental Booking' );
			$this->product->save();
		}
		$this->stored_name = $this->product->get_name();

		wc_load_cart();
		WC()->cart->empty_cart();
	}

	public function tearDown(): void
	{
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		$this->remove_added_uploads();
		parent::tearDown();
	}

	/**
	 * @param array<string, mixed> $booking_data
	 */
	private function add_booking( array $booking_data ): string
	{
		$key = WC()->cart->add_to_cart(
			$this->product->get_id(),
			1,
			0,
			array(),
			array(
				'mhmrentiva_booking_data'    => $booking_data,
				'mhmrentiva_booking_price'   => 50.0,
				'mhmrentiva_booking_pending' => true,
			)
		);
		$this->assertIsString( $key, 'The booking could not be added to the cart.' );

		return $key;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function rental_data( array $extra = array() ): array
	{
		return array_merge(
			array(
				'vehicle_id'   => $this->vehicle_id,
				'pickup_date'  => '2026-07-05',
				'pickup_time'  => '10:00',
				'dropoff_date' => '2026-07-08',
				'dropoff_time' => '11:30',
				'payment_type' => 'full',
				'total_price'  => 300,
			),
			$extra
		);
	}

	private function line_product( string $key ): \WC_Product
	{
		$item = WC()->cart->get_cart()[ $key ];

		return $item['data'];
	}

	public function test_a_rental_line_is_named_after_its_vehicle(): void
	{
		$key = $this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$product = $this->line_product( $key );
		$this->assertSame( 'Mercedes-Benz Vito rental', $product->get_name() );
		// The Store API (block cart, mini cart) reads get_title(), not get_name().
		$this->assertSame( 'Mercedes-Benz Vito rental', $product->get_title() );
	}

	public function test_the_stored_product_keeps_its_title(): void
	{
		$this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$fresh = wc_get_product( $this->product->get_id() );
		$this->assertInstanceOf( \WC_Product::class, $fresh );
		$this->assertSame( $this->stored_name, $fresh->get_name() );
	}

	public function test_two_bookings_keep_their_own_names(): void
	{
		$other = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_title'  => 'Fiat Egea',
				'post_status' => 'publish',
			)
		);

		// The second line is put in place the way a restored session does, so
		// both lines share the restore path the name hook runs on.
		$first        = $this->add_booking( $this->rental_data() );
		$contents     = WC()->cart->get_cart();
		$second       = $contents[ $first ];
		$second['key'] = 'second-line';
		$second['data'] = wc_get_product( $this->product->get_id() );
		$second['mhmrentiva_booking_data'] = $this->rental_data( array( 'vehicle_id' => $other ) );
		$contents['second-line'] = $second;
		WC()->cart->set_cart_contents( $contents );

		WC()->cart->calculate_totals();

		$this->assertSame( 'Mercedes-Benz Vito rental', $this->line_product( $first )->get_name() );
		$this->assertSame( 'Fiat Egea rental', $this->line_product( 'second-line' )->get_name() );
	}

	public function test_a_line_restored_from_the_session_is_named(): void
	{
		$this->add_booking( $this->rental_data() );
		$stored = WC()->cart->get_cart_for_session();

		WC()->cart->set_cart_contents( array() );
		WC()->session->set( 'cart', $stored );
		( new \WC_Cart_Session( WC()->cart ) )->get_cart_from_session();

		$items = array_values( WC()->cart->get_cart() );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Mercedes-Benz Vito rental', $items[0]['data']->get_name() );
	}

	public function test_the_name_filter_decides_and_receives_the_booking(): void
	{
		$seen = array();
		add_filter(
			'mhmrentiva_cart_item_name',
			static function ( $name, $booking_data, $cart_item ) use ( &$seen ) {
				$seen = array( $name, $booking_data, $cart_item );
				return 'transfer' === ( $booking_data['booking_type'] ?? '' ) ? 'VIP Transfer: A to B' : $name;
			},
			10,
			3
		);

		$key = $this->add_booking( $this->rental_data( array( 'booking_type' => 'transfer' ) ) );
		WC()->cart->calculate_totals();

		$this->assertSame( 'VIP Transfer: A to B', $this->line_product( $key )->get_name() );
		$this->assertIsString( $seen[0] );
		$this->assertSame( 'transfer', $seen[1]['booking_type'] );
		$this->assertArrayHasKey( 'mhmrentiva_booking_data', $seen[2] );
	}

	public function test_a_transfer_is_not_called_a_rental_by_default(): void
	{
		// Lite cannot name a transfer (the locations live in Pro); until the Pro
		// add-on answers the filter, the line keeps the generic booking name.
		$key = $this->add_booking( $this->rental_data( array( 'booking_type' => 'transfer' ) ) );
		WC()->cart->calculate_totals();

		$name = $this->line_product( $key )->get_name();
		$this->assertStringNotContainsString( 'rental', $name );
		$this->assertSame( 'Vehicle Rental Booking', $name );
	}

	public function test_a_booking_without_a_vehicle_gets_the_generic_name(): void
	{
		$key = $this->add_booking( $this->rental_data( array( 'vehicle_id' => 0 ) ) );
		WC()->cart->calculate_totals();

		$this->assertSame( 'Vehicle Rental Booking', $this->line_product( $key )->get_name() );
	}

	public function test_an_ordinary_product_is_left_alone(): void
	{
		$plain = new \WC_Product_Simple();
		$plain->set_name( 'Gift card' );
		$plain->set_regular_price( '10' );
		$plain->save();

		$key = WC()->cart->add_to_cart( $plain->get_id(), 1 );
		WC()->cart->calculate_totals();

		$this->assertSame( 'Gift card', $this->line_product( (string) $key )->get_name() );
	}

	public function test_the_order_line_saved_at_checkout_carries_the_name(): void
	{
		$this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items( $order, WC()->cart );
		$order->save();

		$items = array_values( wc_get_order( $order->get_id() )->get_items() );
		$this->assertCount( 1, $items );
		$this->assertSame( 'Mercedes-Benz Vito rental', $items[0]->get_name() );
	}

	public function test_the_order_line_shows_dates_in_the_site_format(): void
	{
		update_option( 'date_format', 'd/m/Y' );

		$this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items( $order, WC()->cart );
		$order->save();

		$item = array_values( wc_get_order( $order->get_id() )->get_items() )[0];
		$this->assertSame( '05/07/2026 10:00', $item->get_meta( 'Pickup' ) );
		$this->assertSame( '08/07/2026 11:30', $item->get_meta( 'Dropoff' ) );
	}

	public function test_a_timestamp_date_format_does_not_break_checkout(): void
	{
		// date_i18n() returns an int for the 'U' format; a string-typed helper
		// returning it unconverted threw a TypeError while the order was built.
		update_option( 'date_format', 'U' );

		$this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$order = wc_create_order();
		WC()->checkout()->create_order_line_items( $order, WC()->cart );
		$order->save();

		$item = array_values( wc_get_order( $order->get_id() )->get_items() )[0];
		$this->assertMatchesRegularExpression( '/^\d+ 10:00$/', (string) $item->get_meta( 'Pickup' ) );
	}

	/**
	 * The block cart's line, as WooCommerce's Store API builds it.
	 *
	 * @return array<string, mixed>
	 */
	private function store_api_line( string $key ): array
	{
		$schema = StoreApi::container()->get( SchemaController::class )->get( 'cart-item' );

		return $schema->get_item_response( WC()->cart->get_cart()[ $key ] );
	}

	public function test_the_block_cart_line_shows_the_name_and_the_vehicle_image(): void
	{
		$key = $this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$line = $this->store_api_line( $key );

		$this->assertSame( 'Mercedes-Benz Vito rental', $line['name'] );
		$this->assertCount( 1, $line['images'] );
		$this->assertSame( $this->image_id, $line['images'][0]->id );
	}

	public function test_the_block_cart_image_has_the_shape_woocommerce_builds(): void
	{
		$key = $this->add_booking( $this->rental_data() );
		WC()->cart->calculate_totals();

		$image = $this->store_api_line( $key )['images'][0];
		$wc    = StoreApi::container()->get( SchemaController::class )->get( 'image' )->get_item_response( $this->image_id );

		$this->assertIsObject( $image );
		$this->assertEqualsCanonicalizing( array_keys( (array) $wc ), array_keys( (array) $image ) );
		$this->assertSame( $wc->src, $image->src );
		$this->assertSame( $wc->thumbnail, $image->thumbnail );
		$this->assertSame( 'Mercedes-Benz Vito', $image->alt );
	}

	public function test_a_vehicle_without_an_image_keeps_woocommerces_images(): void
	{
		delete_post_thumbnail( $this->vehicle_id );
		$key       = $this->add_booking( $this->rental_data() );
		$cart_item = WC()->cart->get_cart()[ $key ];
		$input     = array( (object) array( 'id' => 9 ) );

		$this->assertSame( $input, WooCommerceBridge::inject_vehicle_image_for_blocks( $input, $cart_item, $key ) );
	}
}
