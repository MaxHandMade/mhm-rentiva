<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonMeta;
use WP_UnitTestCase;

/**
 * Where the add-on settings box sits on the edit screen.
 *
 * It used to be registered into the `side` context. That column is roughly
 * 280px wide and the box renders its fields through the shared form-table
 * template, which splits every row into a label cell and a control cell -- so
 * each half landed at about 90px and both the checkbox labels and their
 * explanatory text wrapped one or two words per line. The setting was legible
 * only by reading a column of fragments.
 *
 * The fields themselves are fine; the container was wrong. In `normal` they get
 * the full editor width, which is what the form-table template is built for.
 *
 * The sidebar does not end up empty: Publish, Add-on Categories, Contexts,
 * Attributes and the featured image all still live there.
 */
final class AddonSettingsMetaBoxPlacementTest extends WP_UnitTestCase {

	/**
	 * @return array<string, array{context:string, title:string}>
	 */
	private function registered_boxes(): array {
		global $wp_meta_boxes;

		$screen = 'mhmrentiva_addon';
		$found  = array();

		foreach ( (array) ( $wp_meta_boxes[ $screen ] ?? array() ) as $context => $priorities ) {
			foreach ( (array) $priorities as $boxes ) {
				foreach ( (array) $boxes as $id => $box ) {
					if ( ! is_array( $box ) ) {
						continue;
					}
					$found[ (string) $id ] = array(
						'context' => (string) $context,
						'title'   => (string) ( $box['title'] ?? '' ),
					);
				}
			}
		}

		return $found;
	}

	protected function setUp(): void {
		parent::setUp();
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'mhmrentiva_addon' );

		AddonMeta::register();
		do_action( 'add_meta_boxes', 'mhmrentiva_addon', self::factory()->post->create_and_get( array( 'post_type' => 'mhmrentiva_addon' ) ) );
	}

	protected function tearDown(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_the_settings_box_is_not_squeezed_into_the_sidebar(): void {
		$boxes = $this->registered_boxes();

		$this->assertArrayHasKey( 'addon_settings', $boxes, 'The settings box must be registered at all.' );
		$this->assertNotSame(
			'side',
			$boxes['addon_settings']['context'],
			'The form-table template needs the full width; in the sidebar its labels wrap to one word per line.'
		);
	}

	public function test_the_settings_box_sits_in_the_main_column(): void {
		$boxes = $this->registered_boxes();

		$this->assertSame( 'normal', $boxes['addon_settings']['context'] );
	}

	/**
	 * Positive control. Moving every box to `normal`, or dropping the sidebar
	 * boxes entirely, would satisfy the assertions above while emptying the
	 * column that still has to hold the details box's neighbours.
	 */
	public function test_the_details_box_is_still_registered(): void {
		$boxes = $this->registered_boxes();

		$this->assertArrayHasKey( 'addon_details', $boxes );
		$this->assertSame( 'normal', $boxes['addon_details']['context'] );
	}
}
