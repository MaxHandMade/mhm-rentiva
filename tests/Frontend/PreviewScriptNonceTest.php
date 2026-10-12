<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend;

use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Frontend\PreviewSamples;
use MHMRentiva\Frontend\RenderContext;
use MHMRentiva\Tests\Support\RequestSimulator;
use MHMRentiva\Tests\Support\ResetsRenderContext;
use MHMRentiva\Tests\Support\ShortcodeFixtures;
use WP_UnitTestCase;

/**
 * Slice 3B-2a, script layer (Codex P1): in an editor preview every nonce the
 * widgets hand their scripts through wp_localize_script() is the empty string,
 * so a request the asset sends from the preview (booking submit, favourite
 * toggle, account call, ...) fails the server-side nonce check. On the front
 * end the same keys carry real nonces, exactly as before.
 *
 * Localization is read from wp_scripts() after the render. Every Rentiva
 * handle's localized data is cleared before each render (and restored after
 * the test), so a preview render can never be graded on data a front render
 * left behind, and vice versa.
 */
final class PreviewScriptNonceTest extends WP_UnitTestCase
{
	use ResetsRenderContext {
		tear_down as private reset_context_and_tear_down;
	}

	/** @var array<string,mixed> Original `data` of every handle this test cleared. */
	private array $data_backup = array();

	public function set_up(): void
	{
		parent::set_up();
		$this->assertTrue(defined('ELEMENTOR_VERSION'), 'Elementor is not loaded; a preview cannot be entered.');
		RenderContext::reset();
		PreviewSamples::reset();
		add_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		wp_set_current_user(0);
		RequestSimulator::new_request();
	}

