<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_UnitTestCase;

/**
 * The new-customer card offers an optional country and state, from
 * WooCommerce (spec K6, canvas Yon-Yeni-Rezervasyon v122). The store base
 * country is preselected; the state starts empty, so a value the admin never
 * chose is not saved.
 */
final class ManualBookingLocationFieldsTest extends WP_UnitTestCase
{
	use WooCommerceOptionSandbox;

	public function set_up(): void
	{
		parent::set_up();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		// The suite's WooCommerce default is US:CA; the dev and live stores are Turkish.
		$this->sandbox_option('woocommerce_default_country', 'TR:TR34');
		$this->sandbox_option('woocommerce_allowed_countries', 'all');
	}

	public function tear_down(): void
	{
		$this->restore_sandboxed_options();
		wp_dequeue_script('mhm-rentiva-manual-booking-meta');
		wp_deregister_script('mhm-rentiva-manual-booking-meta');
		unset($GLOBALS['post_type']);
		parent::tear_down();
	}

	private function html(): string
	{
		$post = self::factory()->post->create_and_get(array( 'post_type' => 'mhmrentiva_booking', 'post_status' => 'auto-draft' ));
		ob_start();
		ManualBookingMetaBox::render($post);
		return (string) ob_get_clean();
	}

	public function test_location_data_follows_woocommerce(): void
	{
		$data = ManualBookingMetaBox::location_data();

		$this->assertSame('TR', $data['base']);
		$this->assertCount(count(WC()->countries->get_allowed_countries()), $data['countries']);
		$this->assertSame('İstanbul', $data['states']['TR']['TR34']);
		$this->assertArrayHasKey('DE', $data['states']);
		$this->assertArrayNotHasKey('GB', $data['states'], 'GB has no state list: free text.');
		$this->assertContains('FR', $data['noState'], 'FR has an empty list: no state field.');
		$this->assertNotContains('GB', $data['noState']);
		$this->assertSame(array( 'state', 'region', 'regionPlaceholder', 'selectState' ), array_keys($data['labels']));
	}

	public function test_a_state_the_country_locale_hides_is_no_field(): void
	{
		// Checkout hides the state where the country locale says so
		// (address-i18n.js), list or not. WooCommerce's own data has three
		// such countries with no list at all: Bosnia, Botswana, Cyprus.
		$data = ManualBookingMetaBox::location_data();
		foreach (array( 'BA', 'BW', 'CY' ) as $code) {
			$this->assertContains($code, $data['noState'], $code . ': locale hides the state.');
			$this->assertArrayNotHasKey($code, $data['states']);
		}

		// A store filter that hides a country's listed states hides the field too.
		$hide_tr = static function (array $locale): array {
			$locale['TR']['state']['hidden'] = true;
			return $locale;
		};
		add_filter('woocommerce_get_country_locale', $hide_tr);
		$this->reset_country_locale();
		try {
			$data = ManualBookingMetaBox::location_data();
		} finally {
			remove_filter('woocommerce_get_country_locale', $hide_tr);
			$this->reset_country_locale();
		}
		$this->assertContains('TR', $data['noState']);
		$this->assertArrayNotHasKey('TR', $data['states']);
	}

	/**
	 * WC_Countries caches the merged locale on the instance (public $locale).
	 */
	private function reset_country_locale(): void
	{
		WC()->countries->locale = array();
	}

	public function test_new_customer_card_renders_country_and_state(): void
	{
		$html = $this->html();

		$this->assertStringContainsString('id="mhmrentiva_new_customer_country"', $html);
		$this->assertMatchesRegularExpression('/<option value="TR" selected/', $html);
		$this->assertMatchesRegularExpression('/<select[^>]*id="mhmrentiva_new_customer_state"/', $html);
		$this->assertStringContainsString('value="TR34"', $html);
		$this->assertDoesNotMatchRegularExpression('/<option value="TR\d+" selected/', $html, 'No state is preselected.');
		$this->assertStringContainsString('<span id="mhmrentiva_new_customer_state_label">State</span>', $html);
		$this->assertSame(2, substr_count($html, 'class="mhm-field-optional"'));
		$this->assertDoesNotMatchRegularExpression('/id="mhmrentiva_new_customer_(country|state)"[^>]*required/', $html);
	}

	public function test_country_names_keep_their_entities_once_encoded(): void
	{
		$html = $this->html();

		// countries.php stores "Cura&ccedil;ao"; the browser must show "Curaçao".
		$this->assertStringContainsString('>Cura&ccedil;ao</option>', $html);
		$this->assertStringNotContainsString('&amp;ccedil;', $html);
	}

	public function test_state_table_is_handed_over_inline(): void
	{
		$GLOBALS['post_type'] = 'mhmrentiva_booking';
		ManualBookingMetaBox::enqueue_scripts('post-new.php');

		$before = implode('', (array) wp_scripts()->get_data('mhm-rentiva-manual-booking-meta', 'before'));
		$this->assertStringContainsString('window.mhmManualBookingLocations', $before);
		$this->assertStringContainsString('"TR34"', $before);
	}
}
