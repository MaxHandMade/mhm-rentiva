<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\AssetManager;
use WP_UnitTestCase;

/**
 * The seam between this plugin and the shared React page loader.
 *
 * WHY THIS TEST IS HERE AND NOT ONLY IN THE PACKAGE
 * -------------------------------------------------
 * mhm/ui-core has its own suite for mhmuicore_enqueue_react_page(). That suite
 * calls the function directly. Production does not: production enters through
 * AssetManager::enqueue_react_page(), and so does the add-on -- which passes
 * its OWN path, URL and text domain into this method for five screens. The
 * package being correct proves nothing about the arguments this method hands
 * it, and that mapping is the part that has actually been wrong before.
 *
 * THE HISTORICAL BUG THIS PINS
 * ----------------------------
 * The text domain used to be hardcoded to 'mhm-rentiva' while $base_dir was
 * already a parameter. The add-on's five relocated bundles therefore asked
 * WordPress for 'mhm-rentiva' catalogues inside the ADD-ON's languages/
 * directory -- a lookup that can never succeed, because the add-on compiles
 * under 'mhm-rentiva-pro'. The pair has to travel together, so a test that
 * checks only one of them would still pass on the broken version.
 */
final class ReactPageEnqueueAdapterTest extends WP_UnitTestCase {

	/**
	 * The enqueue registries this class replaces, put back in tearDown().
	 *
	 * @var array<string, mixed>
	 */
	private array $saved_registries = array();

