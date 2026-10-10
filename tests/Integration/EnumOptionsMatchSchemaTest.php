<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Tests\Support\EnumOptionsGate;
use MHMRentiva\Tests\Support\SettingProbe;
use WP_UnitTestCase;

/**
 * Gate G3 for Lite's blocks and widgets (the gate itself: EnumOptionsGate).
 */
final class EnumOptionsMatchSchemaTest extends WP_UnitTestCase
{
	use EnumOptionsGate;

	public function test_the_parse_finds_known_options(): void
	{
		// Positive control: a parse that found nothing would pass everything.
		$this->assertSame(array( 'all', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled' ), SettingProbe::editor_options('my-bookings')['filterStatus'] ?? null);
		$options = SettingProbe::data_controls('SearchResultsWidget')['layout']['options'] ?? array();
		$this->assertSame(array( 'grid', 'list', 'compact' ), array_map('strval', array_keys((array) $options)));
	}

	protected function gate_blocks(): ?array
	{
		return null;
	}

	protected function gate_widgets(): ?array
	{
		return null;
	}

	protected function gate_dir(): string
	{
		return __DIR__;
	}
}
