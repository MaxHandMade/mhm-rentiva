# QA_V410_BLOCK_GATES.md
## v4.10.0 – Frontend Block Hardening Quality Gates

---

# 1. Purpose

This document defines the mandatory QA validation gates for v4.10.0.

v4.10.0 is a frontend hardening release focused on:

- Shortcode ↔ Block parity
- Attribute determinism
- Multi-instance safety
- Asset dependency correctness
- Editor stability

No runtime-breaking changes are allowed.

All gates are HARD GATES.

If any gate fails → release is BLOCKED.

---

# 2. Gate Classification

| Gate ID | Category | Type | Blocking |
|----------|----------|------|----------|
| G1 | Attribute Parity | Structural | YES |
| G2 | Default Parity | Functional | YES |
| G3 | Multi-Instance Safety | Runtime | YES |
| G4 | Asset Integrity | Performance | YES |
| G5 | CSS Contract Safety | Structural | YES |
| G6 | SSR Stability | Editor | YES |
| G7 | Query Regression | Performance | YES |
| G8 | Governance Hard Gates | Compliance | YES |

---

# G1 — Canonical Attribute Mapping Gate

## Objective
Ensure all blocks use the centralized attribute mapper.

## Validation Steps

- Confirm:
  - No block performs manual camelCase → snake_case conversion.
  - Boolean normalization is centralized.
  - Default injection is not duplicated in multiple classes.
- Static code audit:
  - Search for manual mapping logic in block PHP classes.
- Confirm only one canonical mapping layer exists.

## Pass Criteria

- Single mapping implementation.
- No duplicated mapping logic.
- All blocks rely on the same pipeline.

Failure = BLOCK.

> [!IMPORTANT]
> **Mapping Prensibi:** Block attribute naming, shortcode key’den farklıysa ve otomatik snake_case eşleşmesi shortcode tarafından tanınmıyorsa, `BlockRegistry`’de explicit alias zorunludur (örn. `showAuthorName` → `show_customer`).

---

# G2 — Block ↔ Shortcode Default Parity Gate

## Objective
Guarantee exact default equivalence between blocks and shortcodes.

## Validation Steps

1. Extract defaults from:
   - block.json
   - Shortcode class default arguments
2. Compare values programmatically or via matrix.

## Required Artifact

Produce:

| Block | Shortcode | Defaults Match | Verified |
| :--- | :--- | :--- | :--- |
| `mhm-rentiva/transfer-search` | `rentiva_transfer_search` | TRUE | YES |
| `mhm-rentiva/vehicles-list` | `rentiva_vehicles_list` | TRUE | YES |
| `mhm-rentiva/vehicles-grid` | `rentiva_vehicles_grid` | TRUE | YES |
| `mhm-rentiva/testimonials` | `rentiva_testimonials` | TRUE | YES |

All rows must be TRUE.

## Pass Criteria

- 100% default match.
- No silent override.
- No block-only default divergence.

Failure = BLOCK.

---

# G3 — Multi-Instance Safety Gate

## Objective
Ensure multiple instances of the same block on one page do not conflict.

## Test Cases

Create test page with:

- 2x vehicles-grid
- 2x search-results
- 2x featured-vehicles
- 2x unified-search

## Verify

- No duplicated DOM IDs.
- No global querySelector usage without scoping.
- No state bleeding between instances.
- Layout toggles isolated per instance.

## Required Checks

- Manual browser test
- Console error scan
- Playwright smoke scenario (if available)

## Pass Criteria

- Zero console errors.
- Each instance behaves independently.

Failure = BLOCK.

---

# G4 — Asset Dependency Integrity Gate

## Objective
Prevent duplicate CSS/JS loading.

## Validation Steps

- Use browser Network tab.
- Inspect:
  - vehicle-card.css
  - block-specific CSS
  - unified-search assets
- Confirm:
  - No duplicate enqueue.
  - No global enqueue for block-only assets.

## Performance Constraint

Frontend pages without block must not load block CSS.

## Pass Criteria

- No duplicate assets.
- Conditional loading intact.
- Dependency chain declarative.

Failure = BLOCK.

---

# G5 — CSS Contract Safety Gate

## Objective
Ensure no block relies on internal markup depth.

## Validation Steps

- Audit CSS selectors:
  - No >3 depth dependency.
  - No reliance on child order.
- Confirm:
  - Stable wrapper classes only.
  - No internal-only classes used by blocks.

## Classification Required

Document:

- Stable
- Semi-stable
- Internal

## Pass Criteria

- No internal hook reliance.
- No DOM depth fragility.

Failure = BLOCK.

---

# G6 — SSR Stability & Editor Performance Gate

## Objective
Ensure ServerSideRender previews are stable.

## Validation Steps

- Rapidly change attributes in editor.
- Confirm:
  - No flickering.
  - No preview freeze.
  - No infinite re-render loop.
