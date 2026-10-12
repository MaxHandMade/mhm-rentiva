<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a Task 4 (U4): no internal path or template name ever reaches the
 * HTML, administrators included; "template missing" and "intentionally empty"
 * are told apart.
 */
final class FallbackNoPathLeakTest extends WP_UnitTestCase
{
	use ResetsRenderContext;

	private const FORBIDDEN = array( 'shortcodes/', 'Template:', "\u{015E}ablon dosyas\u{0131}", 'Template file not found' );

	public function set_up(): void
	{
		parent::set_up();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		remove_all_filters('mhmrentiva_template_candidates');
		unset($GLOBALS['post']);
		wp_set_current_user(0);
		parent::tear_down();
	}

	private function assert_no_leak(string $html, string $label): void
	{
		foreach (self::FORBIDDEN as $needle) {
			$this->assertSame(0, substr_count($html, $needle), "$label prints `$needle`: $html");
		}
	}

	private function render_rating(string $atts = ''): string
	{
		RequestSimulator::new_request();
		return do_shortcode('[rentiva_vehicle_rating_form' . $atts . ']');
	}

	public function test_contextless_call_leaks_no_path(): void
	{
		$html = $this->render_rating();
		$this->assertNotSame('', trim($html));
		$this->assert_no_leak($html, 'visitor');

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->assert_no_leak($this->render_rating(), 'administrator');
	}

	public function test_missing_template_leaks_no_path_even_for_admins(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		// Every candidate the locator would try is dropped: the file is "gone".
		add_filter('mhmrentiva_template_candidates', static fn (): array => array(), 999);

		$visitor = $this->render_rating(' vehicle_id="' . $vehicle . '"');
		$this->assertNotSame('', trim($visitor), 'A missing template shows a generic state, not nothing.');
		$this->assertStringContainsString('mhm-rentiva-shortcode-error', $visitor);
		$this->assert_no_leak($visitor, 'visitor');
		$this->assertStringNotContainsString('Error:', $visitor, 'Visitors get no error detail.');

		wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
		$admin = $this->render_rating(' vehicle_id="' . $vehicle . '"');
		$this->assert_no_leak($admin, 'administrator');
		$this->assertStringContainsString('Error:', $admin, 'The detail line comes from get_error_html, behind manage_options.');
	}

	public function test_intentionally_empty_template_stays_empty(): void
	{
		$vehicle = ShortcodeFixtures::vehicle();
		$dir     = get_temp_dir() . 'rv-empty-tpl-' . wp_generate_password(6, false);
		wp_mkdir_p($dir);
		$file = $dir . '/vehicle-rating-form.php';
		file_put_contents($file, '<?php // intentionally empty.'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
		add_filter(
			'mhmrentiva_template_candidates',
			static fn (): array => array( $file ),
			999
		);

		try {
			$html = $this->render_rating(' vehicle_id="' . $vehicle . '"');
		} finally {
			unlink($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture.
			rmdir($dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.rmdir_rmdir -- test fixture.
		}
		$this->assertSame('', $html);
	}

	public function test_shortcode_without_template_file_may_return_empty(): void
	{
		// Empty template path + render_template() returning '' on purpose (the Pro
		// VendorBookings shape) is "intentionally empty", not "missing".
		$tag = 'rv_test_no_template';
		EmptyNoTemplateStub::register_for_test($tag);
		try {
			wp_set_current_user((int) self::factory()->user->create(array( 'role' => 'administrator' )));
			$this->assertSame('', do_shortcode('[' . $tag . ']'));
			wp_set_current_user(0);
			$this->assertSame('', do_shortcode('[' . $tag . ']'));
		} finally {
			remove_shortcode($tag);
		}
	}
}

final class EmptyNoTemplateStub extends \MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode
{
	public static function register_for_test(string $tag): void
	{
		add_shortcode($tag, array( self::class, 'render' ));
	}

	protected static function get_shortcode_tag(): string
	{
		return 'rv_test_no_template';
	}

	protected static function get_template_path(): string
	{
		return '';
	}

	protected static function get_default_attributes(): array
	{
		return array();
	}

	protected static function prepare_template_data(array $atts): array
	{
		return array();
	}

	protected static function render_template(array $template_data): string
	{
		return '';
	}
}
