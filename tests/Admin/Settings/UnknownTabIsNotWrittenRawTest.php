<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Settings\Core\SettingsSanitizer;
use WP_UnitTestCase;

/**
 * A tab name the sanitizer does not recognise must not write the raw request.
 *
 * `sanitize()` dispatches on `current_active_tab`, and the fallback arm returned
 * `$input` unchanged. Every declared bound, enum and text sanitizer in the
 * plugin lives inside the named arms, so a value the match does not recognise —
 * a typo, a renamed tab, a field a caller invented — bypassed all of them at
 * once and was merged straight into `mhmrentiva_settings`.
 *
 * `register_setting`'s nonce and `manage_options` limit who can reach it, so
 * this is not a privilege hole. It is still the one write path where "the
 * sanitize callback did nothing" is the documented behaviour, and that is not a
 * shape worth defending to a reviewer.
 *
 * The add-on's own tabs still work: they are dispatched by the filter below the
 * match, and an extension-owned tab with no registered extension is already
 * refused earlier in the method.
 *
 * @covers \MHMRentiva\Admin\Settings\Core\SettingsSanitizer::sanitize
 */
final class UnknownTabIsNotWrittenRawTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option( 'mhmrentiva_settings' );
		parent::tearDown();
	}

	public function test_an_unknown_tab_does_not_write_arbitrary_keys(): void
	{
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'not_a_real_tab',
				'evil_key'           => '<script>alert(1)</script>',
				'another_key'        => array( 'nested' => 'value' ),
			)
		);

		$this->assertArrayNotHasKey(
			'evil_key',
			$result,
			'An unrecognised tab wrote a key straight from the request into the settings.'
		);
		$this->assertArrayNotHasKey( 'another_key', $result );
	}

	/**
	 * And it must not destroy what is already stored either.
	 */
	public function test_an_unknown_tab_preserves_the_stored_settings(): void
	{
		update_option( 'mhmrentiva_settings', array( 'mhmrentiva_cache_enabled' => '0' ) );

		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab' => 'not_a_real_tab',
				'evil_key'           => 'x',
			)
		);

		$this->assertSame( '0', $result['mhmrentiva_cache_enabled'] );
	}

	/**
	 * A recognised tab still saves normally — the fallback change must not
	 * narrow the real ones.
	 */
	public function test_a_known_tab_still_saves(): void
	{
		$result = SettingsSanitizer::sanitize(
			array(
				'current_active_tab'     => 'system',
				'mhmrentiva_log_level'  => 'debug',
			)
		);

		$this->assertSame( 'debug', $result['mhmrentiva_log_level'] );
	}
}
