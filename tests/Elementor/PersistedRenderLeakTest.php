<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Elementor;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\ContactFormWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\MyBookingsWidget;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\PaymentHistoryWidget;
use MHMRentiva\Admin\Services\FavoritesService;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use MHMRentiva\Tests\Support\WidgetFactory;
use WP_UnitTestCase;

/**
 * Audit 2026-10-13 (dilim 3b2b ölçüm §F): Elementor stores two server-side renders
 * of a page that every visitor is later served.
 *
 * 1. DB::save_plain_text() renders each widget's render_plain_content() as the
 *    saving admin and writes it to wp_posts.post_content (search, excerpts, feeds).
 * 2. The markdown-render module caches get_render_content_for_markdown() of the
 *    first requester in _elementor_markdown_cache and serves it to every crawler.
 *
 * Rentiva widgets render the current viewer's bookings, payments, favourites,
 * e-mail and live nonces, so both copies leaked the admin's data. They must add
 * nothing to either copy, while the page itself keeps rendering from _elementor_data.
 */
final class PersistedRenderLeakTest extends WP_UnitTestCase
{
	private const HEADING = 'Plain heading survives';

	private int $admin = 0;

	private int $vehicle = 0;

	private int $booking = 0;

	private string $email = '';

	private int $element_seq = 0;

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; the renders would measure an absence.');
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);

		$this->email = 'leak-admin-' . strtolower(wp_generate_password(6, false, false)) . '@example.test';
		$this->admin = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => $this->email,
			)
		);
		if (is_multisite()) {
			grant_super_admin($this->admin);
		}
		$this->vehicle = ShortcodeFixtures::vehicle();
		$this->booking = ShortcodeFixtures::booking($this->admin, $this->vehicle);
		FavoritesService::add($this->admin, $this->vehicle);

		wp_set_current_user($this->admin);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		parent::tear_down();
	}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	private function widget(string $type, array $settings = array()): array
	{
		return array(
			'id'         => 'p' . ( ++$this->element_seq ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => array() === $settings ? new \stdClass() : $settings,
			'elements'   => array(),
		);
	}

	/**
	 * A builder page saved the way Elementor saves it.
	 *
	 * @param list<array<string,mixed>> $widgets
	 */
	private function page(array $widgets): int
	{
		$id = (int) wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Account page',
			)
		);
		update_post_meta($id, '_elementor_edit_mode', 'builder');
		$tree = array(
			array(
				'id'       => 'c' . ( ++$this->element_seq ),
				'elType'   => 'container',
				'isInner'  => false,
				'settings' => new \stdClass(),
				'elements' => $widgets,
			),
		);
		update_metadata('post', $id, '_elementor_data', wp_slash((string) wp_json_encode($tree)));

		return $id;
	}

	/**
	 * Account and catalog widgets next to a core heading.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function account_and_catalog_widgets(): array
	{
		return array(
			$this->widget('heading', array( 'title' => self::HEADING )),
			$this->widget('rv-my-bookings'),
			$this->widget('rv-payment-history'),
			$this->widget('rv-my-favorites'),
			$this->widget('mhmrentiva_vehicles_grid'),
			$this->widget('rv-contact-form'),
			$this->widget('rv-vehicle-rating', array( 'vehicle_id' => (string) $this->vehicle )),
		);
	}

	/**
	 * Strings that only the admin's own per-viewer render carries.
	 *
	 * @return array<string,string>
	 */
	private function private_markers(): array
	{
		return array(
			'booking number'       => '#' . $this->booking,
			'vehicle of a booking' => get_the_title($this->vehicle),
			'admin e-mail'         => $this->email,
			'data-nonce'           => 'data-nonce',
			'_wpnonce'             => '_wpnonce',
			'name="nonce"'         => 'name="nonce"',
			'rv_contact_nonce'     => 'rv_contact_nonce',
			'rating_nonce'         => 'rating_nonce',
		);
	}

	public function test_save_plain_text_writes_no_viewer_data_to_post_content(): void
	{
		$id = $this->page($this->account_and_catalog_widgets());

		\Elementor\Plugin::$instance->db->save_plain_text($id);

		clean_post_cache($id);
		$content = (string) get_post_field('post_content', $id);
		$found   = array();
		foreach ($this->private_markers() as $what => $marker) {
			if (false !== strpos($content, $marker)) {
				$found[] = $what;
			}
		}
		$this->assertSame(array(), $found, 'post_content carries viewer data.');
		$this->assertStringContainsString(self::HEADING, $content, 'Plain text of non-Rentiva widgets must still be written.');
	}

	public function test_page_render_still_shows_the_widgets_to_the_viewer(): void
	{
		$id = $this->page($this->account_and_catalog_widgets());

		$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id);

		$this->assertStringContainsString(get_the_title($this->vehicle), $html, 'The live render must keep the viewer\'s own data.');
		$this->assertStringContainsString(self::HEADING, $html);
	}

	/**
	 * @return array<string,array{0:class-string<\Elementor\Widget_Base>}>
	 */
	public function account_widgets(): array
	{
		return array(
			'my bookings'     => array( MyBookingsWidget::class ),
			'payment history' => array( PaymentHistoryWidget::class ),
			'contact form'    => array( ContactFormWidget::class ),
		);
	}

	/**
	 * @dataProvider account_widgets
	 * @param class-string<\Elementor\Widget_Base> $class
	 */
	public function test_markdown_render_content_is_empty(string $class): void
	{
		$widget = WidgetFactory::make($class);
		$this->assertTrue(method_exists($widget, 'get_render_content_for_markdown'), 'This Elementor has no markdown render; the test would measure nothing.');

		$method = new \ReflectionMethod($widget, 'get_render_content_for_markdown');
		$method->setAccessible(true);

		$this->assertSame('', $method->invoke($widget));
		$this->assertSame('', $widget->render_markdown());
	}

	/**
	 * @dataProvider account_widgets
	 * @param class-string<\Elementor\Widget_Base> $class
	 */
	public function test_plain_content_render_is_empty(string $class): void
	{
		$widget = WidgetFactory::make($class);

		ob_start();
		$widget->render_plain_content();
		$this->assertSame('', (string) ob_get_clean());
	}
}
