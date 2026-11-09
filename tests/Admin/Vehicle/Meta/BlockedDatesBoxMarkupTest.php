<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Vehicle\Meta;

use MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox;
use WP_UnitTestCase;

/**
 * The Blocked Dates box keeps every id blocked-dates.js binds and shows the
 * count only when days are blocked (PR 4 plan Task 6; spec §Sözleşmeler).
 * The restyle changes the stylesheet only.
 *
 * @covers \MHMRentiva\Admin\Vehicle\Meta\BlockedDatesMetaBox::render
 */
final class BlockedDatesBoxMarkupTest extends WP_UnitTestCase
{
	private function box(int $vehicle_id): string
	{
		ob_start();
		BlockedDatesMetaBox::render(get_post($vehicle_id));
		return (string) ob_get_clean();
	}

	private function vehicle(): int
	{
		wp_set_current_user(self::factory()->user->create(array( 'role' => 'administrator' )));
		return (int) self::factory()->post->create(array(
			'post_type'   => 'mhmrentiva_vehicle',
			'post_status' => 'publish',
		));
	}

	public function test_badge_hidden_at_zero_and_counts_otherwise(): void
	{
		$id = $this->vehicle();

		$this->assertMatchesRegularExpression('/id="mhm-blocked-count-badge"\s+style="display:none;"/', $this->box($id));

		update_post_meta($id, '_mhmrentiva_blocked_dates', array( '2099-01-01', '2099-01-02', '2099-01-03' ));
		$html = $this->box($id);
		$this->assertDoesNotMatchRegularExpression('/id="mhm-blocked-count-badge"\s+style="display:none;"/', $html);
		$this->assertStringContainsString('<span id="mhm-blocked-count-num">3</span>', $html);
	}

	public function test_ids_the_script_binds_are_present(): void
	{
		$html = $this->box($this->vehicle());

		foreach (array(
			'mhmrentiva_blocked_dates_picker',
			'mhm-clear-all-blocked',
			'mhm-blocked-dates-chips',
			'mhm-blocked-empty',
			'mhmrentiva_blocked_dates_value',
			'mhmrentiva_blocked_dates_notes_value',
			'mhmrentiva_apply_to_all_nonce',
			'mhmrentiva_remove_from_all_nonce',
			'mhmrentiva_current_vehicle_id',
			'mhm-apply-blocked-to-all',
			'mhm-remove-blocked-from-all',
			'mhm-apply-result',
			'mhm-blocked-count-badge',
			'mhm-blocked-count-num',
		) as $id) {
			$this->assertStringContainsString('id="' . $id . '"', $html, $id);
		}
	}
}