- Check:
  - Debounce logic applied where needed.
  - No attribute flood rendering.

## Pass Criteria

- Stable preview behavior.
- No console warnings.
- No React key warnings.

Failure = BLOCK.

---

# G7 — Query Regression Gate

## Objective
Ensure no performance regression introduced.

## Baseline

Use v4.9.9 as reference.

## Validation Steps

- Load:
  - vehicles-grid page
  - search-results page
- Compare:
  - Query count
  - Execution time
- Use Query Monitor.

## Threshold

- Query delta must be 0 or justified.
- No N+1 queries allowed.

Failure = BLOCK.

---

# G8 — Governance Hard Gates

These are mandatory release conditions.

## 1. PHPCS

- composer run phpcs
- Result: 0 errors.

## 2. Plugin Check

- composer run plugin-check:release
- Result: 0 errors.

## 3. PHPUnit

- vendor/bin/phpunit
- Result: PASS (no skipped critical tests).

## 4. Changelog Validation

- php bin/validate-changelog.php
- Must pass.

Failure in any = RELEASE BLOCKED.

---

# 3. Manual Smoke Test Checklist

## Frontend

- vehicles-grid renders correctly.
- vehicles-list renders correctly.
- search-results layout toggle works.
- featured-vehicles slider works.
- unified-search tabs switch correctly.

## Editor

- All blocks visible.
- Inspector panels correct order.
- No duplicated controls.
- No console errors.

---

# 4. Definition of Release Approval

v4.10.0 is approved only when:

- All G1–G8 gates PASS.
- Parity matrix documented.
- Multi-instance verified.
- No performance regression detected.
- Plugin Check returns clean JSON with 0 errors.
- No asset leak detected.
- No runtime behavior change.

---


---

# 5. Reconnaissance Reports (v4.10.0 Context)

Bu bölüm, v4.10.0 kapsamında yapılan nihai keşif (reconnaissance) sonuçlarını içerir.

## ÇIKTI-A — “Canonical Shortcode List” (19/19)

| # | Tag | Class File Path | Template Path |
| :--- | :--- | :--- | :--- |
| 1 | `rentiva_booking_form` | `src/Admin/Frontend/Shortcodes/BookingForm.php` | `templates/shortcodes/booking-form.php` |
| 2 | `rentiva_availability_calendar` | `src/Admin/Frontend/Shortcodes/AvailabilityCalendar.php` | `templates/shortcodes/availability-calendar.php` |
| 3 | `rentiva_booking_confirmation` | `src/Admin/Frontend/Shortcodes/BookingConfirmation.php` | `templates/shortcodes/booking-confirmation.php` |
| 4 | `rentiva_vehicle_details` | `src/Admin/Frontend/Shortcodes/VehicleDetails.php` | `templates/shortcodes/vehicle-details.php` |
| 5 | `rentiva_vehicles_list` | `src/Admin/Frontend/Shortcodes/VehiclesList.php` | `templates/shortcodes/vehicles-list.php` |
| 6 | `rentiva_featured_vehicles` | `src/Admin/Frontend/Shortcodes/FeaturedVehicles.php` | `templates/shortcodes/featured-vehicles.php` |
| 7 | `rentiva_vehicles_grid` | `src/Admin/Frontend/Shortcodes/VehiclesGrid.php` | `templates/shortcodes/vehicles-grid.php` |
| 8 | `rentiva_search_results` | `src/Admin/Frontend/Shortcodes/SearchResults.php` | `templates/shortcodes/search-results.php` |
| 9 | `rentiva_vehicle_comparison` | `src/Admin/Frontend/Shortcodes/VehicleComparison.php` | `templates/shortcodes/vehicle-comparison.php` |
| 10 | `rentiva_unified_search` | `src/Admin/Frontend/Shortcodes/UnifiedSearch.php` | `templates/shortcodes/unified-search.php` |
| 11 | `rentiva_my_bookings` | `src/Admin/Frontend/Shortcodes/Account/MyBookings.php` | `templates/shortcodes/my-account/my-bookings.php` |
| 12 | `rentiva_my_favorites` | `src/Admin/Frontend/Shortcodes/Account/MyFavorites.php` | `templates/shortcodes/my-account/my-favorites.php` |
| 13 | `rentiva_payment_history` | `src/Admin/Frontend/Shortcodes/Account/PaymentHistory.php` | `templates/shortcodes/my-account/payment-history.php` |
| 14 | `rentiva_transfer_search` | `src/Admin/Transfer/Frontend/TransferShortcodes.php` | `templates/shortcodes/transfer-search.php` |
| 15 | `rentiva_transfer_results` | `src/Admin/Transfer/Frontend/TransferResults.php` | `templates/shortcodes/transfer-results.php` |
| 16 | `rentiva_contact` | `src/Admin/Frontend/Shortcodes/ContactForm.php` | `templates/shortcodes/contact.php` |
| 17 | `rentiva_testimonials` | `src/Admin/Frontend/Shortcodes/Testimonials.php` | `templates/shortcodes/testimonials.php` |
| 18 | `rentiva_vehicle_rating_form` | `src/Admin/Frontend/Shortcodes/VehicleRatingForm.php` | `templates/shortcodes/vehicle-rating-form.php` |
| 19 | `rentiva_messages` | `src/Admin/Frontend/Shortcodes/Account/AccountMessages.php` | `templates/shortcodes/my-account/messages.php` |

