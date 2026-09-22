<?php
/**
 * The icon concepts Lite and Pro both define, and the glyph both must draw.
 *
 * ui-core's PHP registry is last-write-wins and serves both plugins, so if the
 * two editions ever map one of these differently, whichever registers second
 * silently decides the glyph on BOTH plugins' strips -- one concept, two
 * pictures, no error.
 *
 * 🔴 WHY THIS FILE EXISTS. Pro is private, so Lite's public CI cannot check it
 * out, and a test that compares against the real Pro file skips there -- a
 * Lite change to a shared entry used to pass every Lite check (found by the
 * Codex review on PR #65). This file is the contract both sides can read:
 *
 * - Lite's IconConceptsTwinTest asserts IconConcepts::MAP against it on every
 *   run, with no skip. Changing a shared entry in Lite turns Lite CI red.
 * - Pro's ProIconConceptsTest reads this file from its Lite checkout and
 *   asserts it equals Pro's own shared entries. Editing this file to silence
 *   Lite's test turns Pro CI red instead (nightly, or on demand).
 *
 * Changing a shared glyph is therefore a two-repository change by design:
 * this file, Lite's map and Pro's map move together.
 *
 * @package Mhm_Rentiva
 */

declare(strict_types=1);

return array(
	'bookings' => 'calendar-alt',
	'vehicles' => 'car',
);
