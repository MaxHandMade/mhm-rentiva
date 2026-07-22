<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin;

use MHMRentiva\Admin\Vehicle\Settings\VehicleSettings;
use WP_UnitTestCase;

/**
 * A field that is not enabled must not survive in card/detail/comparison selections.
 * Enforced server-side, not only in the browser.
 */
final class VehicleSettingsSaveCascadeTest extends WP_UnitTestCase {

	public function test_disabled_field_is_stripped_from_card_and_detail_selection(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) ); // navigation NOT enabled

		$selection = array(
			array( 'type' => 'feature', 'key' => 'bluetooth' ),
			array( 'type' => 'feature', 'key' => 'navigation' ),
		);

		$filtered = self::call_private( 'filter_selection_by_enabled', array( $selection ) );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'bluetooth', $filtered[0]['key'] );
	}

	public function test_disabled_field_is_stripped_from_comparison(): void {
		update_option( 'mhm_selected_features', array( 'bluetooth' ) );

		$comparison = array( 'features' => array( 'bluetooth', 'navigation' ) );

		$filtered = self::call_private( 'filter_comparison_by_enabled', array( $comparison ) );

		$this->assertSame( array( 'bluetooth' ), array_values( $filtered['features'] ) );
	}

	/**
	 * Reflection helper — these are private by design; the public surface is the AJAX handler.
	 *
	 * @param array<int,mixed> $args
	 * @return mixed
	 */
	private static function call_private( string $method, array $args ) {
		$ref = new \ReflectionMethod( VehicleSettings::class, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( null, $args );
	}
}
