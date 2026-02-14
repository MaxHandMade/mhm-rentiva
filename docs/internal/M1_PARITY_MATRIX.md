# M1 Parity Matrix: Blocks ↔ Shortcodes

**Generated:** 2026-02-14
**Source Version:** 4.9.8
**Context:** M1 Shortcode Engine Audit & Optimization

This document maps Gutenberg Blocks to their underlying Shortcodes, detailing attribute transformation rules and identifying parity issues.

## 1. Mapping Strategy

*   **Mechanism:** `MHMRentiva\Blocks\BlockRegistry::render_callback`
*   **Transformation:**
    1.  **Explicit Aliases:** Defined in `map_attributes_to_shortcode`.
    2.  **Implicit Conversion:** camelCase → snake_case (e.g., `showTitle` → `show_title`).
    3.  **Type Cast:** Booleans converted to "1"/"0".

## 2. Parity Table

| Block Slug | Shortcode Tag | Explicit Aliases | Status |
| :--- | :--- | :--- | :--- |
| `booking-form` | `rentiva_booking_form` | `minWidth`, `maxWidth`, `height` (Style injection) | ✅ Verified |
| `availability-calendar` | `rentiva_availability_calendar` | None | ✅ Verified |
| `vehicles-list` | `rentiva_vehicles_list` | `showPrice`→`show_price`, `showRating`→`show_rating`, `showBookButton`→`show_booking_btn`, etc. | ✅ Verified |
| `vehicle-comparison` | `rentiva_vehicle_comparison` | `showComparisonImages`→`show_images`, `showRemoveButton`→`show_remove_buttons` | ✅ Verified |

## 3. Discrepancies & Issues

### 3.1 [RESOLVED] Asset Handle Mismatch (Double Loading Risk)
**Severity:** LOW (Fixed)
**Description:** Blocks and Shortcodes were suspected of using different handles. Verification `B3` proved they are identical.

*   **Shortcode Handle:** `mhm-rentiva-rentiva-booking-form`
*   **Block Handle:** `mhm-rentiva-rentiva-booking-form` (Generated dynamically via tag parity)
*   **Result:** **NO DUPLICATION.** WordPress properly deduplicates the request.

### 3.2 Attribute Aliasing
*   `showBookButton` maps to `show_booking_btn`.
*   `showFavoriteButton` maps to `show_favorite_button`.
*   **Inconsistency:** Some shortcodes use `_btn`, some use `_button`. BlockRegistry handles this via aliases, but standardization is recommended in Shortcode attributes.

## 4. Recommendations

1.  **[DONE] Unify Asset Handles:** `BlockRegistry` has been updated to use tag-based handles (e.g. `mhm-rentiva-rentiva-booking-form`) ensuring parity with `AbstractShortcode`.
2.  **Standardize Attributes:** Deprecate `_btn` in favor of `_button` (or vice versa) across all shortcodes, maintaining aliases for backward compatibility.

