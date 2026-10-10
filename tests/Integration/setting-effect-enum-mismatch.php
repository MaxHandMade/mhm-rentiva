<?php
/**
 * Gate G3 (EnumOptionsMatchSchemaTest): editor options the schema would replace
 * with its default, outside the rows of setting-effect-expected-dead.php.
 *
 * One row per (surface, tag, key, option); the task that aligns the options with
 * the schema deletes its rows. Lite has none on 2026-10-10: all 13 measured
 * mismatches sit on expected-dead rows (my-bookings / my-favorites /
 * payment-history sort and filter).
 *
 * @return list<array{surface:'block'|'elementor', tag:string, key:string, option:string}>
 */

declare(strict_types=1);

return array();
