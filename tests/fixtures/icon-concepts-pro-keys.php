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
 * asserts the list equals array_keys( ProIconConcepts::MAP ). Moving or
 * renaming this file turns Pro's CI red; a new Pro concept means a line here.
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
);
