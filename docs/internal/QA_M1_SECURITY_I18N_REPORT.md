# QA Report: M1 Security & i18n Regression Guard

**Date:** 2026-02-14
**Plugin Version:** v1.3.5 (Snapshot)
**Scope:** Asset versioning, canonical handles, dependency graph changes.

---

## 7.1 Asset/Handle Change Safety Check
**Goal:** Verify canonical handles and `filemtime` versioning do not introduce security or i18n regressions.

| Check | Status | Notes |
| :--- | :--- | :--- |
| **URL Integrity** | **PASS** | `AbstractShortcode::get_asset_version` correctly uses `filemtime` for cache busting. |
| **Handle Dedup** | **PASS** | `BlockRegistry` and `BookingForm` share canonical handles (e.g., `mhm-rentiva-datepicker-custom`). |
| **Wrong-Asset Risk** | **PASS** | Both point to the same physical file paths in `assets/css/frontend/`. |

---

## 7.2 Dependency Graph Regression Check
**Goal:** Ensure declarative dependencies (`AvailabilityCalendar` → `BookingForm`) function correctly.

| Check | Status | Notes |
| :--- | :--- | :--- |
| **Asset Loading** | **PASS** | `AvailabilityCalendar` explicitly calls `BookingForm::enqueue_assets()` if class exists. |
| **JS Errors** | **PASS** | No syntax errors found in static analysis. |
| **Block Parity** | **PASS** | `ShortcodeServiceProvider` declares dependency; BlockRegistry handles its own deps. |

---

## 7.3 Nonce & AJAX Guard Quick Scan
**Goal:** Confirm refactor didn't break AJAX security barriers.

| Endpoint / Action | Nonce Check | Sanitization | Status |
| :--- | :--- | :--- | :--- |
| `ajax_booking_form` | **PASS** | Checks `mhm_rentiva_booking_form_nonce` (BookingForm.php). | **OK** |
| `ajax_calculate_price` | **PASS** | Checks `mhm_rentiva_booking_form_nonce` (BookingForm.php). | **OK** |
| `ajax_unified_availability` | **PASS** | Checks `mhm_rentiva_availability_nonce` (AvailabilityCalendar.php). | **OK** |
| `ajax_get_vehicle_info` | **PASS** | Checks `mhm_rentiva_availability_nonce` (AvailabilityCalendar.php). | **OK** |

**Note:** Verified matching nonces between `get_localized_data` and AJAX handlers in both `BookingForm` and `AvailabilityCalendar`.

---

## 7.4 Template Escaping Spot Check
**Goal:** Spot check templates for escaping issues.

| Template | Status | Notes |
| :--- | :--- | :--- |
| `booking-form.php` | **PASS** | Extensive use of `esc_html`, `esc_attr`. SVG output is raw (intentional). |
| `search-results.php` | **PASS** | `wp_kses_post` used for nonce; `esc_html` used for vehicle data. |

---

## 7.5 i18n Compliance Quick Scan
**Goal:** Verify text domain and string safety.

- [x] Text domain is strictly `'mhm-rentiva'`.
- [x] `_n()` used correctly for pluralization in `search-results.php`.
- [x] Date formatting respects WP locale.

---

## 7.6 Cache Poisoning Risk Check
**Goal:** Ensure user-specific data isn't cached globally.

| Check | Status | Notes |
| :--- | :--- | :--- |
| **Account Shortcodes** | **PASS** | `ShortcodeServiceProvider` configures `requires_auth => true`. |
| **Global Keys** | **PASS** | `AvailabilityCalendar` cache keys are vehicle+date specific, not user specific. |
| **Parametrized Output** | **PASS** | Search results use transient caching for vehicle list (global), not user results. |

---

## 7.7 Final Decision
- [x] **PASS** (Ready for Step 8: Integration Testing)
