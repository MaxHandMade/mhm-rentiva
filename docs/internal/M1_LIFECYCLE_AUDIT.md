# M1 Shortcode Lifecycle Audit

**Generated:** 2026-02-14
**Source Version:** 4.9.8
**Context:** M1 Shortcode Engine Audit & Optimization

This document defines the **Shortcode Lifecycle Contract** and documents the compliance status of core shortcodes.

## 1. The Shortcode Lifecycle Contract

All shortcodes MUST extend `MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode` and adhere to the following lifecycle managed by `render()`:

1.  **Normalization:** Attributes are normalized using `shortcode_atts` and `get_default_attributes()`.
2.  **Cache Check:** `get_cached_html()` is called using a versioned transient key.
3.  **Asset Loading:** `enqueue_assets_once()` is called.
    *   De-duplicates assets per request.
    *   Loads global notifications.
    *   Calls concrete `enqueue_assets()`.
4.  **Data Preparation:** `prepare_template_data($atts)` is called.
    *   **Strict Rule:** No HTML generation in this phase. Data only.
5.  **Rendering:** `render_template()` calls `Templates::render()`.
6.  **Caching:** Output is cached via `cache_html()`.

### Registration Contract
*   Managed centrally by `MHMRentiva\Admin\Core\ShortcodeServiceProvider`.
*   Wraps execution with `handle_shortcode_execution` for:
    *   Authentication checks (`requires_auth` config).
    *   Dependency validation.

## 2. Compliance Audit

### `[rentiva_booking_form]`
*   **Status:** ✅ Compliant
*   **Lifecycle:** Does **not** override `render()`.
*   **Asset Loading:** Overrides `enqueue_scripts/styles` for custom file-mtime versioning.
*   **Note:** Exposes `public static function enqueue_assets()` to allow external loading (used by widgets).

### `[rentiva_availability_calendar]`
*   **Status:** ✅ Compliant (Dependency Resolved)
*   **Lifecycle:** Does **not** override `render()`.
*   **Asset Loading:**
    *   Overrides `enqueue_assets()`.
    *   **CRITICAL:** Explicitly calls `BookingForm::enqueue_assets()` (Line 208).
    *   **Resolved:** `ShortcodeServiceProvider` config now explicitly lists `'dependencies' => ['booking_form_module']` (or equivalent) to ensure context validity.

### `[rentiva_vehicles_list]` & `[rentiva_vehicles_grid]`
*   **Status:** ✅ Compliant (Checked via Parity Audit)
*   **Lifecycle:** Does **not** override `render()`.
*   **Parity:** Implements complex `prepare_template_data` handling attribute mapping for Block parity.

## 3. Findings & Recommendations

1.  **[RESOLVED] Hidden Dependency:** `AvailabilityCalendar` depends on `BookingForm` for assets.
    *   **Fix:** `ShortcodeServiceProvider` config updated to declare dependency.
    
2.  **Asset Versioning Strategy:**
    *   `BookingForm` uses `filemtime`.
    *   `AbstractShortcode` uses `MHM_RENTIVA_VERSION`.
    *   **Recommendation:** Elevate `filemtime` strategy to `AbstractShortcode` if performance allows, or standardize on Plugin Version for production.

3.  **Public Asset Loading:**
    *   `BookingForm` making `enqueue_assets` public is a valid pattern for Widgets/Blocks re-using shortcode assets.
    *   **Recommendation:** Formalize this in `AbstractShortcode` interface? (Low priority).

