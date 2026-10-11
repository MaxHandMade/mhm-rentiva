<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration;

use MHMRentiva\Tests\Support\RemovedSettingsGate;
use WP_UnitTestCase;

/**
 * Gate G4 for Lite's blocks and widgets (the gate itself: RemovedSettingsGate).
 */
final class RemovedSettingsAreGoneTest extends WP_UnitTestCase
{
	use RemovedSettingsGate;

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
