<?php
/**
 * The NAMES of every icon concept the Pro edition defines -- no glyphs.
 *
 * Lite's CI cannot see Pro, so without this list a concept Lite adds that Pro
 * already defines would become shared without appearing in
 * icon-concepts-shared-with-pro.php, and no Lite check would notice until
 * Pro's next CI run. IconConceptsTwinTest reads this list on every Lite run.
 *
 * 🔴 Pro's ProIconConceptsTest reads THIS PATH from its Lite checkout and
 * fails when a concept Pro defines is missing here. Moving or renaming this
 * file turns Pro's CI red. Order for a NEW Pro concept: add its name here
 * first (a name Pro does not define yet is tolerated), then the Pro change.
 * Removing one: Pro first, then drop the name here.
 *
 * A concept entering or leaving the SHARED set (both editions define it) is a
 * two-repository change: this list, icon-concepts-shared-with-pro.php, Lite's
 * map and Pro's map move together, Lite first. Pro's CI is red on its
 * contract test between the Lite merge and the Pro merge -- merge order, not
 * drift (see the LITE_REF note in Pro's testing.yml).
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

return array(
	'bookings',
	'vehicles',
	'occupancy',
	'routes',
	'loyalty',
	'refunds',
	'latest',
	'completed',
	'balance',
	'iban',
	'reliability',
);
