<?php
/**
 * Known schema-mapped widget controls that do not reach their shortcode (spec §3 T2).
 *
 * One row per control: widget short class name, control id, and a reason that is
 * exactly `layout-class-only` (the value only picks a wrapper class the widget
 * prints itself) or `deferred-dilim-3:<id>` (the fix belongs to slice 3).
 * WidgetContractTest fails on a stale row (control gone, or now working) and on
 * any other reason.
 *
 * @return list<array{widget:string,control:string,reason:string}>
 */

declare(strict_types=1);

return array();
