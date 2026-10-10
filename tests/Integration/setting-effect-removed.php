<?php
/**
 * Gate G4 (RemovedSettingsAreGoneTest): settings removed by the slice.
 *
 * A task that removes a REMOVE row's key moves the row here from
 * setting-effect-expected-dead.php, unchanged; the test then checks the (tag, key)
 * is gone from all five layers. Rows stay after T10 deletes the expected-dead file.
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, decision:'REMOVE'}>
 */

declare(strict_types=1);

return array();