	/**
	 * Start every test from an empty enqueue surface -- and hand it back.
	 *
	 * WordPress keeps wp_scripts()/wp_styles() in globals that outlive a single
	 * test, and the nonce middleware is guarded by a once-per-request flag. The
	 * first draft of this class left all three alone and two tests agreed for
	 * the wrong reason: the stylesheet assertion passed on a queue an earlier
	 * test had filled, and the "added once" delta measured a flag an earlier
	 * test had already consumed.
	 *
	 * Nulling the two globals makes wp_scripts()/wp_styles() rebuild, which
	 * re-runs core's own wp_default_scripts -- so core's nonce line is present
	 * in the baseline exactly as it is in a real request.
	 *
	 * 🔴 And then tearDown() MUST put the originals back. The second draft did
	 * not, and it broke three unrelated tests in BundledFontEnqueueTest and
	 * FeaturedVehiclesSliderAssetsTest -- they register handles during plugin
	 * boot and then assert on them, so a registry this class silently replaced
	 * arrived at them empty. Each of the three passed when run alone. Isolating
	 * yourself at the cost of the tests that run after you is not isolation.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->saved_registries = array(
			'wp_scripts' => $GLOBALS['wp_scripts'] ?? null,
			'wp_styles'  => $GLOBALS['wp_styles'] ?? null,
			'nonce'      => $GLOBALS['mhmuicore_react_nonce_added'] ?? null,
		);

		$GLOBALS['wp_scripts']                  = null;
		$GLOBALS['wp_styles']                   = null;
		$GLOBALS['mhmuicore_react_nonce_added'] = false;

		/*
		 * 🔴 The SECOND nonce guard, and it has to be reset by reflection.
		 *
		 * Two guards protect one behaviour here: the package's global, and this
		 * class's own private static used by the fallback branch. A class static
		 * outlives a test, so whichever test enqueued first consumed it for the
		 * whole run -- and that masked the other guard completely.
		 *
		 * Measured: deleting the `return;` that ends the delegation (making BOTH
		 * branches run and double-register the middleware) left this suite green.
		 * With this reset in place the same mutation turns it red. The reset is
		 * not tidiness; it is the difference between a test and a decoration.
		 */
		$fallback_guard = new \ReflectionProperty( AssetManager::class, 'react_nonce_added' );
		$fallback_guard->setAccessible( true );
		$fallback_guard->setValue( null, false );
	}

	/**
	 * Hand the enqueue registries back exactly as they were found.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp_scripts']                  = $this->saved_registries['wp_scripts'];
		$GLOBALS['wp_styles']                   = $this->saved_registries['wp_styles'];
		$GLOBALS['mhmuicore_react_nonce_added'] = $this->saved_registries['nonce'];

		parent::tearDown();
	}

	/**
	 * Declare which world this test measured.
	 *
	 * The method has two branches: delegate to the shared loader, or run its
	 * own original body when an older ui-core won the version arbitration. Both
	 * are meant to produce the same registration, so the assertions below cannot
	 * tell them apart -- which would make a silently-missing package look like a
	 * pass. This says out loud which branch the rest of the class exercised.
	 */
	public function test_the_shared_loader_is_the_branch_under_test(): void {
		$this->assertTrue(
			function_exists( 'mhmuicore_enqueue_react_page' ),
			'The bundled ui-core did not boot, so these tests measured the local fallback '
			. 'branch, not the shipped one. Check vendor/mhm/ui-core and the '
			. 'mhmuicore_register() literal in mhm-rentiva.php.'
		);

		/*
		 * Existence is not delegation. The function being present says nothing
		 * about this method calling it -- measured: replacing the guard with
		 * `if ( false )`, so the method silently ran its own fallback body for
		 * every page, left the whole class green. The two branches are meant to
		 * be behaviourally identical, so no outcome assertion can separate them.
		 *
		 * Exactly one observable does: the package records "nonce already
		 * installed" in its own global, the fallback in this class's private
		 * static. Reading the global after one call is therefore the only cheap
		 * proof that the shared implementation is what ran -- and without it,
		 * the code could quietly stop using the package it was moved into.
		 */
		AssetManager::enqueue_react_page( 'dashboard' );

		$this->assertTrue(
			! empty( $GLOBALS['mhmuicore_react_nonce_added'] ),
			'The method did not delegate to the shared loader: the package never '
			. 'recorded the nonce middleware, so the local fallback body ran instead.'
		);
	}

	/**
	 * The default call describes this plugin: its URL, its version source, its
	 * domain, its catalogues.
	 */
	public function test_own_page_is_registered_from_this_plugins_own_identity(): void {
		AssetManager::enqueue_react_page( 'dashboard' );

		$script = wp_scripts()->registered['mhm-rentiva-react-dashboard'] ?? null;
		$this->assertNotNull( $script, 'The bundle was not registered at all.' );

		$this->assertSame(
			MHMRENTIVA_PLUGIN_URL . 'build/admin/dashboard.js',
			$script->src
		);
		$this->assertSame( 'mhm-rentiva', $script->textdomain );
		$this->assertSame( MHMRENTIVA_PLUGIN_DIR . 'languages/', $script->translations_path );
	}

	/**
	 * Dependencies and version come from the generated manifest, not from the
	 * plugin version. The manifest's version is a content hash; substituting the
	 * plugin version would serve stale bytes under an unchanged cache key for a
	 * whole release cycle.
	 */
	public function test_dependencies_and_version_come_from_the_generated_manifest(): void {
		$manifest = include MHMRENTIVA_PLUGIN_DIR . 'build/admin/dashboard.asset.php';

		AssetManager::enqueue_react_page( 'dashboard' );
		$script = wp_scripts()->registered['mhm-rentiva-react-dashboard'];

		$this->assertSame( $manifest['dependencies'], $script->deps );
		$this->assertSame( $manifest['version'], $script->ver );
		$this->assertNotSame( MHMRENTIVA_VERSION, $script->ver );
	}

	/**
	 * 🔴 The add-on's shape: a foreign directory, URL and text domain.
	 *
	 * The directory deliberately does not exist, which does double duty -- it
	 * proves the catalogue path follows $base_dir rather than this plugin's own,
	 * and it exercises the missing-manifest fallback, where the caller-supplied
	 * version is the only thing standing between a deploy and an un-busted cache.
	 */
	public function test_addon_page_keeps_the_addons_url_domain_and_catalogues(): void {
		$addon_dir = MHMRENTIVA_PLUGIN_DIR . 'tests/does-not-exist-addon/';
		$addon_url = 'https://example.test/wp-content/plugins/addon/';

		AssetManager::enqueue_react_page( 'reports', array(), $addon_dir, $addon_url, 'mhm-rentiva-pro' );

		$script = wp_scripts()->registered['mhm-rentiva-react-reports'] ?? null;
		$this->assertNotNull( $script );

		$this->assertSame( $addon_url . 'build/admin/reports.js', $script->src );
		$this->assertSame( 'mhm-rentiva-pro', $script->textdomain );
		$this->assertSame( $addon_dir . 'languages/', $script->translations_path );
		$this->assertSame( MHMRENTIVA_VERSION, $script->ver, 'No manifest: the caller version must be used.' );

		/*
		 * Not array(): wp_set_script_translations() appends 'wp-i18n' to the
		 * handle's dependencies when it is not already there. The page above has
		 * no manifest, so it starts empty and ends with exactly that one. The
		 * dashboard case does not show this because its generated manifest
		 * already lists wp-i18n -- which is why the empty-manifest case is the
		 * one that reveals the behaviour.
		 */
		$this->assertSame( array( 'wp-i18n' ), $script->deps );
	}

	/**
	 * WordPress ships its React components unstyled; without this the screen
	 * renders bare.
	 */
	public function test_component_stylesheet_is_enqueued(): void {
		$this->assertNotContains( 'wp-components', wp_styles()->queue );

		AssetManager::enqueue_react_page( 'dashboard' );

		$this->assertContains( 'wp-components', wp_styles()->queue );
	}

	/**
	 * One REST nonce middleware per request, however many pages enqueue.
	 *
	 * Measured as a DELTA, not an absolute count: WordPress core attaches its
	 * own createNonceMiddleware line to wp-api-fetch, so counting occurrences
	 * would fold core's in with ours and agree for the wrong reason. A probe
	 * that did exactly that reported "2" during this change and looked like a
	 * duplicate-registration bug for a minute.
	 */
	public function test_nonce_middleware_is_added_once_for_two_pages(): void {
		$before = $this->nonce_middleware_lines();

		AssetManager::enqueue_react_page( 'dashboard' );
		AssetManager::enqueue_react_page( 'customers' );

		$this->assertSame(
			$before + 1,
			$this->nonce_middleware_lines(),
			'Two pages must add exactly one nonce middleware between them.'
		);
	}

	/**
	 * Count the inline lines attached after wp-api-fetch that install a nonce
	 * middleware, whoever put them there.
	 */
	private function nonce_middleware_lines(): int {
		$after = wp_scripts()->get_data( 'wp-api-fetch', 'after' );

		if ( ! is_array( $after ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $after as $line ) {
			if ( is_string( $line ) && false !== strpos( $line, 'createNonceMiddleware' ) ) {
				++$count;
			}
		}

		return $count;
	}
}
