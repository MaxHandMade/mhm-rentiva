<?php
/**
 * Gate G1 (SettingHasEffectTest): the settings that change nothing in the render today.
 *
 * One row per (surface, tag, canonical key), from docs/superpowers/audits/2026-10-10-elementor-kopru-dilim-3/dead-settings.tsv
 * (status=dead; decision = the recommendation column; my_favorites/show_badges REMOVE by spec 3A §2.1),
 * minus the rows the gate measures as live (see the Task 0 report). Each task of the slice deletes its
 * own rows; a REMOVE row moves to setting-effect-removed.php when its key is removed. Empty and deleted at T10.
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, decision:'APPLY'|'REMOVE'}>
 */

declare(strict_types=1);

return array();
