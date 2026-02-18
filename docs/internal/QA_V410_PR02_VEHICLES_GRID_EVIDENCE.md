# QA_V410_PR02_VEHICLES_GRID_EVIDENCE

## 1. Default Parity Diff Table (Mandatory)

Establish deterministic Block ↔ Shortcode parity for `rentiva_vehicles_grid`.

- **Shortcode Default Reference:** `MHMRentiva\Admin\Frontend\Shortcodes\VehiclesGrid::get_default_attributes()` (line 49)
- **Block Default Reference:** `assets/blocks/vehicles-grid/block.json`

| Key | Shortcode Default | Block Default (Before) | Block Default (After) | Match After |
| :--- | :--- | :--- | :--- | :--- |
| `limit` | `12` | `12` | `12` | YES |
| `columns` | `2` | `2` | `2` | YES |
| `enable_ajax_filtering` | `0` | `(missing)` | `false` | YES |

---

## 2. Type Normalization Disclosure
- **Mechanism:** Centralized via `BlockRegistry::map_attributes_to_shortcode`.
- **Logic:** `boolean` `true` is converted to string `'1'`, and `false` to string `'0'`.
- **Verification:** Confirmed that `BlockRegistry` maps `enableAjaxFiltering` (bool) to `enable_ajax_filtering` (string) correctly for all blocks, including `rentiva_vehicles_grid`.

---

## 3. CLI Hard Gates Evidence

### M4 Gate 1: PHPCS
- **Command:** `vendor/bin/phpcs --standard=phpcs.xml src/Admin/Frontend/Shortcodes/VehiclesGrid.php src/Blocks/BlockRegistry.php`
- **Result:** PASS (Warnings only for pre-existing alignment).
- **Log Snippet:**
```json
{"totals":{"errors":0,"warnings":3}}
```

### M4 Gate 2: Plugin Check
- **Command:** `composer run plugin-check:release`
- **Result:** PASS (0 errors).
- **Exit Code:** 0

### M4 Gate 3: PHPUnit
- **Command:** `vendor/bin/phpunit tests/Integration/VehiclesGridDefaultsParityTest.php`
- **Result:** PASS (1 test, 36 assertions).
- **Exit Code:** 0

---

## 4. Smoke Test Confirmation

### Editor SSR Preview
- Block added to Gutenberg page.
- SSR renders vehicle grid correctly with `limit="12"` and `columns="2"`.
- "Show Filters" toggle successfully added to "Visibility Controls" panel.

### Frontend Parity
- Verified that block output mirrors shortcode `[rentiva_vehicles_grid enable_ajax_filtering="1"]` when toggled.

---

## 5. Parity Matrix Update
- **Status:** ✅ COMPLETED
- **Default Parity:** YES
- **Type Parity:** YES
- **Missing-in-Block:** none
