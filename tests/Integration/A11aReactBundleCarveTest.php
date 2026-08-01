<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use WP_UnitTestCase;

/**
 * Task A11a (WP.org T4 Phase A -- seam inversion) moved the 5 Pro-only React
 * admin bundles out of Lite's tree entirely, both the pre-built assets and
 * their JSX source:
 *
 * - messages
 * - reports
 * - export
 * - vendor-management
 * - vendor-reports
 *
 * Before this task those bundles SHIPPED in Lite's build/admin/ (and their
 * source in Lite's src-react/admin/) even though only Pro's enqueue classes
 * ever loaded them -- pointed at Lite's own MHMRENTIVA_PLUGIN_URL. That
 * leaked paid-feature compiled JS/CSS (and readable JSX source) into the
 * free WP.org ZIP, a Guideline 4/5 violation. They now live under
 * mhm-rentiva-pro/build/admin/ and mhm-rentiva-pro/src-react/admin/ instead,
 * enqueued via MHMRENTIVA_PRO_URL / MHMRENTIVA_PRO_PATH.
 *
 * Lite keeps its own 4 screens: about, customers, dashboard, shortcode-pages.
 */
final class A11aReactBundleCarveTest extends WP_UnitTestCase {

	/** @return list<string> Bundle basenames moved to Pro. */
	private function moved_bundles(): array {
		return array(
			'messages',
			'reports',
			'export',
			'vendor-management',
			'vendor-reports',
		);
	}

	/** @return list<string> Bundle basenames Lite still owns. */
	private function lite_owned_bundles(): array {
		return array(
			'about',
			'customers',
			'dashboard',
			'shortcode-pages',
		);
	}

	public function test_none_of_the_moved_bundles_ship_in_lite_build_admin(): void {
		foreach ( $this->moved_bundles() as $name ) {
			foreach ( array( "{$name}.js", "{$name}.css", "{$name}-rtl.css", "{$name}.asset.php" ) as $file ) {
				$this->assertFileDoesNotExist(
					MHMRENTIVA_PLUGIN_PATH . 'build/admin/' . $file,
					"Lite must not ship build/admin/{$file} any more (Task A11a)."
				);
			}
		}
	}

	public function test_none_of_the_moved_bundles_have_src_react_left_in_lite(): void {
		foreach ( $this->moved_bundles() as $name ) {
			$this->assertDirectoryDoesNotExist(
				MHMRENTIVA_PLUGIN_PATH . 'src-react/admin/' . $name,
				"Lite must not ship src-react/admin/{$name}/ any more (Task A11a)."
			);
		}
	}

	public function test_lite_owned_bundles_are_untouched(): void {
		foreach ( $this->lite_owned_bundles() as $name ) {
			foreach ( array( "{$name}.js", "{$name}.css", "{$name}.asset.php" ) as $file ) {
				$this->assertFileExists(
					MHMRENTIVA_PLUGIN_PATH . 'build/admin/' . $file,
					"Lite must still ship build/admin/{$file}."
				);
			}
			$this->assertDirectoryExists(
				MHMRENTIVA_PLUGIN_PATH . 'src-react/admin/' . $name,
				"Lite must still ship src-react/admin/{$name}/."
			);
		}
	}
}
