<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin;

use WP_UnitTestCase;

final class LayoutStandardTest extends WP_UnitTestCase {

	/** @return array<string, array{0:string}> */
	public function capped_stylesheets(): array {
		return array(
			'customers'      => array( 'src-react/admin/customers/customers.css' ),
			'shortcode-page' => array( 'src-react/admin/shortcode-pages/shortcode-pages.css' ),
			'settings'       => array( 'assets/css/admin/settings.css' ),
		);
	}

	/**
	 * @dataProvider capped_stylesheets
	 */
	public function test_no_page_root_caps_its_width( string $relative ): void {
		$css = (string) file_get_contents( MHMRENTIVA_PLUGIN_PATH . $relative );

		// The page-level caps this migration removes. Field- and cell-level caps stay.
		$this->assertDoesNotMatchRegularExpression(
			'/\.(rv-cust|rv-scp)\s*\{[^}]*max-width|\.wrap\.mhm-settings-page\s*\{[^}]*max-width/',
			$css
		);
	}

	public function test_settings_shell_carries_the_kit_page_classes_and_a_measure_column(): void {
		$tpl = (string) file_get_contents( MHMRENTIVA_PLUGIN_PATH . 'templates/admin/settings-page.php' );

		$this->assertStringContainsString( 'wrap mhm-settings-page mhmui-admin mhmui-admin-page', $tpl );
		$this->assertStringContainsString( 'mhm-settings-tab-container mhmui-measure', $tpl );
	}
}
