# V4.20.1 Ecosystem Audit (X-Ray) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Perform a full ecosystem audit (X-Ray) of the MHM Rentiva plugin for v4.20.1, produce a verified inventory snapshot, detect structural inconsistencies, and synchronize documentation to reflect actual runtime state.

**Architecture:** Audit-only workflow. No new functionality introduced. No behavioral refactors. Documentation synchronization only unless explicit defect correction is authorized. Single unified rendering pipeline invariant (Render Parity Rule) is binding.

**Tech Stack:** PHP 8.x, WordPress, Gutenberg Blocks, Elementor Widgets, PHPUnit, WP-CLI

---

## AUDIT FINDINGS (Evidence-Based)

### Source Files Inspected
1. `src/Admin/Core/ShortcodeServiceProvider.php` — Active shortcode registry (20 shortcodes)
2. `src/Blocks/BlockRegistry.php` — Block registry (19 blocks)
3. `src/Core/Attribute/AllowlistRegistry.php` — Canonical attribute schema (1053 lines)
4. `src/Admin/Frontend/Widgets/Elementor/ElementorIntegration.php` — Elementor widget registry (20 widgets)
5. `SHORTCODES.md` — Current documentation (partial 3-layer matrix exists)
6. `PROJECT_MEMORIES.md` — Memory log (v4.20.0 foundation freeze noted)

---

## PHASE 1: INVENTORY SNAPSHOT (Verified)

### Active Shortcodes (20 total — from ShortcodeServiceProvider)
| Shortcode | Group | Auth | Block | Allowlist | Elementor |
|-----------|-------|------|-------|-----------|-----------|
| `rentiva_booking_form` | reservation | No | ✅ | ✅ | ✅ |
| `rentiva_availability_calendar` | reservation | No | ✅ | ✅ | ✅ |
| `rentiva_booking_confirmation` | reservation | No | ✅ | ✅ | ✅ |
| `rentiva_vehicle_details` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_vehicles_list` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_featured_vehicles` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_vehicles_grid` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_search_results` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_vehicle_comparison` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_unified_search` | vehicle | No | ✅ | ✅ | ✅ |
| `rentiva_my_bookings` | account | Yes | ✅ | ✅ | ✅ |
| `rentiva_my_favorites` | account | Yes | ✅ | ✅ | ✅ |
| `rentiva_payment_history` | account | Yes | ✅ | ✅ | ✅ |
| `rentiva_transfer_search` | transfer | No | ✅ | ✅ | ✅ |
| `rentiva_transfer_results` | transfer | No | ✅ | ✅ | ✅ |
| `rentiva_contact` | support | No | ✅ | ✅ | ✅ |
| `rentiva_testimonials` | support | No | ✅ | ✅ | ✅ |
| `rentiva_vehicle_rating_form` | support | No | ✅ | ✅ | ✅ |
| `rentiva_messages` | support | Yes | ✅ | ✅ | ✅ |
| `rentiva_home_poc` | support | No | ❌ | ❌ | ❌ |

### Block Registry (19 blocks — from BlockRegistry)
All 19 blocks map to shortcodes via `render_callback → do_shortcode()` (Render Parity satisfied).
`mhm-rentiva/unified-search`, `mhm-rentiva/search-results`, `mhm-rentiva/transfer-results`,
`mhm-rentiva/vehicle-comparison`, `mhm-rentiva/testimonials`, `mhm-rentiva/availability-calendar`,
`mhm-rentiva/booking-confirmation`, `mhm-rentiva/vehicle-details`, `mhm-rentiva/vehicles-grid`,
`mhm-rentiva/vehicles-list`, `mhm-rentiva/featured-vehicles`, `mhm-rentiva/contact`,
`mhm-rentiva/vehicle-rating-form`, `mhm-rentiva/my-bookings`, `mhm-rentiva/my-favorites`,
`mhm-rentiva/payment-history`, `mhm-rentiva/booking-form`, `mhm-rentiva/messages`,
`mhm-rentiva/transfer-search`

### Elementor Widget Registry (20 widgets — from ElementorIntegration)
1. `VehicleCardWidget` — renders vehicle card component (no dedicated shortcode counterpart)
2. `UnifiedSearchWidget` → `rentiva_unified_search`
3. `SearchResultsWidget` → `rentiva_search_results`
4. `VehiclesListWidget` → `rentiva_vehicles_list`
5. `VehiclesGridWidget` → `rentiva_vehicles_grid`
6. `FeaturedVehiclesWidget` → `rentiva_featured_vehicles`
7. `VehicleDetailsWidget` → `rentiva_vehicle_details`
8. `BookingFormWidget` → `rentiva_booking_form`
9. `AvailabilityCalendarWidget` → `rentiva_availability_calendar`
10. `BookingConfirmationWidget` → `rentiva_booking_confirmation`
11. `MyBookingsWidget` → `rentiva_my_bookings`
12. `MyFavoritesWidget` → `rentiva_my_favorites`
13. `PaymentHistoryWidget` → `rentiva_payment_history`
14. `MyMessagesWidget` → `rentiva_messages`
15. `VehicleComparisonWidget` → `rentiva_vehicle_comparison`
16. `ContactFormWidget` → `rentiva_contact`
17. `TestimonialsWidget` → `rentiva_testimonials`
18. `VehicleRatingWidget` → `rentiva_vehicle_rating_form`
19. `TransferSearchWidget` → `rentiva_transfer_search`
20. `TransferResultsWidget` → `rentiva_transfer_results`

