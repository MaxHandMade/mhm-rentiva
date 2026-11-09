<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Privacy\PolicyText;
use MHMRentiva\Admin\Privacy\PrivacyRegistry;
use WP_UnitTestCase;

/**
 * Rentiva's entries in the core privacy tools and the suggested policy text
 * (5a spec T1, T8; acceptance 1).
 *
 * @covers \MHMRentiva\Admin\Privacy\PrivacyRegistry
 * @covers \MHMRentiva\Admin\Privacy\PolicyText
 */
final class PrivacyRegistryTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		// The policy test pretends to be in wp-admin after admin_init; do not
		// leak that into the next test.
		$GLOBALS['current_screen'] = null;
		unset($GLOBALS['wp_actions']['admin_init']);
		parent::tearDown();
	}

	/**
	 * @return string[] Rentiva (Lite) keys in a core filter's result.
	 */
	private function lite_keys(string $filter): array
	{
		$keys = array_keys((array) apply_filters($filter, array()));
		$keys = array_values(array_filter($keys, static fn(string $k): bool => 0 === strpos($k, 'mhm-rentiva-') && 0 !== strpos($k, 'mhm-rentiva-pro-')));
		sort($keys);

		return $keys;
	}

	public function test_exporter_and_eraser_keys_are_exactly_the_planned_sets(): void
	{
		$this->assertSame(
			array('mhm-rentiva-account', 'mhm-rentiva-bookings', 'mhm-rentiva-contact', 'mhm-rentiva-email-log'),
			$this->lite_keys('wp_privacy_personal_data_exporters')
		);
		$this->assertSame(
			array('mhm-rentiva-account', 'mhm-rentiva-app-log', 'mhm-rentiva-bookings', 'mhm-rentiva-contact', 'mhm-rentiva-email-log'),
			$this->lite_keys('wp_privacy_personal_data_erasers')
		);
	}

	public function test_every_entry_has_a_friendly_name_and_a_callable(): void
	{
		foreach (array('wp_privacy_personal_data_exporters' => 'exporter_friendly_name', 'wp_privacy_personal_data_erasers' => 'eraser_friendly_name') as $filter => $name_key) {
			foreach ((array) apply_filters($filter, array()) as $key => $entry) {
				if (0 !== strpos($key, 'mhm-rentiva-')) {
					continue;
				}
				$this->assertNotSame('', (string) ($entry[ $name_key ] ?? ''), $key);
				$this->assertTrue(is_callable($entry['callback'] ?? null), $key);
			}
		}
	}

	public function test_registration_is_idempotent(): void
	{
		PrivacyRegistry::register();
		PrivacyRegistry::register();

		$this->assertCount(4, $this->lite_keys('wp_privacy_personal_data_exporters'));
		$this->assertSame(10, has_action('admin_init', array(PolicyText::class, 'add')));
	}

	public function test_policy_text_is_suggested_with_the_tutorial_class(): void
	{
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		$store = new \ReflectionProperty(\WP_Privacy_Policy_Content::class, 'policy_content');
		$store->setAccessible(true);
		$store->setValue(null, array());

		// Core accepts suggestions only in wp-admin, during or after admin_init.
		set_current_screen('options-privacy');
		$GLOBALS['wp_actions']['admin_init'] = 1;

		PolicyText::add();

		$content = (array) $store->getValue();
		$names   = array_column($content, 'plugin_name');
		$this->assertContains('MHM Rentiva', $names);
		$html = implode('', array_column($content, 'policy_text'));
		$this->assertStringContainsString('privacy-policy-tutorial', $html);
		$this->assertStringContainsString('IP', $html);
	}
}
