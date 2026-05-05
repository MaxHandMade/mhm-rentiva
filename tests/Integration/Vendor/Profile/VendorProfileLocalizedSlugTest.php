<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorProfileUrlBase;

/**
 * @group vendor-profile
 * @group vendor-i18n
 */
final class VendorProfileLocalizedSlugTest extends \WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhm_rentiva_vendor_url_base_cached');
		remove_all_filters('mhm_rentiva_vendor_profile_url_base');
		parent::tearDown();
	}

	public function test_default_base_is_vendor_in_en_locale(): void
	{
		$this->assertSame('vendor', VendorProfileUrlBase::resolve());
	}

	public function test_filter_override_is_honored(): void
	{
		add_filter('mhm_rentiva_vendor_profile_url_base', static fn() => 'partner');

		$this->assertSame('partner', VendorProfileUrlBase::resolve());
	}

	public function test_filter_returning_empty_falls_back_to_vendor(): void
	{
		add_filter('mhm_rentiva_vendor_profile_url_base', static fn() => '');

		$this->assertSame('vendor', VendorProfileUrlBase::resolve());
	}

	public function test_filter_non_ascii_value_is_sanitized(): void
	{
		add_filter('mhm_rentiva_vendor_profile_url_base', static fn() => 'Bäyi Şirketler');

		$this->assertSame('bayi-sirketler', VendorProfileUrlBase::resolve());
	}

	/**
	 * Regression for reviewer Bulgu 1: a filter returning a value with
	 * percent-encoded characters must be urldecoded before sanitize_title so
	 * the resulting slug matches Apache/Nginx-decoded request URIs.
	 */
	public function test_filter_url_encoded_value_is_decoded_then_sanitized(): void
	{
		add_filter('mhm_rentiva_vendor_profile_url_base', static fn() => 'foo%2fbar');

		$this->assertSame('foo-bar', VendorProfileUrlBase::resolve());
	}

	/**
	 * Regression for reviewer Bulgu 2: url_for_slug('') must return '' instead
	 * of a malformed '/{base}//' URL. Protects callers (templates, widgets,
	 * 301 redirect target builder) from emitting broken hrefs.
	 */
	public function test_url_for_slug_returns_empty_string_for_empty_slug(): void
	{
		$this->assertSame('', VendorProfileUrlBase::url_for_slug(''));
	}

	public function test_check_for_locale_change_triggers_flush_on_first_resolve(): void
	{
		delete_option(VendorProfileUrlBase::CACHE_OPTION);
		$flushed = false;
		add_action(
			'mhm_rentiva_vendor_url_base_changed',
			static function () use (&$flushed): void {
				$flushed = true;
			}
		);

		VendorProfileUrlBase::check_for_locale_change();

		$this->assertTrue($flushed);
		$this->assertSame('vendor', get_option(VendorProfileUrlBase::CACHE_OPTION));
	}

	public function test_check_does_nothing_when_base_unchanged(): void
	{
		update_option(VendorProfileUrlBase::CACHE_OPTION, 'vendor');
		$fired = 0;
		add_action(
			'mhm_rentiva_vendor_url_base_changed',
			static function () use (&$fired): void {
				$fired++;
			}
		);

		VendorProfileUrlBase::check_for_locale_change();

		$this->assertSame(0, $fired);
	}

	public function test_check_fires_when_filter_changes_base(): void
	{
		update_option(VendorProfileUrlBase::CACHE_OPTION, 'vendor');
		add_filter('mhm_rentiva_vendor_profile_url_base', static fn() => 'partner');
		$fired = 0;
		add_action(
			'mhm_rentiva_vendor_url_base_changed',
			static function () use (&$fired): void {
				$fired++;
			}
		);

		VendorProfileUrlBase::check_for_locale_change();

		$this->assertSame(1, $fired);
		$this->assertSame('partner', get_option(VendorProfileUrlBase::CACHE_OPTION));
	}
}
