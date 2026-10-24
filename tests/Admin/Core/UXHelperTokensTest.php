<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Core;

use MHMRentiva\Admin\Core\Utilities\UXHelper;
use WP_UnitTestCase;

/**
 * Messages carry {key} tokens: GlotPress read "%num_days% days" as the printf
 * placeholder "% d" and flagged every translation where the next letter differed,
 * which blocked the 6.3.0 import. %key% is still replaced for filtered messages.
 *
 * @covers \MHMRentiva\Admin\Core\Utilities\UXHelper::get_warning_message
 * @covers \MHMRentiva\Admin\Core\Utilities\UXHelper::get_user_friendly_error
 */
final class UXHelperTokensTest extends WP_UnitTestCase
{
	public function test_a_brace_token_is_filled(): void
	{
		$this->assertSame(
			"Your driver's license will expire in 5 days.",
			UXHelper::get_warning_message( 'license_expiring', array( 'num_days' => 5 ) )
		);
	}

	public function test_an_error_message_token_is_filled(): void
	{
		$message = UXHelper::get_user_friendly_error( UXHelper::ERROR_TYPE_BOOKING, 'minimum_duration', array( 'hours' => 3 ) );

		$this->assertStringContainsString( '3 hours', $message );
		$this->assertStringNotContainsString( '{hours}', $message );
	}

	public function test_no_message_ships_a_percent_token(): void
	{
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Admin/Core/Utilities/UXHelper.php' );

		$this->assertSame( 0, preg_match_all( "/__\\(\\s*'[^']*%[a-z_]+%/", $source ) );
	}
}