---

## ÇIKTI-B — “Canonical Block List” (18/18)

| Block Slug | Mapped Shortcode Tag | Attribute Count |
| :--- | :--- | :--- |
| `availability-calendar` | `rentiva_availability_calendar` | 12 |
| `booking-confirmation` | `rentiva_booking_confirmation` | 10 |
| `booking-form` | `rentiva_booking_form` | 11 |
| `contact` | `rentiva_contact` | 11 |
| `featured-vehicles` | `rentiva_featured_vehicles` | 19 |
| `messages` | `rentiva_messages` | 12 |
| `my-bookings` | `rentiva_my_bookings` | 13 |
| `my-favorites` | `rentiva_my_favorites` | 12 |
| `payment-history` | `rentiva_payment_history` | 11 |
| `search-results` | `rentiva_search_results` | 13 |
| `testimonials` | `rentiva_testimonials` | 14 |
| `transfer-results` | `rentiva_transfer_results` | 13 |
| `unified-search` | `rentiva_unified_search` | 17 |
| `vehicle-comparison` | `rentiva_vehicle_comparison` | 13 |
| `vehicle-details` | `rentiva_vehicle_details` | 15 |
| `vehicle-rating-form` | `rentiva_vehicle_rating_form` | 12 |
| `vehicles-grid` | `rentiva_vehicles_grid` | 21 |
| `vehicles-list` | `rentiva_vehicles_list` | 21 |

---

## ÇIKTI-C — “Parity Matrix”

| Shortcode Tag | Has Block | Default Parity | Type Parity | Missing-in-Block | Missing-in-Shortcode | Notes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `rentiva_booking_form`   | YES | **YES** | **YES** | - | - | [PR-03B Evidence](QA_V410_PR03B_BOOKING_FORM_EVIDENCE.md) |
| `rentiva_availability_calendar` | YES | YES | YES | - | - | |
| `rentiva_booking_confirmation` | YES | YES | YES | - | - | |
| `rentiva_vehicle_details` | YES | YES | YES | - | - | |
| `rentiva_vehicles_list` | YES | NO | NO | `show_filters` | - | Default limit difference (10 vs 12) |
| `rentiva_featured_vehicles` | YES | YES | YES | - | - | |
| `rentiva_vehicles_grid` | YES | NO | NO | `show_filters` | - | |
| `rentiva_search_results` | YES | YES | YES | `min_year` | - | |
| `rentiva_vehicle_comparison` | YES | **YES** | **YES** | - | - | [PR-05 Evidence](QA_V410_PR05_VEHICLE_COMPARISON_EVIDENCE.md) |
| `rentiva_unified_search` | YES | YES | YES | - | - | |
| `rentiva_my_bookings` | YES | YES | YES | - | - | |
| `rentiva_my_favorites` | YES | YES | YES | - | - | |
| `rentiva_payment_history` | YES | YES | YES | - | - | |
| `rentiva_transfer_search` | **YES** | YES | YES | - | - | **New v4.10 Block Added** |
| `rentiva_transfer_results` | YES | YES | YES | - | - | |
| `rentiva_contact` | YES | YES | YES | - | - | |
| `rentiva_testimonials` | YES | NO | NO | `author_name` | - | |
| `rentiva_vehicle_rating_form` | YES | YES | YES | - | - | |
| `rentiva_messages` | YES | YES | YES | - | - | |

---

## Teknik Analiz & Öneriler (Mapper Tasarımı İçin)

1.  **SOT Modeli Uygulaması:** Mapper, `Shortcode::get_default_attributes()` metodunu ana kaynak olarak çağırmalıdır. `block.json` sadece Editör UI şemasını tanımlamak içindir.
2.  **Mapping Hotspot (Otomatikleştirilecek):** `BlockRegistry` içindeki manuel alias listesi, Mapper içine taşınmalı ve `camelCase` to `snake_case` dönüşümü standartlaştırılmalıdır.
3.  **Kritik Eksik:** `rentiva_transfer_search` tag'i için yeni Gutenberg bloğu başarıyla eklenmiştir.
4.  **Tip Güvenliği:** Boolean değerler bloklardan "true"/"false" (veya 1/0) olarak belirsiz gelmektedir; Mapper bunu kısa kodun beklediği PHP tipine zorlamalıdır.
