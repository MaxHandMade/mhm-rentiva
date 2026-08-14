<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * Drag-to-reorder.
 *
 * WHY A BAD ID FAILS THE WHOLE REQUEST
 * ------------------------------------
 * This endpoint takes a list, not a single target, so "validate each item"
 * has two possible meanings: skip the bad ones, or refuse the batch. It refuses
 * the batch.
 *
 * Skipping would leave the ordering half-applied — some rows moved, some not —
 * against a list the operator can see, so the screen would disagree with the
 * database and the only way back would be to drag everything again. Worse, a
 * partial write is the shape that hides an attack: slipping one foreign ID into
 * an otherwise valid batch would write to it while the response still looked
 * successful. Refusing the batch makes the failure loud and leaves the previous
 * order intact.
 *
 * The order is stored in WordPress's own `menu_order`, which the post type
 * already supports through `page-attributes`. Note what it is NOT stored in:
 * `mhmrentiva_addon_display_order` sounds like this but is a site-wide SETTING
 * naming the sort criterion (menu_order | title | price_asc | price_desc |
 * date_created), not a per-post position.
 */
final class AddonReorderEndpointTest extends WP_UnitTestCase {

	/** @var int[] */
	private array $addon_ids = array();

	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		foreach ( array( 'GPS', 'Child seat', 'Extra driver' ) as $index => $title ) {
			$this->addon_ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'mhmrentiva_addon',
					'post_title'  => $title,
					'post_status' => 'publish',
					'menu_order'  => $index,
				)
			);
		}
	}

	protected function tearDown(): void {
		$_POST           = array();
		$this->addon_ids = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param int[] $order
	 * @return array<string,mixed>
	 */
	private function build_request( array $order, array $overrides = array() ): array {
		return array_merge(
			array(
				'nonce' => wp_create_nonce( AddonScreen::NONCE_ACTION ),
				'order' => array_map( 'strval', $order ),
			),
			$overrides
		);
	}

	/** @return int[] */
	private function current_order(): array {
		$positions = array();
		foreach ( $this->addon_ids as $id ) {
			$positions[ $id ] = (int) get_post_field( 'menu_order', $id );
		}
		return $positions;
	}

	public function test_it_writes_the_new_positions(): void {
		$reversed = array_reverse( $this->addon_ids );

		$result = AddonScreen::reorder( $this->build_request( $reversed ) );

		$this->assertTrue( $result['success'] );

		$positions = $this->current_order();
		foreach ( $reversed as $expected_position => $id ) {
			$this->assertSame(
				$expected_position,
				$positions[ $id ],
				sprintf( 'Add-on %d should sit at position %d.', $id, $expected_position )
			);
		}
	}

	/**
	 * The rule this endpoint is built around.
	 */
	public function test_one_foreign_id_rejects_the_entire_batch(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page', 'menu_order' => 0 ) );
		$before  = $this->current_order();

		$order   = array_reverse( $this->addon_ids );
		$order[] = $page_id;

		$result = AddonScreen::reorder( $this->build_request( $order ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, $this->current_order(), 'No add-on may move when the batch is refused.' );
		$this->assertSame(
			0,
			(int) get_post_field( 'menu_order', $page_id ),
			'The foreign post must not be written to either.'
		);
	}

	public function test_a_missing_id_rejects_the_batch(): void {
		$before  = $this->current_order();
		$order   = $this->addon_ids;
		$order[] = 99999999;

		$result = AddonScreen::reorder( $this->build_request( $order ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, $this->current_order() );
	}

	public function test_an_empty_order_is_refused(): void {
		$result = AddonScreen::reorder( $this->build_request( array() ) );

		$this->assertFalse( $result['success'] );
	}

	/** Guard 1 in isolation. */
	public function test_it_refuses_a_bad_nonce(): void {
		$before = $this->current_order();

		$result = AddonScreen::reorder(
			$this->build_request( array_reverse( $this->addon_ids ), array( 'nonce' => 'nope' ) )
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, $this->current_order() );
	}

	/** Guard 2 in isolation — user switched before the nonce is minted. */
	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$before = $this->current_order();

		$result = AddonScreen::reorder( $this->build_request( array_reverse( $this->addon_ids ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, $this->current_order() );
	}

	public function test_the_endpoint_is_registered_on_admin_init(): void {
		\MHMRentiva\Admin\Addons\AddonManager::admin_init();

		$this->assertSame(
			10,
			has_action( 'wp_ajax_mhmrentiva_addon_reorder', array( AddonScreen::class, 'ajax_reorder' ) )
		);
	}
}
