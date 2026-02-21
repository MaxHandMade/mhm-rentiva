# QA & Evidence Report: v4.11.0 Phase 1 Surface Audit

**Plugin:** MHM Rentiva  
**Phase:** 1 (Surface Audit)  
**Verification Date:** 18.02.2026

## 1. CLI Evidence
| Metric | Result | Evidence |
| :--- | :--- | :--- |
| **PHPCS** | ✅ 0 Errors | `composer run phpcs` -> Exit code 0 |
| **Plugin Check** | ✅ 0 Production Errors | `wp plugin check` -> Passed |
| **Block.json Count** | ✅ 19 | `(Get-ChildItem -Recurse -Filter "block.json").Count` |
| **PHPUnit Tests** | ✅ 216 Tests / 0 Failures | `vendor/bin/phpunit` -> Exit code 0 |

## 2. Source Code Statistics
- **add_shortcode() Calls:** 3 (Centralized in `ShortcodeServiceProvider.php`)
- **register_block_type() Calls:** 58 (Including metadata-based registrations and build files)
- **Primary Block Registry:** `src/Blocks/BlockRegistry.php` (Authoritative)
- **Primary Shortcode Registry:** `src/Admin/Core/ShortcodeServiceProvider.php` (Authoritative)

## 3. MCP File Verification
- **ShortcodeServiceProvider.php:** Confirmed exists and manages 19 shortcodes in a centralized loop.
- **BlockRegistry.php:** Confirmed contains `do_shortcode()` delegation in `render_callback` (L452).
- **Parity Status:** 100% (19 Blocks mapping to 19 Shortcodes).

## 4. Drift Validation (Mapping Layer)

### 4.1 Hardcoded Alias List
The following aliases were extracted from `BlockRegistry.php`:

| Block Attribute (camelCase) | Shortcode Attribute (snake_case) |
| :--- | :--- |
| `showPrice` | `show_prices` |
| `showComparisonImages` | `show_images` |
| `showBookButton` | `show_booking_buttons` |
| `showRemoveButton` | `show_remove_buttons` |
| `showAddVehicle` | `show_add_vehicle` |
| `maxVehicles` | `max_vehicles` |
| `vehicleIds` | `vehicle_ids` |
| `startDate` | `start_date` |
| `endDate` | `end_date` |
| `defaultDays` | `default_days` |
| `minDays` | `min_days` |
| `maxDays` | `max_days` |
| `redirectUrl` | `redirect_url` |
| `defaultPayment` | `default_payment` |

### 4.2 Normalization Logic
- **Boolean Normalization:** Gutenberg booleans (`true`/`false`) are converted to shortcode-compatible strings (`"1"`/`"0"`) or empty strings.
- **CamelCase to Snake_case:** A regex-based transformer handles standard attributes.
- **Location:** `BlockRegistry::map_attributes_to_shortcode()` (Lines 510-600).

## 5. Verification Verdict
> [!IMPORTANT]
> **CONCLUSION: PASS.**  
> The surface audit is verified against the codebase. No hidden transform logic was found outside `BlockRegistry`. The inventory matches `PROJECT_MEMORIES.md`. Phase 2 (CAM Implementation) is safe to proceed.

---
*Reported by Antigravity AI - v4.11.0 Audit Suite.*
