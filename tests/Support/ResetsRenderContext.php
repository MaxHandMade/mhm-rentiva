<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Frontend\RenderContext;

/**
 * Empties RenderContext's REST block stack after every test.
 *
 * A REST callback that throws never reaches `rest_request_after_callbacks`, so
 * the stack would stay populated for the rest of the PHPUnit process (see the
 * RenderContext class note). `use` this trait in any test class that renders a
 * widget or dispatches a block-renderer request; it hooks tear_down() and
 * chains to the parent.
 */
trait ResetsRenderContext
{
	public function tear_down(): void
	{
		RenderContext::reset();
		parent::tear_down();
	}
}