	public function tear_down(): void
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode(false);
		remove_filter('mhmrentiva_shortcode_html_cache_enabled', '__return_false', 999);
		foreach ($this->data_backup as $handle => $data) {
			if (isset(wp_scripts()->registered[ $handle ])) {
				if (null === $data) {
					unset(wp_scripts()->registered[ $handle ]->extra['data']);
				} else {
					wp_scripts()->registered[ $handle ]->extra['data'] = $data;
				}
			}
		}
		$this->set_core_scripts_localized(false);
		wp_set_current_user(0);
		RequestSimulator::new_request();
		$this->reset_context_and_tear_down();
	}

	/**
	 * The widgets' shortcodes (the Elementor widgets render these tags).
	 *
	 * @return array<string,array{0:string}>
	 */
	public function tags(): array
	{
		return array(
			'availability'      => array( 'rentiva_availability_calendar' ),
			'booking form'      => array( 'rentiva_booking_form' ),
			'contact'           => array( 'rentiva_contact' ),
			'featured vehicles' => array( 'rentiva_featured_vehicles' ),
			'my bookings'       => array( 'rentiva_my_bookings' ),
			'my favorites'      => array( 'rentiva_my_favorites' ),
			'payment history'   => array( 'rentiva_payment_history' ),
			'search results'    => array( 'rentiva_search_results' ),
			'testimonials'      => array( 'rentiva_testimonials' ),
			'unified search'    => array( 'rentiva_unified_search' ),
			'comparison'        => array( 'rentiva_vehicle_comparison' ),
			'vehicle details'   => array( 'rentiva_vehicle_details' ),
			'rating form'       => array( 'rentiva_vehicle_rating_form' ),
			'vehicles grid'     => array( 'rentiva_vehicles_grid' ),
			'vehicles list'     => array( 'rentiva_vehicles_list' ),
		);
	}

	/**
	 * @dataProvider tags
	 */
	public function test_preview_localizes_empty_nonces_front_keeps_them(string $tag): void
	{
		$this->login_for($tag);

		$front = $this->localized_nonces($tag, false);
		$this->assertNotEmpty($front, "$tag: no nonce localized on the front end; the control proves nothing.");
		foreach ($front as $path => $value) {
			$this->assertNotSame('', $value, "$tag: front nonce `$path` is empty.");
		}

		$preview = $this->localized_nonces($tag, true);
		$this->assertNotEmpty($preview, "$tag: the preview localized no nonce key at all (render did not enqueue).");
		foreach ($preview as $path => $value) {
			$this->assertSame('', $value, "$tag: preview localizes a real nonce at `$path`.");
		}
		// Same keys on both sides: only the values change.
		$this->assertSame(array_keys($front), array_keys($preview), "$tag: the preview changes the localized key set.");
	}

	/**
	 * The booking form, the Codex finding: the exact object submitForm() reads.
	 */
	public function test_booking_form_nonce_is_empty_in_preview_and_real_on_front(): void
	{
		$this->login_for('rentiva_booking_form');

		$front = $this->localized_nonces('rentiva_booking_form', false);
		$this->assertSame(wp_create_nonce('mhmrentiva_booking_form_nonce'), $front['mhmRentivaBookingForm.nonce'] ?? null);

		$preview = $this->localized_nonces('rentiva_booking_form', true);
		$this->assertArrayHasKey('mhmRentivaBookingForm.nonce', $preview);
		$this->assertSame('', $preview['mhmRentivaBookingForm.nonce']);
	}

	/**
	 * mhmRentivaAccount is also localized site-wide by AccountController on
	 * wp_enqueue_scripts (same object the account widgets localize), so the
	 * preview iframe must not carry its real nonces either.
	 */
	public function test_account_controller_localization_follows_the_context(): void
	{
		wp_set_current_user(ShortcodeFixtures::customer());

		$front = $this->collect(static fn () => AccountController::enqueue_assets());
		$this->assertSame(wp_create_nonce('mhmrentiva_account'), $front['mhmRentivaAccount.nonce'] ?? null);
		$this->assertSame(wp_create_nonce('wp_rest'), $front['mhmRentivaAccount.restNonce'] ?? null);
		$this->assertSame(wp_create_nonce('mhmrentiva_upload_receipt'), $front['mhmRentivaAccount.uploadNonce'] ?? null);

		\Elementor\Plugin::$instance->editor->set_edit_mode(true);
		$preview = $this->collect(static fn () => AccountController::enqueue_assets());
		foreach (array( 'nonce', 'restNonce', 'uploadNonce' ) as $key) {
			$this->assertArrayHasKey('mhmRentivaAccount.' . $key, $preview);
			$this->assertSame('', $preview[ 'mhmRentivaAccount.' . $key ], "mhmRentivaAccount.$key");
		}
	}

	public function test_script_nonce_helper(): void
	{
		$this->assertSame(wp_create_nonce('zz_action'), RenderContext::script_nonce('zz_action'));
		\Elementor\Plugin::$instance->editor->set_edit_mode(true);
		$this->assertSame('', RenderContext::script_nonce('zz_action'));
	}

	private function login_for(string $tag): void
	{
		ShortcodeFixtures::vehicle();
		$fixture = ShortcodeFixtures::for_tag($tag);
		wp_set_current_user(null !== $fixture['user'] ? (int) $fixture['user'] : ShortcodeFixtures::customer());
	}

	/**
	 * Render $tag in the given context and return every localized nonce of the
	 * Rentiva handles, as `object.path` => value.
	 *
	 * @return array<string,string>
	 */
	private function localized_nonces(string $tag, bool $preview): array
	{
		\Elementor\Plugin::$instance->editor->set_edit_mode($preview);
		$fixture = ShortcodeFixtures::for_tag($tag);
		$atts    = $fixture['atts'];
		if ('rentiva_contact' === $tag) {
			$atts['email_to'] = 'zz-recipient@example.test';
		}

		return $this->collect(
			static function () use ($tag, $atts): void {
				$pairs = '';
				foreach ($atts as $key => $value) {
					$pairs .= sprintf(' %s="%s"', $key, esc_attr((string) $value));
				}
				do_shortcode('[' . $tag . $pairs . ']');
			}
		);
	}

	/**
	 * Fresh request state, run $render, read the nonces it localized.
	 *
	 * @return array<string,string>
	 */
	private function collect(callable $render): array
	{
		RequestSimulator::new_request();
		\MHMRentiva\Admin\Frontend\Shortcodes\FeaturedVehicles::reset_layout_enqueued_for_tests();
		$this->set_core_scripts_localized(false);
		foreach (wp_scripts()->registered as $handle => $dep) {
			if (! preg_match('/^(mhm|rentiva)/', (string) $handle)) {
				continue;
			}
			if (! array_key_exists($handle, $this->data_backup)) {
				$this->data_backup[ $handle ] = $dep->extra['data'] ?? null;
			}
			unset($dep->extra['data']);
		}

		$render();

		$nonces = array();
		foreach (wp_scripts()->registered as $handle => $dep) {
			if (! preg_match('/^(mhm|rentiva)/', (string) $handle) || empty($dep->extra['data'])) {
				continue;
			}
			if (! array_key_exists($handle, $this->data_backup)) {
				$this->data_backup[ $handle ] = null;
			}
			foreach (preg_split('/\n/', (string) $dep->extra['data']) as $line) {
				if (! preg_match('/^var\s+([A-Za-z0-9_$]+)\s*=\s*(.*);$/s', trim($line), $m)) {
					continue;
				}
				$decoded = json_decode($m[2], true);
				if (is_array($decoded)) {
					$this->walk($decoded, $m[1], $nonces);
				}
			}
		}
		ksort($nonces);
		return $nonces;
	}

	/**
	 * @param array<mixed>         $data
	 * @param array<string,string> $out
	 */
	private function walk(array $data, string $path, array &$out): void
	{
		foreach ($data as $key => $value) {
			$here = $path . '.' . $key;
			if (is_array($value)) {
				$this->walk($value, $here, $out);
			} elseif (is_string($key) && preg_match('/nonce/i', $key)) {
				$out[ $here ] = (string) $value;
			}
		}
	}

	private function set_core_scripts_localized(bool $value): void
	{
		$prop = new \ReflectionProperty(\MHMRentiva\Admin\Core\AssetManager::class, 'core_scripts_localized');
		$prop->setAccessible(true);
		$prop->setValue(null, $value);
	}
}
