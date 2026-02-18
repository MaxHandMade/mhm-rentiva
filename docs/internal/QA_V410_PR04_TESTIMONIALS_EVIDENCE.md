# QA_V410_PR04_TESTIMONIALS_EVIDENCE

## 1. Default Parity Diff Table (Mandatory)

Establish deterministic Block ↔ Shortcode parity for `rentiva_testimonials`.

- **Shortcode Default Reference:** `MHMRentiva\Admin\Frontend\Shortcodes\Testimonials::get_default_attributes()` ([L70](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/src/Admin/Frontend/Shortcodes/Testimonials.php#L70))
- **Block Default Reference:** `assets/blocks/testimonials/block.json`

| Key | Shortcode Default | Block Default (Before) | Block Default (After) | Match After |
| :--- | :--- | :--- | :--- | :--- |
| `limit` (limitItems) | `5` | `6` | `5` | YES |
| `layout` | `grid` | `carousel` | `grid` | YES |
| `columns` | `3` | `3` | `3` | YES |
| `auto_rotate` (autoplay) | `0` (false) | `true` | `false` | YES |
| `show_rating` | `1` | `true` | `true` | YES |
| `show_date` | `1` | `true` | `true` | YES |
| `show_vehicle` | `1` | `true` | `true` | YES |
| `show_customer` | `1` | `true` | `true` | YES |

---

## 2. author_name Status Declaration
- **Finding:** Shortcode `rentiva_testimonials` does not support `author_name` as a filter in its attributes or query logic ([QA_V410_PR04_TESTIMONIALS_SOT_VERIFY.md](file:///c:/xampp/htdocs/otokira/wp-content/plugins/mhm-rentiva/docs/internal/QA_V410_PR04_TESTIMONIALS_SOT_VERIFY.md)).
- **Action:** Not added to block to maintain strict SOT parity.
- **Runtime:** No behavior change introduced.

---

## 3. Type Normalization Disclosure
- **Mechanism:** `BlockRegistry::map_attributes_to_shortcode`.
- **Logic:** 
  - `boolean` `true` → string `'1'`.
  - `boolean` `false` → string `'0'`.
- **Specific Aliases:**
  - `limitItems` → `limit`
  - `autoplay` → `auto_rotate`
  - `showAuthorName` → `show_customer`
  - `showVehicleName` → `show_vehicle`

---

## 4. CLI Hard Gates Evidence

### M4 Gate 1: PHPCS
- **Command:** `composer run phpcs -- src/Admin/Frontend/Shortcodes/Testimonials.php src/Blocks/BlockRegistry.php`
- **Result:** PASS (Warnings only).
- **Log Snippet:** `{"totals":{"errors":0,"warnings":13}}`

### M4 Gate 2: Plugin Check
- **Command:** `composer run plugin-check:release`
- **Result:** PASS (0 errors).

### M4 Gate 3: PHPUnit
- **Command:** `vendor/bin/phpunit`
- **Result:** PASS (216 tests).
- **Exit Code:** 0

---

## 5. Smoke Test Confirmation

### Editor SSR Preview
- Block inserted into page.
- Layout defaults to "Grid".
- Limit defaults to 5 items.
- SSR renders correct markup matched with shortcode output.

### Frontend Parity
- Block output matches `[rentiva_testimonials]` exactly when defaults are used.
- Toggling Autoplay correctly triggers `auto_rotate` logic in shortcode context.

---

## 6. Parity Matrix Update
- **Status:** ✅ COMPLETED
- **Default Parity:** YES
- **Type Parity:** YES
- **Missing-in-Block:** none
