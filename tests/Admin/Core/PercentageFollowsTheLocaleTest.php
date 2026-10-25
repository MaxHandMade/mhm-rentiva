<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\CurrencyHelper;
use MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator;
use MHMRentiva\Tests\Support\WooCommerceOptionSandbox;
use WP_Ajax_UnitTestCase;

/**
 * A percentage is written through the catalogue, never as `rate . '%'`.
 *
 * The deposit hint beside a vehicle's deposit field had a hard-coded "%10" on
 * every site. (The booking form's own tax line, which once glued "20%" onto a
 * Turkish label, is gone: tax is shown through WooCommerce since PR 0.)
 *
 * @covers \MHMRentiva\Admin\Core\CurrencyHelper::format_percentage
 * @covers \MHMRentiva\Admin\Vehicle\Deposit\DepositCalculator::format_deposit_display
 */
final class PercentageFollowsTheLocaleTest extends WP_Ajax_UnitTestCase
{
	use WooCommerceOptionSandbox;

	/** @var string */
	protected $_last_response;

	/** The catalogue's Turkish translation of the format. */
	private const TR_FORMAT = '%%%s';

	public function tearDown(): void
	{
		remove_all_filters('gettext_with_context');
		delete_option('mhmrentiva_settings');
		$this->restore_sandboxed_options();
		parent::tearDown();
	}

	private function turkish(): void
	{
		add_filter(
			'gettext_with_context',
			static function ($translation, $text, $context, $domain) {
				return 'mhm-rentiva' === $domain && '%s%%' === $text && str_starts_with((string) $context, 'A percentage')
					? self::TR_FORMAT
					: $translation;
			},
			10,
			4
		);
	}

	public function test_english_puts_the_sign_after_the_number(): void
	{
		$this->assertSame('20%', CurrencyHelper::format_percentage(20.0));
	}

	public function test_turkish_puts_the_sign_before_the_number(): void
	{
		$this->turkish();

		$this->assertSame('%20', CurrencyHelper::format_percentage(20.0));
	}

	public function test_a_fraction_keeps_its_digits_in_the_stores_decimal_format(): void
	{
		$this->sandbox_option('woocommerce_price_decimal_sep', ',');

		$this->assertSame('12,5%', CurrencyHelper::format_percentage(12.5));
		$this->assertSame('8,25%', CurrencyHelper::format_percentage(8.25));
	}

	public function test_the_deposit_hint_follows_the_locale(): void
	{
		$this->assertSame('10%', DepositCalculator::format_deposit_display('10'));

		$this->turkish();
		$this->assertSame('%10', DepositCalculator::format_deposit_display('10'));
	}
}
