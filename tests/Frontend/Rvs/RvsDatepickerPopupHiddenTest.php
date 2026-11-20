<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Rvs;

use WP_UnitTestCase;

/**
 * The jQuery UI datepicker popup is closed until a field opens it.
 *
 * jQuery UI creates #ui-datepicker-div at init and relies on its own base
 * stylesheet to hide it. The booking form loads neither that stylesheet nor
 * the old datepicker skin, and the rvs look starts only when the popup opens
 * (beforeShow adds `rvs-dp`), so on a site with no other datepicker skin the
 * empty popup sat unstyled at the end of the page. Measured in the browser:
 * closed on load, open (rvs-dp) on show, closed again after hide.
 */
final class RvsDatepickerPopupHiddenTest extends WP_UnitTestCase
{
	public function test_the_rvs_datepicker_stylesheet_keeps_the_popup_closed(): void
	{
		$css = (string) file_get_contents(MHMRENTIVA_PLUGIN_DIR . 'assets/css/rvs/rvs-datepicker.css');
		$css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

		$this->assertMatchesRegularExpression('/#ui-datepicker-div\s*\{\s*display:\s*none;\s*\}/', $css, 'No rule closes the popup.');
		// Not !important: jQuery UI opens the popup with an inline display:block.
		$this->assertDoesNotMatchRegularExpression('/#ui-datepicker-div\s*\{[^}]*!important/', $css);
	}

	public function test_the_booking_form_loads_that_stylesheet(): void
	{
		\MHMRentiva\Admin\Core\AssetManager::enqueue_rvs_booking();

		$this->assertTrue(wp_style_is('mhm-rentiva-rvs-datepicker', 'enqueued'), 'The booking chain does not load rvs-datepicker.css.');
	}
}
