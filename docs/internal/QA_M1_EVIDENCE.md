# QA Evidence: M1 Validation & Testing

**Date:** 2026-02-14
**Scope:** Dependency Graph & Asset Loading

---

## A1) Registry Dependency Check (CODE TRUTH)
**Status:** PASS
**File:** `src/Admin/Core/ShortcodeServiceProvider.php`
**Evidence:**
```php
'rentiva_availability_calendar' => array(
    'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\AvailabilityCalendar::class,
    'dependencies'  => array('booking_form'), // Declarative dependency
    'requires_auth' => false,
),
```

---

## A2) Hidden Dependency Check (ANTI-PATTERN)
**Status:** FIXED (Redundant call removed)
**File:** `src/Admin/Frontend/Shortcodes/AvailabilityCalendar.php`
**Action:** Removed direct call to `BookingForm::enqueue_assets()` in favor of registry dependency.

**Diff:**
```diff
- // Enqueue Booking Form Assets (Required for modal)
- if (class_exists('\MHMRentiva\Admin\Frontend\Shortcodes\BookingForm')) {
-     \MHMRentiva\Admin\Frontend\Shortcodes\BookingForm::enqueue_assets();
- }
+ // JS and CSS are enqueued above.
+ // Booking Form assets are handled via declarative dependency in ShortcodeServiceProvider.
```

---

## B1) Shortcode Handle Generation Check (CODE TRUTH)
**Status:** PASS
**File:** `src/Admin/Frontend/Shortcodes/Core/AbstractShortcode.php`
**Method:** `get_asset_handle()`

**Logic:**
```php
protected static function get_asset_handle(): string
{
    return 'mhm-rentiva-' . str_replace('_', '-', static::get_shortcode_tag());
}
```

**Verification Example:**
- Input Tag: `rentiva_booking_form`
- Generated Handle: `mhm-rentiva-rentiva-booking-form`
- Result: Deterministik ve tag-derived.

---

## B2) BlockRegistry Handle Parity (CODE TRUTH)
**Status:** PASS
**File:** `src/Blocks/BlockRegistry.php`

**Logic:**
```php
// Use Shortcode Tag driven handle if available to ensure parity with AbstractShortcode
if (isset($config['tag']) && $index === 0) {
    $style_handle = 'mhm-rentiva-' . str_replace('_', '-', $config['tag']);
}
```

**Verification:**
- `booking-form` config: `tag => 'rentiva_booking_form'`
- Derived Handle: `mhm-rentiva-rentiva-booking-form`
- Shortcode Handle (from B1): `mhm-rentiva-rentiva-booking-form`
- Result: **EXACT MATCH** (WordPress deduplication will work).

---

## B3) Tek sayfada “Block + Shortcode” dedup kanıtı (RUNTIME TRUTH)
**Status:** PASS
**Method:** `tests/manual/verify_dedup_b3.php` (Mocked WP Environment)

**Execution Log:**
```
=== M1 Step B3: Runtime Deduplication (Handle Parity Proof) ===

[1] Registering Blocks (BlockRegistry side)...
Block Registered: mhm-rentiva-rentiva-booking-form

[2] Invoking Shortcode Render...
Shortcode Enqueued: mhm-rentiva-rentiva-booking-form

[3] Result Comparison:
Block Handle:     mhm-rentiva-rentiva-booking-form
Shortcode Handle: mhm-rentiva-rentiva-booking-form
PASS: Handles are IDENTICAL (mhm-rentiva-rentiva-booking-form).
Proof: WP Core dedups identical handles.
```

**Conclusion:**
Both Block and Shortcode use the **exact same canonical handle** (`mhm-rentiva-rentiva-booking-form`). WordPress's internal `WP_Dependencies` class guarantees that a handle is enqueued only once per page load.



