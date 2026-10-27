<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

use MHMRentiva\Admin\Core\LocationContext;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;

/**
 * Drops LocationContext's request cache before every test.
 *
 * The whole suite is one PHP process, so a list cached by one test would be
 * served to the next (registered in phpunit.xml; Pro has its own copy).
 */
final class ResetsLocationContextListener implements TestListener
{
	use TestListenerDefaultImplementation;

	public function startTest(Test $test): void
	{
		if (class_exists(LocationContext::class)) {
			LocationContext::flush();
		}
	}
}
