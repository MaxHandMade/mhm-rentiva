<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_Ajax_UnitTestCase;

/**
 * Blocked-day notes survive every write path (backslash class, 2026-11-08).
 *
 * The notes are stored as a JSON string. wp_json_encode() writes "ü" as
 * "\u00fc" and a quote as "\"", and update_post_meta() unslashes its value --
 * so an unslashed JSON string lost every backslash: "günü" was stored as
 * "gu00fcnu00fc", and one note with a quote made the whole map undecodable
 * (every note of the vehicle gone). Measured on dev before the fix.
 */
final class BlockedDatesNotesTest extends WP_Ajax_UnitTestCase
{
	private const NOTE = 'Bakım günü — "fren" kontrolü, C:\servis';

	/** @var string */
	protected $_last_response;

	private int $source;

	private int $other;

	public function setUp(): void
	{
		parent::setUp();
		BlockedDatesMetaBox::register();
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		$this->source = $this->vehicle();
		$this->other  = $this->vehicle();
	}

	public function tearDown(): void
	{
		$_POST = array();
		parent::tearDown();
	}

	private function vehicle(): int
	{
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
	}

	private function dispatch(string $action): void
	{
		try {
			$this->_handleAjax($action);
		} catch (\WPAjaxDieContinueException $e) {
			unset($e);
		}
		$this->_last_response = '';
	}

	/** A real request arrives slashed. */
	private function post(array $fields): void
	{
		$_POST = wp_slash($fields);
	}

	public function test_saving_the_vehicle_keeps_the_note(): void
	{
		$this->post(array(
			'mhmrentiva_blocked_dates_nonce'  => wp_create_nonce('mhmrentiva_blocked_dates_save'),
			'_mhmrentiva_blocked_dates'       => '["2027-01-04","2027-01-05"]',
			'_mhmrentiva_blocked_dates_notes' => wp_json_encode(array( '2027-01-04' => self::NOTE, '2027-01-05' => 'ikinci not' )),
		));

		BlockedDatesMetaBox::save($this->source);

		$this->assertSame(
			array( '2027-01-04' => self::NOTE, '2027-01-05' => 'ikinci not' ),
			BlockedDatesMetaBox::get_blocked_notes($this->source)
		);
	}

	public function test_apply_to_all_keeps_the_note_on_the_source_and_the_targets(): void
	{
		$this->post(array(
			'nonce'      => wp_create_nonce('mhmrentiva_apply_blocked_to_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04"]',
			'notes'      => wp_json_encode(array( '2027-01-04' => self::NOTE )),
		));

		$this->dispatch('mhmrentiva_apply_blocked_dates_to_all');

		$this->assertSame(array( '2027-01-04' => self::NOTE ), BlockedDatesMetaBox::get_blocked_notes($this->source));
		$this->assertSame(array( '2027-01-04' => self::NOTE ), BlockedDatesMetaBox::get_blocked_notes($this->other));
	}

	public function test_remove_from_all_keeps_the_remaining_notes(): void
	{
		// The target already holds two well-stored notes; removing one day
		// rewrites the map, which must not strip the other one.
		update_post_meta($this->other, '_mhmrentiva_blocked_dates', '["2027-01-04","2027-01-05"]');
		update_post_meta($this->other, '_mhmrentiva_blocked_dates_notes', wp_slash(wp_json_encode(array( '2027-01-04' => 'gider', '2027-01-05' => self::NOTE ))));
		$this->post(array(
			'nonce'      => wp_create_nonce('mhmrentiva_remove_blocked_from_all'),
			'vehicle_id' => (string) $this->source,
			'dates'      => '["2027-01-04"]',
		));

		$this->dispatch('mhmrentiva_remove_blocked_dates_from_all');

		$this->assertSame(array( '2027-01-05' => self::NOTE ), BlockedDatesMetaBox::get_blocked_notes($this->other));
	}
}
