<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Shortcodes;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\ContactFormWidget;
use MHMRentiva\Tests\Support\RenderNormalizer;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Contact form front-end goldens, recorded BEFORE slice 3B-2a (preview context
 * and placeholders) changes anything: the front-end output of the shortcode, the
 * block and the Elementor widget stays byte-identical, for a guest and for a
 * logged-in customer. The `email-to` variants pin the hidden `recipient_token`
 * input (3B-2a prints no token in a preview; the front end keeps it).
 *
 * (tests/fixtures/golden/contact-form/, MHMRENTIVA_RECORD_GOLDEN=1 re-records).
 * The recipient token is random per render, so its value is masked here, in this
 * test only; the shared RenderNormalizer is untouched.
 */
final class ContactFormGoldenTest extends WP_UnitTestCase
{
	private const GOLDEN_DIR = __DIR__ . '/../../fixtures/golden/contact-form';

	private ?string $request_uri = null;

	public function setUp(): void
	{
		parent::setUp();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the widget renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		// wp_nonce_field() prints the request URI as _wp_http_referer; an earlier test leaves its own behind.
		$this->request_uri      = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test state.
		$_SERVER['REQUEST_URI'] = '';
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tearDown(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		if (null === $this->request_uri) {
			unset($_SERVER['REQUEST_URI']);
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tearDown();
	}

	/**
	 * @dataProvider surfaces
	 */
	public function test_front_end_render_unchanged(string $name): void
	{
		$logged_in = str_ends_with($name, '-logged-in');
		$email_to  = str_contains($name, '-email-to-');
		$surface   = (string) preg_replace('/^contact-form-(email-to-)?/', '', (string) preg_replace('/-logged-in$/', '', $name));

		if ($logged_in) {
			wp_set_current_user(
				(int) self::factory()->user->create(
					array(
						'role'         => 'customer',
						'user_login'   => 'zz_golden_customer',
						'user_email'   => 'zz-golden@example.test',
						'display_name' => 'Zz Golden Customer',
						'first_name'   => 'Zz',
						'last_name'    => 'Golden',
					)
				)
			);
		}

		$html = $this->render($surface, $email_to);
		$this->assertStringContainsString('rv-contact', $html, "$name rendered no contact form; the golden would pin an empty render.");
		$this->assertSame($email_to, str_contains($html, 'name="recipient_token"'), "$name: the recipient token input follows email_to.");

		$actual = $this->golden_form($html);
		$file   = self::GOLDEN_DIR . '/' . $name . '.html';

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
		foreach (array( 'shortcode', 'block', 'widget' ) as $surface) {
			$names[] = 'contact-form-' . $surface;
			$names[] = 'contact-form-' . $surface . '-logged-in';
		}
		// The widget has no recipient control; only the shortcode and the block carry email_to.
		foreach (array( 'shortcode', 'block' ) as $surface) {
			$names[] = 'contact-form-email-to-' . $surface;
		}
		return array_combine($names, array_map(static fn(string $n): array => array( $n ), $names));
	}

	private function render(string $surface, bool $email_to): string
	{
		RequestSimulator::new_request();
		switch ($surface) {
			case 'shortcode':
				return do_shortcode($email_to ? '[rentiva_contact email_to="zz-recipient@example.test"]' : '[rentiva_contact]');
			case 'block':
				return render_block(
					array(
						'blockName'    => 'mhm-rentiva/contact',
						'attrs'        => $email_to ? array( 'emailTo' => 'zz-recipient@example.test' ) : array(),
						'innerHTML'    => '',
						'innerContent' => array(),
						'innerBlocks'  => array(),
					)
				);
			case 'widget':
				ob_start();
				try {
					WidgetFactory::make(ContactFormWidget::class, array())->render_content();
				} finally {
					$html = (string) ob_get_clean();
				}
				return $html;
		}
		throw new \InvalidArgumentException($surface);
	}

	private function golden_form(string $html): string
	{
		// The recipient token is a random sealed payload: mask its value.
		$html = (string) preg_replace('/(name="recipient_token" value=")[^"]*"/', '$1TOKEN"', $html);
		$html = RenderNormalizer::golden($html);
		$html = (string) preg_replace('/>\s+</', ">\n<", trim($html));
		return (string) preg_replace('/[ \t]+/', ' ', $html) . "\n";
	}
}
