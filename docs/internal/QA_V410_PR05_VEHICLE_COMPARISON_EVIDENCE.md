# QA Evidence: PR-05 Vehicle Comparison Parity Implementation

## Summary
Achieved full parity between the `mhm-rentiva/vehicle-comparison` block and the `rentiva_vehicle_comparison` shortcode. This PR implemented plural-to-singular attribute mapping (aliasing) and a deterministic value transformation for the `showFeatures` attribute to maintain strict logic compatibility.

## 1. Default Parity Diff Table
| Key | Shortcode Default | Block Default | Match (Normalized) |
| :--- | :--- | :--- | :--- |
| `vehicle_ids` | `""` | `""` | **YES** |
| `show_features` | `'all'` | `true` | **YES** (via Transform) |
| `max_vehicles` | `"4"` | `"4"` | **YES** |
| `show_prices` | `'1'` | `true` | **YES** (via Normalize) |
| `show_images` | `'1'` | `true` | **YES** (via Normalize) |
| `show_booking_buttons`| `'1'`| `true` | **YES** (via Normalize) |

## 2. Alias Table (Explicit Mapping)
The following aliases were added to `BlockRegistry.php` to resolve naming mismatches:
| Block Key | Shortcode Key | Reason |
| :--- | :--- | :--- |
| `showPrice` | `show_prices` | Plural mismatch |
| `showComparisonImages`| `show_images` | Shorter key in shortcode |
| `showBookButton` | `show_booking_buttons` | Plural/Naming mismatch |
| `maxVehicles` | `max_vehicles` | snake_case compliance |
| `vehicleIds` | `vehicle_ids` | snake_case compliance |

## 3. `showFeatures` Value Transform
Strictly isolated to the `vehicle-comparison` block in `BlockRegistry.php`:
- **Logic**: `true` → `'all'`, `false` → `'basic'`
- **Parity Status**: **YES** (Type Parity achieved).

## 4. M4 Hard Gates Verification
| Tool | Status | Note |
| :--- | :--- | :--- |
| `PHPCS` | **PASS** | Verified via `composer run phpcs` |
| `Plugin Check` | **PASS** | Verified via `composer run plugin-check:release` |
| `PHPUnit` | **PASS** | Verified via `composer run test` (216 tests passed) |

## 5. Visual Smoke Test
Verified in Gutenberg Editor. SSR preview correctly renders the comparison table and reacts to attribute changes without JSON errors.

![Vehicle Comparison Smoke Test](assets/vehicle_comparison_smoke_test.webp)

---
**Status: YES/YES Parity Tescil Edildi.**
