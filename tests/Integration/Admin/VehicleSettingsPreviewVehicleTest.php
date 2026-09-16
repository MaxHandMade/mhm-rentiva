<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * The Display & Preview tab's live preview card shows a real vehicle: the
 * first published vehicle (oldest) that has a featured image, with its own
 * title and daily price. With no such vehicle the payload is empty and the
 * card keeps its grey placeholder and sample name.
 */
final class VehicleSettingsPreviewVehicleTest extends WP_UnitTestCase {

	private function vehicle( string $title, string $date, string $status = 'publish', bool $with_image = true, string $price = '1250' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mhmrentiva_vehicle',
				'post_title'  => $title,
				'post_status' => $status,
				'post_date'   => $date,
			)
		);
		update_post_meta( $id, \MHMRentiva\Admin\Core\MetaKeys::VEHICLE_PRICE_PER_DAY, $price );

		if ( $with_image ) {
			$attachment = self::factory()->attachment->create_object(
				'car-' . $id . '.jpg',
				$id,
				array( 'post_mime_type' => 'image/jpeg' )
			);
			update_post_meta( $attachment, '_wp_attached_file', '2026/09/car-' . $id . '.jpg' );
			set_post_thumbnail( $id, $attachment );
		}

		return $id;
	}

	public function test_no_vehicle_with_an_image_gives_an_empty_payload(): void {
		$this->vehicle( 'No Image', '2020-01-01 10:00:00', 'publish', false );

		$this->assertSame( array(), VehicleSettings::build_preview_vehicle() );
	}

	public function test_the_oldest_published_vehicle_with_an_image_is_used(): void {
		$this->vehicle( 'Newer Car', '2024-05-01 10:00:00' );
		$this->vehicle( 'Draft Oldest', '2019-01-01 10:00:00', 'draft' );
		$this->vehicle( 'Oldest Without Image', '2019-06-01 10:00:00', 'publish', false );
		$this->vehicle( 'Oldest Car', '2021-03-01 10:00:00', 'publish', true, '1850' );

		$preview = VehicleSettings::build_preview_vehicle();

		$this->assertSame( 'Oldest Car', $preview['name'] );
		$this->assertStringContainsString( 'car-', $preview['image'] );
		$this->assertStringContainsString( '.jpg', $preview['image'] );
		$this->assertNotSame( '', $preview['price'] );
		$this->assertStringContainsString( '850', $preview['price'] );
	}

	public function test_a_vehicle_without_a_daily_price_leaves_the_price_empty(): void {
		$this->vehicle( 'Priceless', '2021-03-01 10:00:00', 'publish', true, '' );

		$preview = VehicleSettings::build_preview_vehicle();

		$this->assertSame( 'Priceless', $preview['name'] );
		$this->assertSame( '', $preview['price'] );
	}
}
