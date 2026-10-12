<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\UserDashboardWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * User dashboard (retired stub) front-end goldens, recorded BEFORE slice 3B-2a
 * (preview context and placeholders) changes anything. A guest sees nothing, a
 * customer sees the pointer to My Account, an administrator also sees the
 * deprecation sentence; shortcode, block and Elementor widget all render the
 * same stub. 3B-2a gives the Elementor/editor preview a notice; the front end
 * stays byte-identical.
 *
 * (tests/fixtures/golden/user-dashboard/, MHMRENTIVA_RECORD_GOLDEN=1 re-records.)
 */
final class UserDashboardGoldenTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/user-dashboard';

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @dataProvider surfaces
	 */
	public function test_front_end_render_unchanged(string $name): void
	{
		list( $role, $surface ) = explode('|', $name);
		if ('guest' !== $role) {
			wp_set_current_user((int) self::factory()->user->create(array( 'role' => $role )));
		}

		$html = $this->render($surface);
		if ('guest' === $role) {
			$this->assertStringNotContainsString('mhm-rentiva-retired-dashboard', $html, "$name: a guest sees no stub.");
		} else {
			$this->assertStringContainsString('mhm-rentiva-retired-dashboard', $html, "$name rendered no stub; the golden would pin an empty render.");
			$this->assertSame('administrator' === $role, str_contains($html, 'mhm-rentiva-retired-dashboard__admin'), "$name: the deprecation sentence is the administrator's.");
		}

		$actual = $this->golden_form($html);
		$file   = self::GOLDEN_DIR . '/user-dashboard-' . $surface . '-' . $role . '.html';

		if ('1' === getenv('MHMRENTIVA_RECORD_GOLDEN')) {
			if (! is_dir(self::GOLDEN_DIR)) {
				mkdir(self::GOLDEN_DIR, 0777, true); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- test fixture.
			}
			file_put_contents($file, $actual); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture.
			$this->markTestIncomplete("Recorded golden $name.");
		}

		$this->assertFileExists($file, "No golden for $name; record it on the code before the change.");
		$this->assertSame(file_get_contents($file), $actual, "The front-end render of $name changed (spec 3B-2a, golden gate)."); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test fixture.
	}

	/**
	 * @return array<string,array{0:string}>
	 */
	public function surfaces(): array
	{
		$names = array();
		foreach (array( 'guest', 'customer', 'administrator' ) as $role) {
			foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
				$names[] = $role . '|' . $surface;
			}
		}
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render(string $surface): string
	{
		RequestSimulator::new_request();
		switch ($surface) {
			case 'shortcode':
				return do_shortcode('[rentiva_user_dashboard]');
			case 'block':
				return render_block(
					array(
						'blockName'    => 'mhm-rentiva/user-dashboard',
						'attrs'        => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
						'innerBlocks'  => array(),
					)
				);
			case 'widget':
				ob_start();
				try {
					WidgetFactory::make(UserDashboardWidget::class, array())->render_content();
				} finally {
					$html = (string) ob_get_clean();
				}
				return $html;
		}
		throw new \InvalidArgumentException($surface);
	}

	private function golden_form(string $html): string
	{
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return '' === $html ? '' : (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}
}
