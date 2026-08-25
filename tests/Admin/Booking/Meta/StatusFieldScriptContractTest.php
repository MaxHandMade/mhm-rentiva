<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Meta;

use MHMRentiva\Admin\Booking\Meta\BookingEditMetaBox;
use MHMRentiva\Admin\Booking\Meta\ManualBookingMetaBox;
use WP_UnitTestCase;

/**
 * Two booking screens print a status <select> and hand it to a script by id.
 * Both had the same slip: the script reaches for the field's `name`, and the
 * element's `id` is spelled differently, so `$( '#...' )` matches nothing.
 *
 * jQuery says nothing when a selector matches nothing -- every call on the
 * empty set is a no-op that returns successfully -- so neither screen ever
 * raised an error, and thousands of green tests never touched the question.
 * The two failures were different, which is why both need a fence:
 *
 * - Manual booking: `.val()` on the empty set is undefined, and jQuery.param()
 *   encodes that as an empty string, so the handler stored an empty status.
 *   Fenced end to end in ManualBookingStatusContractTest.
 * - Booking edit: the `change` handler that asks the operator to confirm a
 *   status change is bound to an element that does not exist, so the
 *   confirmation never appears. The server reads the field by `name` and so
 *   still gets the right value -- what was lost is the prompt in front of a
 *   money-adjacent action, and the <label for> association with it.
 *
 * These assertions read the shipped script rather than a copy of its selector,
 * because the defect lives in the disagreement between two files. Anything
 * that reads only one of them cannot see it.
 */
final class StatusFieldScriptContractTest extends WP_UnitTestCase
{
	private function script(string $relative_path): string
	{
		$path = MHMRENTIVA_PLUGIN_DIR . $relative_path;
		$this->assertFileExists($path);

		return (string) file_get_contents($path);
	}

	/**
	 * @return list<string>
	 */
	private function ids_the_script_reads(string $source): array
	{
		// '~' delimiter, not '#': the pattern itself contains a literal '#'.
		preg_match_all("~\\\$\\(\\s*'#([A-Za-z0-9_-]+)'\\s*\\)~", $source, $matches);

		return array_values(array_unique($matches[1]));
	}

	public function test_manual_booking_script_reads_the_id_the_form_prints(): void
	{
		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		ob_start();
		ManualBookingMetaBox::render(get_post($booking_id));
		$markup = (string) ob_get_clean();

		$this->assertStringContainsString(
			'id="mhmrentiva_manual_booking_status"',
			$markup
		);

		$source = $this->script('assets/js/admin/manual-booking-meta.js');

		$this->assertStringContainsString(
			"'#mhmrentiva_manual_booking_status'",
			$source,
			'The payload builder must read the id the form actually prints.'
		);
		$this->assertStringNotContainsString(
			"'#mhmrentiva_manual_status'",
			$source,
			'mhmrentiva_manual_status is the field NAME, never an id -- reading it as one is the defect this test exists for.'
		);
	}

	public function test_booking_edit_label_select_and_script_agree_on_one_id(): void
	{
		$booking_id = (int) self::factory()->post->create(array( 'post_type' => 'mhmrentiva_booking' ));

		ob_start();
		BookingEditMetaBox::render(get_post($booking_id));
		$markup = (string) ob_get_clean();

		$this->assertSame(
			1,
			preg_match('#<select id="([A-Za-z0-9_-]+)" name="mhmrentiva_edit_status"#', $markup, $select),
			'The booking edit screen must render a status select.'
		);
		$select_id = $select[1];

		$this->assertSame(
			1,
			preg_match('#<label for="([A-Za-z0-9_-]+)"[^>]*>[^<]*</label>\s*<select id="' . preg_quote($select_id, '#') . '"#s', $markup, $label),
			'The status label must sit directly in front of the status select.'
		);
		$this->assertSame(
			$select_id,
			$label[1],
			'A <label for> pointing at an id nothing prints associates the label with nothing.'
		);

		$source = $this->script('assets/js/admin/booking-edit-meta.js');

		$this->assertContains(
			$select_id,
			$this->ids_the_script_reads($source),
			'The status-change confirmation is bound by id; bound to a missing id it never fires.'
		);
		$this->assertStringNotContainsString(
			"'#mhmrentiva_edit_status'",
			$source,
			'mhmrentiva_edit_status is the field NAME. The server reads it from $_POST; the script must not read it as an id.'
		);
	}
}
