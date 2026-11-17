<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Booking\Helpers;

use MHMRentiva\Admin\Booking\Helpers\Cache;
use MHMRentiva\Admin\Booking\Helpers\Util;
use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_UnitTestCase;

/**
 * The availability answer is cached per vehicle and window, not per language.
 * Its message was cached already translated, so on a multilingual site the
 * first visitor's language reached everyone after them until the entry
 * expired (a Turkish "already booked" on the English page, 2026-11-17).
 * The message must be written in the language of the request that reads it.
 *
 * @covers \MHMRentiva\Admin\Booking\Helpers\Util::check_availability
 * @covers \MHMRentiva\Admin\Booking\Helpers\Cache
 */
final class AvailabilityCacheLanguageTest extends WP_UnitTestCase
{
	private const BOOKED    = 'This vehicle is already booked for the selected dates. Please choose different dates or select another vehicle.';
	private const AVAILABLE = '✅ Great! This vehicle is available for your selected dates.';

	private bool $turkish = false;

	public function setUp(): void
	{
		parent::setUp();
		Cache::clearAll();

		// A second language for Rentiva's own strings, switched per request.
		add_filter(
			'gettext',
			function (string $translation, string $text, string $domain): string {
				if ($this->turkish && 'mhm-rentiva' === $domain && in_array($text, array( self::BOOKED, self::AVAILABLE ), true)) {
					return 'TR: ' . $text;
				}
				return $translation;
			},
			10,
			3
		);
	}

	public function tearDown(): void
	{
		Cache::clearAll();
		parent::tearDown();
	}

	private function vehicle(): int
	{
		$vehicle_id = self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
		update_post_meta($vehicle_id, '_mhmrentiva_price_per_day', '1000');

		return $vehicle_id;
	}

	private function check(int $vehicle_id): array
	{
		return Util::check_availability($vehicle_id, '2027-03-08', '10:00', '2027-03-11', '10:00');
	}

	public function test_a_cached_booked_answer_is_worded_in_the_readers_language(): void
	{
		$vehicle_id = $this->vehicle();
		BlockedDatesMetaBox::toggle_blocked_date($vehicle_id, '2027-03-09');

		$this->turkish = true;
		$first         = $this->check($vehicle_id);
		$this->assertFalse($first['ok']);
		$this->assertSame('TR: ' . self::BOOKED, $first['message'], 'precondition: the first reader gets their own language');

		$this->turkish = false;
		$second        = $this->check($vehicle_id);

		$this->assertFalse($second['ok']);
		$this->assertSame('unavailable', $second['code']);
		$this->assertSame(self::BOOKED, $second['message']);
	}

	public function test_a_cached_available_answer_is_worded_in_the_readers_language(): void
	{
		$vehicle_id = $this->vehicle();

		$this->turkish = true;
		$first         = $this->check($vehicle_id);
		$this->assertTrue($first['ok'], (string) wp_json_encode($first));

		$this->turkish = false;
		$second        = $this->check($vehicle_id);

		$this->assertTrue($second['ok']);
		$this->assertSame(self::AVAILABLE, $second['message']);
		$this->assertEqualsWithDelta(3000.0, (float) $second['total_price'], 0.001, 'the cached figures are still served');
	}

	public function test_an_entry_cached_with_a_translated_message_is_reworded_on_read(): void
	{
		// An entry written before this fix carries its message; it lives until
		// its TTL, so the read side must not trust it.
		$vehicle_id = $this->vehicle();
		BlockedDatesMetaBox::toggle_blocked_date($vehicle_id, '2027-03-09');
		$this->check($vehicle_id);

		$precheck = new \ReflectionMethod(Util::class, 'availability_precheck');
		$precheck->setAccessible(true);
		$window = $precheck->invoke(null, $vehicle_id, '2027-03-08', '10:00', '2027-03-11', '10:00')['window'];
		Cache::setAvailability($vehicle_id, $window['start_ts'], $window['end_ts'], array(
			'ok'      => false,
			'code'    => 'unavailable',
			'message' => 'TR: ' . self::BOOKED,
		));

		$this->assertSame(self::BOOKED, $this->check($vehicle_id)['message']);
	}
}