Note: `VehicleCardWidget` does not have a direct shortcode mapping. It wraps the vehicle card template component.

---

## PHASE 2: DISCREPANCY MATRIX

### CRITICAL Findings

#### C1: AllowlistRegistry::ALLOWLIST has 10 duplicate PHP array keys
**File:** `src/Core/Attribute/AllowlistRegistry.php`
**Impact:** In PHP, duplicate array keys silently overwrite the first definition with the second. This causes silent schema drift.

| Key | First Definition | Second Definition | Runtime Effect |
|-----|-----------------|-------------------|----------------|
| `ids` | type: `idlist`, group: `data`, aliases: `['ids','vehicleIds','vehicle_ids']` | type: `string`, group: `feature`, aliases: `['ids']` | **Type changed idlist→string. Aliases narrowed.** |
| `default_days` | type: `int`, group: `workflow`, aliases: `['defaultDays']` | type: `int`, group: `workflow`, aliases: `['defaultDays']` | No functional change (identical) |
| `min_days` | type: `int`, group: `workflow`, aliases: `['minDays']` | type: `int`, group: `workflow`, aliases: `['minDays']` | No functional change (identical) |
| `max_days` | type: `int`, group: `workflow`, aliases: `['maxDays']` | type: `int`, group: `workflow`, aliases: `['maxDays']` | No functional change (identical) |
| `redirect_page` | type: `string`, group: `workflow`, aliases: `['redirect_url','redirectPage','redirectUrl']` | type: `string`, group: `workflow`, aliases: `['redirectPage']` | **Aliases narrowed. `redirect_url` alias lost.** |
| `default_tab` | type: `enum`, group: `workflow`, aliases: `['defaultTab']` | type: `string`, group: `feature`, aliases: `['defaultTab']` | **Type changed enum→string. Group changed.** |
| `months_to_show` | type: `int`, group: `layout`, aliases: `['monthsToShow']` | type: `int`, group: `feature`, aliases: `['monthsToShow']` | Group changed layout→feature (minor) |
| `show_booking_button` | type: `bool`, aliases: `['showBookButton','show_booking_button','showBookBtn','show_book_btn']` | type: `bool`, aliases: `['showBookButton']` | **Aliases narrowed. `show_book_btn`, `showBookBtn` aliases lost.** |
| `show_favorite_btn` | type: `bool`, aliases: `['showFavoriteBtn']` | type: `bool`, aliases: `['show_favorite_button']` | **Alias changed. `showFavoriteBtn` → `show_favorite_button`.** |
| `show_compare_btn` | type: `bool`, aliases: `['showCompareBtn']` | type: `bool`, aliases: `['show_compare_button']` | **Alias changed. `showCompareBtn` → `show_compare_button`.** |

**Recommended Action:** Code-level fix in AllowlistRegistry.php to remove duplicate definitions and merge intended aliases into the first definition. **Requires separate authorized task.**

#### C2: `rentiva_home_poc` is orphaned (SC-Only, no Allowlist schema, no Block)
**Impact:** No attribute schema validation. No CAM (CanonicalAttributeMapper) pipeline applied.
**Status:** Already documented in SHORTCODES.md as "Experimental". No attributes accepted by design.
**Recommended Action:** Document formally. Either promote to full shortcode with Allowlist schema or deprecate.

---

### MAJOR Findings

#### M1: Enum attributes without `values` constraint arrays
These attributes have `type: 'enum'` in ALLOWLIST but no `values` key:
| Attribute | Group | Note |
|-----------|-------|------|
| `status` | workflow | Used in my_bookings, payment_history, messages |
| `default_payment` | workflow | Used in booking_form |
| `service_type` | feature | Used in featured_vehicles, unified_search |
| `type` | feature | Used in contact (type: inquiry/complaint/etc.) |

Note: `default_tab` originally had type: `enum` but due to C1 duplicate key, runtime type is now `string`.

#### M2: TAG_MAPPING references attributes NOT in canonical ALLOWLIST
These attributes are in TAG_MAPPING but have no definition in `AllowlistRegistry::ALLOWLIST`. The `get_registry()` method falls through to empty config `[]` — effectively untyped.

