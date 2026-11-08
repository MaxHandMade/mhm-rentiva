<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Emails;

use MHMRentiva\Admin\Emails\Core\Templates;
use MHMRentiva\Admin\Emails\Templates\BookingNotifications;
use MHMRentiva\Admin\Settings\Groups\EmailSettings;
use WP_UnitTestCase;

/**
 * PR 3b-3 R3: "the Settings template" is what the Settings screen shows, and
 * that is what goes out. The screen used to swap a saved body for its default
 * when the body lacked a marker ('<table', 'background:' ...), while the
 * sender used the saved body; and the reminder's screen default was not the
 * body the sender fell back to (it used the .html.php file).
 */
final class SettingsTemplateParityTest extends WP_UnitTestCase
{
	public function tearDown(): void
	{
		delete_option('mhmrentiva_booking_created_body');
		parent::tearDown();
	}

	public function test_the_screen_shows_a_simple_saved_body_as_saved(): void
	{
		update_option('mhmrentiva_booking_created_body', '<p>Kısa gövde {booking_id}</p>');

		ob_start();
		BookingNotifications::render();
		$screen = (string) ob_get_clean();

		$this->assertStringContainsString(esc_textarea('<p>Kısa gövde {booking_id}</p>'), $screen);
	}

	/**
	 * Codex #178 I2: with no saved subject the screen showed one default and
	 * the sender used another ("Booking Confirmed: #42" vs "Booking #42
	 * Confirmed - Site"). Both read EmailSettings::default_subject() now.
	 *
	 * @dataProvider screen_subjects
	 */
	public function test_an_unsaved_subject_goes_out_as_the_screen_shows_it(string $key): void
	{
		$ctx = array( 'booking' => array( 'order_id' => 42 ), 'customer' => array( 'name' => 'Ali' ), 'site' => array( 'name' => 'Site' ) );

		$default = EmailSettings::default_subject($key);
		$this->assertNotNull($default);
		$this->assertNotSame('', trim($default));
		$this->assertSame(Templates::replace_placeholders($default, $ctx), Templates::compile_subject($key, $ctx));
	}

	/** @return array<string, array{0: string}> */
	public static function screen_subjects(): array
	{
		$keys = array( 'booking_created_customer', 'booking_status_changed_customer', 'booking_created_admin', 'auto_cancel', 'booking_cancelled', 'booking_reminder_customer', 'welcome_customer', 'refund_customer', 'refund_admin' );

		return array_combine($keys, array_map(static fn ($k) => array( $k ), $keys));
	}

	public function test_the_screen_shows_the_shared_default_subject(): void
	{
		ob_start();
		BookingNotifications::render();
		$screen = (string) ob_get_clean();

		$this->assertStringContainsString('value="' . esc_attr(EmailSettings::default_subject('booking_created_customer')) . '"', $screen);
	}

	public function test_the_reminder_goes_out_with_the_screens_default_body(): void
	{
		$ctx  = array( 'booking' => array( 'order_id' => 42 ), 'customer' => array( 'name' => 'Ali', 'salutation_name' => 'Ali' ), 'site' => array( 'name' => 'S' ) );
		$html = Templates::render_body('booking_reminder_customer', $ctx);

		$expected = Templates::replace_placeholders(EmailSettings::get_default_booking_reminder_body(), $ctx);
		$this->assertStringContainsString(trim(wp_kses_post($expected)) === '' ? '__never__' : 'Ali', $html);
		$this->assertStringContainsString(mb_substr(wp_strip_all_tags($expected), 0, 20), wp_strip_all_tags($html));
	}
}
