<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\SettingHasEffectGate;
use MHMRentiva\Tests\Support\SettingProbe;
use WP_UnitTestCase;

/**
 * Gate G1 for Lite's 16 blocks and 17 widgets (the gate itself: SettingHasEffectGate).
 *
 * The probe renders ~590 surfaces once per process (about 100 s on the dev
 * container); the results are shared by every test method of the class.
 */
final class SettingHasEffectTest extends WP_UnitTestCase
{
	use SettingHasEffectGate;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the Elementor half of the gate would measure an absence.');
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	protected function gate_blocks(): ?array
	{
		return null;
	}

	protected function gate_widgets(): ?array
	{
		return null;
	}

	protected function gate_dir(): string
	{
		return __DIR__;
	}

	/**
	 * hide_nav: AccountRenderer::get_navigation() returns [] whenever WooCommerce is
	 * loaded, so these rows can not render an effect here; HideNavHasEffectTest
	 * proves it link by link. Pinned by equality (SettingHasEffectGate).
	 */
	public const UNAVAILABLE_UNDER_WOOCOMMERCE = array(
		'block|rentiva_my_bookings|hide_nav',
		'elementor|rentiva_my_bookings|hide_nav',
		'block|rentiva_payment_history|hide_nav',
		'elementor|rentiva_payment_history|hide_nav',
	);

	protected function gate_unavailable(): array
	{
		return self::UNAVAILABLE_UNDER_WOOCOMMERCE;
	}

	/**
	 * Surface counts on 2026-10-10 (Task 0). Lower them only together with a
	 * deliberate removal of settings.
	 */
	protected function gate_floors(): array
	{
		return array(
			'block'     => 278,
			'elementor' => 313,
		);
	}

	protected function gate_without_settings(): array
	{
		return array( 'user-dashboard', 'UserDashboardWidget' );
	}
}