| Shortcode | Attribute | Closest Canonical | Impact |
|-----------|-----------|-------------------|--------|
| `rentiva_vehicle_details` | `show_technical_specs` | None in ALLOWLIST | Untyped pass-through |
| `rentiva_vehicle_details` | `show_booking_form` | None in ALLOWLIST | Untyped pass-through |
| `rentiva_vehicle_comparison` | `show_technical_specs` | None in ALLOWLIST | Untyped pass-through |
| `rentiva_vehicle_comparison` | `show_book_button` | `show_booking_button` | Alias gap |
| `rentiva_testimonials` | `sort_by` | `orderby` (canonical) | Non-canonical alias |
| `rentiva_testimonials` | `sort_order` | `order` (canonical) | Non-canonical alias |
| `rentiva_messages` | `show_avatar` | `show_author_avatar` (canonical) | Alias mismatch |

#### M3: `filter_rating` in `rentiva_testimonials` TAG_MAPPING is an alias, not a canonical key
`filter_rating` appears in ALLOWLIST only as an alias of `show_rating`, not as a standalone canonical key. TAG_MAPPING reference returns empty schema from `get_registry()`.

---

### MINOR Findings

#### Mi1: Intentional but redundant twin attributes in ALLOWLIST
`show_booking_button` / `show_booking_btn`, `show_favorite_btn` / `show_favorite_button`, `show_compare_btn` / `show_compare_button` are all registered as separate canonical keys. This is acknowledged redundancy from the migration to unified aliases.

#### Mi2: Block-Only / SC-Only attributes in 3-Layer Matrix
The existing 3-Layer Matrix in SHORTCODES.md documents many `⚠️ Block Only` and `⚠️ SC Only` attributes. These are by design (CAM alias resolution). Not a defect.

---

## PHASE 3: DOCUMENTATION TASKS

### Task 1: Update SHORTCODES.md

**File:** `SHORTCODES.md`
**Changes:**
1. Add Section 5: Elementor Widget Inventory (20 widgets)
2. Add Section 6: Audit Findings v4.20.1 with:
   - 6.1 Critical Findings (C1: duplicate keys, C2: home_poc orphan)
   - 6.2 Major Findings (M1: enum values, M2: unregistered attributes, M3: filter_rating)
   - 6.3 Minor Findings
3. Verify Section 1 inventory matches runtime (currently accurate — 20 shortcodes)

**Step 1: Read current SHORTCODES.md end section**
Read from line 200 onward to see what's already there.

**Step 2: Append new sections**
Append Elementor inventory and audit findings.

**Step 3: Verify all section headings are correct**

---

### Task 2: Update PROJECT_MEMORIES.md

**File:** `PROJECT_MEMORIES.md`
**Changes:**
Prepend new audit log entry for v4.20.1 X-Ray at the top.

---

### Task 3: Create QA Evidence Document

**File:** `docs/2026-02-25-V4201-ECOSYSTEM-AUDIT-EVIDENCE.md`
**Content:**
- CLI commands for verification
- Runtime validation results
- PHPUnit baseline reference
- MCP file verification results

---

## PHASE 4: VALIDATION REQUIREMENTS

### Required CLI Commands
```bash
# 1. Verify plugin is active
wp plugin list --fields=name,status,version

# 2. Extract runtime shortcode registry
wp eval 'print_r( apply_filters( "mhm_rentiva_registered_shortcodes", [] ) );'

# 3. Verify shortcodes via ShortcodeServiceProvider
wp eval '$p = MHMRentiva\Admin\Core\ShortcodeServiceProvider::instance(); echo "Total: " . MHMRentiva\Admin\Core\ShortcodeServiceProvider::get_total_count();'

# 4. Check registered blocks
wp eval 'print_r( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) );' | grep mhm-rentiva

# 5. Dump AllowlistRegistry TAG_MAPPING keys
wp eval 'print_r( array_keys( MHMRentiva\Core\Attribute\AllowlistRegistry::get_registry() ) );'
```

### Required PHPUnit Command
```bash
cd C:\projects\rentiva-dev\plugins\mhm-rentiva
vendor/bin/phpunit --testdox
```
**Baseline:** 268 tests, 1379 assertions (v4.20.0 foundation freeze)
**Acceptance:** No new failures. Same or higher count.

---

## Definition of Done (DoD)

- [x] All shortcodes verified against runtime (ShortcodeServiceProvider)
- [x] All blocks verified against registry (BlockRegistry)
- [x] AllowlistRegistry mismatches documented (C1, M1, M2, M3)
- [x] Elementor layer inventoried (20 widgets)
- [ ] SHORTCODES.md updated with Elementor section + Audit Findings
- [ ] PROJECT_MEMORIES.md updated with audit entry
- [ ] QA evidence document created
- [ ] CLI evidence obtained (Windows/WP-CLI environment dependent)
- [ ] PHPUnit baseline confirmed
- [ ] QA Decision: Pass or Fail documented
