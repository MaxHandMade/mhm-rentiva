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

	public function test_the_reminder_goes_out_with_the_screens_default_body(): void
	{
		$ctx  = array( 'booking' => array( 'order_id' => 42 ), 'customer' => array( 'name' => 'Ali', 'salutation_name' => 'Ali' ), 'site' => array( 'name' => 'S' ) );
		$html = Templates::render_body('booking_reminder_customer', $ctx);

		$expected = Templates::replace_placeholders(EmailSettings::get_default_booking_reminder_body(), $ctx);
		$this->assertStringContainsString(trim(wp_kses_post($expected)) === '' ? '__never__' : 'Ali', $html);
		$this->assertStringContainsString(mb_substr(wp_strip_all_tags($expected), 0, 20), wp_strip_all_tags($html));
	}
}
