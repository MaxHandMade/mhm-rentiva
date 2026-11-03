<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Setup;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Setup\SetupWizard;
use WP_UnitTestCase;

/**
 * The wizard's stylesheet reads ui-core tokens (`--mhmui-*`), which are
 * declared on `.mhmui-admin` in the kit's admin.css. The static token gate
 * cannot follow that family (the kit lives in vendor/), so this runtime
 * contract stands in for it: the kit is a declared dependency, it is
 * enqueued, and the body carries the scope class.
 *
 * @covers \MHMRentiva\Admin\Setup\SetupWizard
 */
final class SetupWizardKitContractTest extends WP_UnitTestCase
{
	private const HOOK = 'mhm-rentiva_page_mhm-rentiva-setup';

	/** @var mixed */
	private $saved_styles;

	public function set_up(): void
	{
		parent::set_up();
		$this->saved_styles   = $GLOBALS['wp_styles'] ?? null;
		$GLOBALS['wp_styles'] = null;
		remove_filter('admin_body_class', array( SetupWizard::class, 'add_body_class' ));
	}

	public function tear_down(): void
	{
		remove_filter('admin_body_class', array( SetupWizard::class, 'add_body_class' ));
		unset($_GET['page'], $_GET['step']);
		$GLOBALS['wp_styles'] = $this->saved_styles;
		parent::tear_down();
	}

	public function test_wizard_style_depends_on_the_kit(): void
	{
		SetupWizard::enqueue_assets(self::HOOK);

		$this->assertSame('mhmuicore-admin', AssetManager::kit_handle('admin'));
		$this->assertContains('mhmuicore-admin', wp_styles()->registered['mhm-rentiva-setup-wizard']->deps);
	}

	public function test_kit_is_enqueued_on_the_wizard(): void
	{
		SetupWizard::enqueue_assets(self::HOOK);

		$this->assertTrue(wp_style_is('mhmuicore-admin', 'enqueued'));
	}

	public function test_wizard_body_class_adds_mhmui_admin(): void
	{
		SetupWizard::enqueue_assets(self::HOOK);

		// Only this filter: other plugins' admin_body_class callbacks need a
		// real admin request (WooCommerce raises a doing_it_wrong here).
		$this->assertNotFalse(has_filter('admin_body_class', array( SetupWizard::class, 'add_body_class' )));
		$classes = SetupWizard::add_body_class('a');

		$this->assertContains('mhmui-admin', preg_split('/\s+/', trim($classes)));
		$this->assertContains('a', preg_split('/\s+/', trim($classes)));
	}

	public function test_kit_not_enqueued_on_other_admin_pages(): void
	{
		SetupWizard::enqueue_assets('index.php');

		$this->assertFalse(wp_style_is('mhm-rentiva-setup-wizard', 'enqueued'));
		$this->assertFalse(wp_style_is('mhmuicore-admin', 'enqueued'));
		$this->assertFalse(has_filter('admin_body_class', array( SetupWizard::class, 'add_body_class' )));
	}
}
