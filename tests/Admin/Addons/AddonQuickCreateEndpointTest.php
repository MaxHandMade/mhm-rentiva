<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Addons;

use MHMRentiva\Admin\Addons\AddonPricingType;
use MHMRentiva\Admin\Addons\AddonScreen;
use WP_UnitTestCase;

/**
 * The quick-create form beside the list.
 *
 * WHAT THE FORM DOES NOT WRITE IS THE INTERESTING PART
 * ----------------------------------------------------
 * An add-on carries eleven fields. This form writes four -- title, description,
 * price, pricing type -- and the full editor keeps the rest. That is fine for
 * every field whose absent value is also its sensible default, and wrong for
 * exactly one:
 *
 *   `mhmrentiva_addon_enabled` is read as `(bool) get_post_meta(...)`
 *   (AddonManager.php:356, AddonMeta.php:181). No meta means '' means false.
 *
 * So a service created here would be born INACTIVE unless the endpoint writes
 * the flag on purpose, and the operator would have no way to tell why: the
 * screen would show a service they just created, switched off, with no
 * explanation. Measured on the dev database, one of the three existing add-ons
 * ("Bebek Koltuğu") has no enabled meta at all and is inactive for this exact
 * reason.
 *
 * The first test below is that rule. The rest guard the boundary.
 */
final class AddonQuickCreateEndpointTest extends WP_UnitTestCase {

	private int $admin_id;

	protected function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	protected function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param array<string,string> $overrides
	 * @return array<string,string>
	 */
	private function build_request( array $overrides = array() ): array {
		return array_merge(
			array(
				'nonce'        => wp_create_nonce( AddonScreen::NONCE_ACTION ),
				'title'        => 'Roadside Assistance',
				'description'  => 'Cover for breakdowns during the rental.',
				'price'        => '150',
				'pricing_type' => AddonPricingType::PER_DAY,
			),
			$overrides
		);
	}

	/**
	 * The rule this endpoint exists to get right.
	 */
	public function test_a_created_service_is_active(): void {
		$result = AddonScreen::quick_create( $this->build_request() );

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			'1',
			(string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_enabled', true ),
			'A service created from the form must be usable immediately; leaving the flag unset makes it inactive.'
		);
	}

	public function test_it_writes_the_four_fields_the_form_collects(): void {
		$result = AddonScreen::quick_create( $this->build_request() );
		$addon  = get_post( $result['addon_id'] );

		$this->assertSame( 'mhmrentiva_addon', $addon->post_type );
		$this->assertSame( 'publish', $addon->post_status );
		$this->assertSame( 'Roadside Assistance', $addon->post_title );
		$this->assertSame( 'Cover for breakdowns during the rental.', $addon->post_excerpt );
		$this->assertSame( '150', (string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_price', true ) );
		$this->assertSame(
			AddonPricingType::PER_DAY,
			(string) get_post_meta( $result['addon_id'], '_mhmrentiva_addon_pricing_type', true )
		);
	}

	/**
	 * `required` is the other post-meta flag the form omits. Unlike `enabled`,
	 * false IS the right default for it -- a new service should not silently
	 * become mandatory on every booking -- so it is written explicitly to say
	 * so rather than left to the absence of a row.
	 */
	public function test_a_created_service_is_not_mandatory(): void {
		$result = AddonScreen::quick_create( $this->build_request() );

		$this->assertSame( '0', (string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_required', true ) );
	}

	/**
	 * Both switches are now part of the form.
	 *
	 * They were left out of the first version on the rule that the quick form
	 * writes four fields and the editor owns the rest. That rule holds for the
	 * complicated fields -- tax rate, category, context -- and broke down for
	 * these two: they are single checkboxes, and leaving them out meant every
	 * newly created service had to be opened in the full editor to be switched
	 * off or made mandatory, which is the trip the form exists to avoid.
	 */
	public function test_a_service_can_be_created_switched_off(): void {
		$result = AddonScreen::quick_create( $this->build_request( array( 'enabled' => '0' ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '0', (string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_enabled', true ) );
	}

	public function test_a_service_can_be_created_as_mandatory(): void {
		$result = AddonScreen::quick_create( $this->build_request( array( 'required' => '1' ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '1', (string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_required', true ) );
	}

	/**
	 * The default when the form says nothing is still active, for the reason
	 * the original K1 test gives: an absent flag reads as false, and a service
	 * born switched off with no explanation is a silent defect.
	 */
	public function test_the_default_is_still_active_when_the_field_is_absent(): void {
		$request = $this->build_request();
		unset( $request['enabled'] );

		$result = AddonScreen::quick_create( $request );

		$this->assertSame( '1', (string) get_post_meta( $result['addon_id'], 'mhmrentiva_addon_enabled', true ) );
	}

	public function test_it_rejects_an_empty_title(): void {
		$result = AddonScreen::quick_create( $this->build_request( array( 'title' => '   ' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['addon_id'] );
	}

	public function test_it_rejects_a_negative_price(): void {
		$result = AddonScreen::quick_create( $this->build_request( array( 'price' => '-5' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['addon_id'] );
	}

	/** An unknown pricing type falls back rather than storing garbage. */
	public function test_it_normalises_an_unknown_pricing_type(): void {
		$result = AddonScreen::quick_create( $this->build_request( array( 'pricing_type' => 'per_fortnight' ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertContains(
			(string) get_post_meta( $result['addon_id'], '_mhmrentiva_addon_pricing_type', true ),
			array( AddonPricingType::PER_DAY, AddonPricingType::PER_BOOKING ),
			'The stored pricing type must always be one the rest of the plugin understands.'
		);
	}

	/** Guard 1 in isolation. */
	public function test_it_refuses_a_bad_nonce(): void {
		$before = wp_count_posts( 'mhmrentiva_addon' )->publish;

		$result = AddonScreen::quick_create( $this->build_request( array( 'nonce' => 'not-a-nonce' ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, wp_count_posts( 'mhmrentiva_addon' )->publish, 'Nothing may be created.' );
	}

	/**
	 * Guard 2 in isolation. The user switch happens BEFORE the nonce is minted;
	 * minting first would tie the nonce to the administrator and this would
	 * fail on the nonce instead, measuring the wrong guard.
	 */
	public function test_it_refuses_a_user_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$before = wp_count_posts( 'mhmrentiva_addon' )->publish;

		$result = AddonScreen::quick_create( $this->build_request() );

		$this->assertFalse( $result['success'] );
		$this->assertSame( $before, wp_count_posts( 'mhmrentiva_addon' )->publish );
	}

	/** The endpoint has to be reachable, not merely callable. */
	public function test_the_endpoint_is_registered_on_admin_init(): void {
		\MHMRentiva\Admin\Addons\AddonManager::admin_init();

		$this->assertSame(
			10,
			has_action( 'wp_ajax_mhmrentiva_addon_quick_create', array( AddonScreen::class, 'ajax_quick_create' ) )
		);
	}
}
