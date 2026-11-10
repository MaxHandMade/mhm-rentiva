<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Privacy;

use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Privacy\ConsentState;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use WP_UnitTestCase;

/**
 * One answer to "has this customer consented to data processing?".
 *
 * Two user metas carried the answer and no reader asked both: the My Account
 * consent card wrote `mhmrentiva_gdpr_consent_given`, while the booking gate
 * (Lite BookingForm, Pro check_booking_consent) read only
 * `mhmrentiva_data_consent_given` -- which no form in either tree ever sets to
 * '1'. With "data consent required" switched on, no logged-in customer could
 * book, however often they pressed Accept. Found in the customer-eye round
 * (2026-11-10, finding #24) while looking at the card's wording.
 *
 * Both keys stay readable so consents already stored under either one keep
 * counting; withdrawal clears both so neither can resurrect it.
 */
final class ConsentStateTest extends WP_UnitTestCase
{
	private int $user;

	/** @var mixed */
	private $settings_before;

	public function set_up(): void
	{
		parent::set_up();
		$this->settings_before = get_option(SettingsCore::OPTION_NAME, array());
		$this->user = self::factory()->user->create(array( 'role' => 'customer' ));
	}

	public function tear_down(): void
	{
		remove_all_actions('wp_ajax_mhmrentiva_data_export');
		update_option(SettingsCore::OPTION_NAME, $this->settings_before);
		wp_set_current_user(0);
		parent::tear_down();
	}

	public function test_a_customer_with_neither_meta_has_not_consented(): void
	{
		$this->assertFalse(ConsentState::is_given($this->user));
	}

	public function test_the_consent_card_meta_counts(): void
	{
		update_user_meta($this->user, ConsentState::CARD_KEY, '1');
		$this->assertTrue(ConsentState::is_given($this->user));
	}

	public function test_the_registration_meta_counts(): void
	{
		update_user_meta($this->user, ConsentState::REGISTRATION_KEY, '1');
		$this->assertTrue(ConsentState::is_given($this->user));
	}

	public function test_a_registration_refusal_does_not_count(): void
	{
		update_user_meta($this->user, ConsentState::REGISTRATION_KEY, '0');
		$this->assertFalse(ConsentState::is_given($this->user));
	}

	public function test_record_gives_consent_and_clears_a_withdrawal(): void
	{
		update_user_meta($this->user, ConsentState::REGISTRATION_KEY, '0');
		update_user_meta($this->user, 'mhmrentiva_gdpr_consent_withdrawn', '1');

		ConsentState::record($this->user);

		$this->assertTrue(ConsentState::is_given($this->user));
		$this->assertSame('', get_user_meta($this->user, 'mhmrentiva_gdpr_consent_withdrawn', true));
		$this->assertNotSame('', get_user_meta($this->user, 'mhmrentiva_gdpr_consent_date', true));
	}

	public function test_withdraw_clears_consent_stored_under_either_key(): void
	{
		update_user_meta($this->user, ConsentState::CARD_KEY, '1');
		update_user_meta($this->user, ConsentState::REGISTRATION_KEY, '1');

		ConsentState::withdraw($this->user);

		$this->assertFalse(ConsentState::is_given($this->user));
		$this->assertSame('1', get_user_meta($this->user, 'mhmrentiva_gdpr_consent_withdrawn', true));
		$this->assertNotSame('', get_user_meta($this->user, 'mhmrentiva_gdpr_consent_withdrawal_date', true));
	}

	public function test_the_booking_gate_lets_a_customer_who_accepted_the_card_through(): void
	{
		$this->require_data_consent();
		wp_set_current_user($this->user);

		$this->assertArrayHasKey('error', $this->validate_user_access(), 'Control: no consent must still be refused.');

		update_user_meta($this->user, ConsentState::CARD_KEY, '1');
		$this->assertSame(array(), $this->validate_user_access());
	}

	public function test_withdraw_consent_is_offered_only_after_consent(): void
	{
		add_action('wp_ajax_mhmrentiva_data_export', '__return_null');
		wp_set_current_user($this->user);

		$before = AccountRenderer::render_dashboard();
		$this->assertStringContainsString('id="export-data"', $before, 'Control: the privacy section must render.');
		$this->assertStringNotContainsString('id="withdraw-consent"', $before);

		ConsentState::record($this->user);
		$this->assertStringContainsString('id="withdraw-consent"', AccountRenderer::render_dashboard());
	}

	private function require_data_consent(): void
	{
		$settings = (array) get_option(SettingsCore::OPTION_NAME, array());
		$settings['mhmrentiva_customer_data_consent'] = '1';
		update_option(SettingsCore::OPTION_NAME, $settings);
	}

	/** @return array<string, mixed> */
	private function validate_user_access(): array
	{
		$method = new \ReflectionMethod(\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::class, 'validate_user_access');
		$method->setAccessible(true);
		return $method->invoke(null);
	}
}
