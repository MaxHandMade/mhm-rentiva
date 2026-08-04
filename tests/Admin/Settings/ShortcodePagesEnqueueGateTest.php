<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Core\ShortcodeUrlManager;
use MHMRentiva\Admin\Settings\ShortcodePages;
use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use MHMRentiva\Admin\Utilities\Menu\Menu;
use WP_UnitTestCase;

/**
 * T8 Görev 11 Part 2 (independent nonce-behavior audit, Fable#2): before this
 * fix, ShortcodePages::enqueue_assets()'s gate was
 *
 *   ( '' !== $this->page_hook && $hook_suffix === $this->page_hook )
 *       || str_contains( $hook_suffix, self::MENU_SLUG )
 *
 * $this->page_hook is set only by add_admin_menu() -- a method Menu.php
 * never calls (it registers this screen's add_submenu_page() itself,
 * centrally); in production $this->page_hook is always '', so that half of
 * the OR never fires and str_contains() alone decides every request. A
 * foreign screen whose hook merely EMBEDS "mhm-rentiva-shortcode-pages" as a
 * substring (e.g. a hypothetical settings sub-tab hook, or a typo'd sibling
 * screen) would also pull the React bundle + CSS in.
 *
 * The fix pins the gate to the exact hook suffix WordPress actually assigns
 * this screen -- the same idiom About::enqueue_scripts() and Görev 3's
 * Addons\AddonSettings::enqueue_scripts() already use for their own screens
 * -- rather than resurrecting the historical blank-screen bug that motivated
 * the str_contains() fallback in the first place ($this->page_hook being
 * unconditionally '' in production, per the comment this replaces).
 *
 * @covers \MHMRentiva\Admin\Settings\ShortcodePages::enqueue_assets
 */
final class ShortcodePagesEnqueueGateTest extends WP_UnitTestCase {

	private const REAL_HOOK      = 'mhm-rentiva_page_mhm-rentiva-shortcode-pages';
	private const HANDLE_SCRIPT  = 'mhm-rentiva-react-shortcode-pages';
	private const HANDLE_STYLE   = 'mhm-rentiva-shortcode-pages';

	/** @var array<int|string,mixed> */
	private array $menu_backup = array();
	/** @var array<int|string,mixed> */
	private array $submenu_backup = array();

	private ShortcodePages $orchestrator;

	public function setUp(): void {
		parent::setUp();

		global $menu, $submenu;
		$this->menu_backup    = is_array( $menu ) ? $menu : array();
		$this->submenu_backup = is_array( $submenu ) ? $submenu : array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->orchestrator = new ShortcodePages( new ShortcodePageActions(), new ShortcodeUrlManager() );

		$this->reset_handles();
	}

