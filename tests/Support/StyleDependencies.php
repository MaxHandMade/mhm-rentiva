<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Support;

/**
 * Whether a registered stylesheet pulls another in, directly or further down
 * its dependency chain (PR S: every customer date surface loads the
 * shared rvs datepicker skin).
 */
trait StyleDependencies
{
	private static function style_needs(string $handle, string $needle): bool
	{
		$seen  = array();
		$stack = array( $handle );
		while (array() !== $stack) {
			$current = (string) array_pop($stack);
			if (isset($seen[ $current ])) {
				continue;
			}
			$seen[ $current ] = true;
			foreach ( (array) ( wp_styles()->registered[ $current ]->deps ?? array() ) as $dep) {
				if ($needle === $dep) {
					return true;
				}
				$stack[] = (string) $dep;
			}
		}
		return false;
	}
}