	public function tearDown(): void {
		global $menu, $submenu;
		$menu    = $this->menu_backup;
		$submenu = $this->submenu_backup;

		$this->reset_handles();
		$this->reset_shortcode_pages_singleton();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function reset_handles(): void {
		wp_dequeue_script( self::HANDLE_SCRIPT );
		wp_deregister_script( self::HANDLE_SCRIPT );
		wp_dequeue_style( self::HANDLE_STYLE );
		wp_deregister_style( self::HANDLE_STYLE );
	}

	/**
	 * The runtime probe below calls the real Menu::add_menu(), which -- among
	 * its other submenu registrations -- builds the Shortcode Pages callback
	 * as `array( ShortcodePages::register(), 'render_page' )`. register() is a
	 * memoising singleton (`self::$instance ??=`), so the first call anywhere
	 * in the whole test run constructs the one-and-only instance and wires its
	 * `admin_enqueue_scripts` action for that object.
	 *
	 * WP_UnitTestCase backs up and restores WordPress hooks around every test,
	 * so that action is gone again once this test ends -- but `self::$instance`
	 * is a plain PHP static, untouched by the hook restore, so it survives.
	 * Left alone, the NEXT test to call register() would get the same cached
	 * instance without register_hooks() running again, permanently losing its
	 * admin_enqueue_scripts registration -- exactly what broke
	 * ShortcodePagesTest::it_registers_hooks_correctly() the first time this
	 * file was added (it sorts alphabetically after this one, so it inherited
	 * the poisoned singleton). Resetting the static after this test keeps the
	 * probe's realism (it still exercises Menu::add_menu() verbatim) without
	 * leaking test order dependence into unrelated test classes.
	 */
	private function reset_shortcode_pages_singleton(): void {
		$instance = new \ReflectionProperty( ShortcodePages::class, 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Runtime probe (brief's explicit requirement): don't just assert a
	 * hand-typed literal against itself -- run the REAL production
	 * registration path (Menu::add_menu(), the method that actually calls
	 * add_submenu_page() for this screen; ShortcodePages::add_admin_menu()
	 * is dead code Menu.php never invokes) and read back the hook suffix
	 * WordPress itself computes via get_plugin_page_hookname(). This is the
	 * exact value admin_enqueue_scripts hands to enqueue_assets() live, and
	 * it is what REAL_HOOK below must equal.
	 */
	public function test_runtime_probe_of_the_real_shortcode_pages_hook_suffix(): void {
		global $menu, $submenu;
		$menu    = array();
		$submenu = array();

		Menu::add_menu();

		$probed_suffix = get_plugin_page_hookname( 'mhm-rentiva-shortcode-pages', 'mhm-rentiva' );

		$this->assertSame(
			self::REAL_HOOK,
			$probed_suffix,
			'Menu.php registration no longer produces the hook suffix the gate is pinned to -- update both together.'
		);

		// Not just a name WP could theoretically compute -- a callback must
		// really be wired to it, proving Menu::add_menu() registered this
		// exact screen under this exact hookname.
		$this->assertNotFalse(
			has_action( $probed_suffix ),
			'No callback is registered on the probed hookname -- Menu.php did not really register this screen under it.'
		);
	}

	public function test_enqueue_assets_loads_on_the_real_production_hook_suffix(): void {
		$this->orchestrator->enqueue_assets( self::REAL_HOOK );

		$this->assertTrue( wp_style_is( self::HANDLE_STYLE, 'enqueued' ), 'The real hook suffix must enqueue the style.' );
		$this->assertTrue( wp_script_is( self::HANDLE_SCRIPT, 'enqueued' ), 'The real hook suffix must enqueue the React script.' );
	}

	/**
	 * Negative control, Görev-3 hole lesson: a hook that does not even
	 * contain the slug (e.g. 'toplevel_page_mhm-rentiva') can't distinguish
	 * an exact-match gate from a str_contains() gate -- both would reject it
	 * the same way. These hooks all DO contain "mhm-rentiva-shortcode-pages"
	 * as a substring without being the exact suffix, so only an exact-match
	 * gate rejects them; a str_contains() gate would wrongly accept every one.
	 *
	 * @dataProvider foreign_hook_containing_the_slug_provider
	 */
	public function test_enqueue_assets_does_nothing_when_hook_merely_contains_the_slug( string $foreign_hook ): void {
		$this->orchestrator->enqueue_assets( $foreign_hook );

		$this->assertFalse(
			wp_style_is( self::HANDLE_STYLE, 'enqueued' ),
			"A hook that merely contains the slug as a substring must not enqueue the style: '$foreign_hook'."
		);
		$this->assertFalse(
			wp_script_is( self::HANDLE_SCRIPT, 'enqueued' ),
			"A hook that merely contains the slug as a substring must not enqueue the React script: '$foreign_hook'."
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function foreign_hook_containing_the_slug_provider(): array {
		return array(
			'suffix appended after the real hook'    => array( self::REAL_HOOK . '-extra' ),
			'different (wrong) parent-page prefix'   => array( 'admin_page_mhm-rentiva-shortcode-pages' ),
			'slug embedded as a sub-tab of settings'  => array( 'mhm-rentiva_page_mhm-rentiva-settings-mhm-rentiva-shortcode-pages' ),
		);
	}
}
