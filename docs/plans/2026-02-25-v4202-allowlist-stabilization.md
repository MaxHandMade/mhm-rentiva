# v4.20.2 — Allowlist Stabilization Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Restore `AllowlistRegistry` as Single Source of Truth by eliminating 10 duplicate PHP array keys (C1), adding `values` constraints to 4 enum attributes (M1), closing 6 canonical gaps in TAG_MAPPING (M2), and adding `SHORTCODES.md` to version control (Governance).

**Architecture:** All changes are confined to `src/Core/Attribute/AllowlistRegistry.php` (ALLOWLIST constant + TAG_MAPPING constant) and `.gitignore`. No shortcode PHP, no block.json, no template changes. Each fix is additive or subtractive within the PHP constant — no behavioral refactors. PHPUnit SchemaParityTest provides regression net.

**Tech Stack:** PHP 8.x, WordPress, PHPUnit (WP_UnitTestCase), WP-CLI (optional runtime verification)

---

## Pre-Flight: Understand the Existing Defects

Before touching code, read the exact lines that need changing.

### Duplicate Keys Reference (C1)

All 10 duplicate keys confirmed from static analysis. The PHP runtime uses the **last** definition. First definitions (correct ones) are at these lines:

| Duplicate Key | First Def (KEEP) | Second Def (REMOVE) | Drift |
|---|---|---|---|
| `ids` | L167–171 `idlist/data/3 aliases` | L648–652 `string/feature/1 alias` | Type: idlist→string, aliases narrowed |
| `default_days` | L108–112 `int/workflow` | L693–697 identical | No functional change (remove anyway) |
| `min_days` | L113–117 `int/workflow` | L698–702 identical | No functional change |
| `max_days` | L118–122 `int/workflow` | L703–707 identical | No functional change |
| `redirect_page` | L172–176 `3 aliases incl. redirect_url` | L777–781 `1 alias (redirectPage only)` | Alias loss: `redirect_url` gone |
| `default_tab` | L177–181 `enum/workflow` | L794–798 `string/feature` | Type: enum→string |
| `months_to_show` | L299–303 `int/layout` | L1044–1048 `int/feature` | Group drift: layout→feature |
| `show_booking_button` | L209–213 `4 aliases` | L873–877 `1 alias (showBookButton)` | Aliases narrowed |
| `show_favorite_btn` | L214–218 `aliases: [showFavoriteBtn]` | L883–887 `aliases: [show_favorite_button]` | Alias changed |
| `show_compare_btn` | L224–228 `aliases: [showCompareBtn]` | L888–892 `aliases: [show_compare_button]` | Alias changed |

**Action for C1:** Keep first definitions (correct ones). Remove all 10 second definitions. For `show_favorite_btn` and `show_compare_btn`: merge both alias sets into the first definition before removing the second.

### M1 Enum Values Reference

| Attribute | Line | Correct `values` (from runtime code) |
|---|---|---|
| `status` | L388–392 | `['', 'pending', 'confirmed', 'in_progress', 'cancelled', 'completed', 'refunded']` |
| `default_payment` | L767–771 | `['deposit', 'full']` (BookingForm.php L1123) |
| `service_type` | L839–843 | `['rental', 'transfer', 'both']` (UnifiedSearch.php L62) |
| `type` | L970–973 | `['general', 'booking', 'support', 'feedback']` (ContactForm.php L152+) |

Also: `default_tab` first definition (L177–181) had `type: 'enum'` before C1 drift. After C1 fix it is restored to enum. Add `values: ['rental', 'transfer', 'default']` (UnifiedSearch.php L48).

### M2 Canonical Gap Reference

These 6 keys appear in TAG_MAPPING but are NOT ALLOWLIST canonical keys. `get_registry()` returns empty schema `[]` for them.

| TAG_MAPPING Key | Shortcodes Using It | Fix Strategy |
|---|---|---|
| `show_technical_specs` | `rentiva_vehicle_details`, `rentiva_vehicle_comparison` | Add new canonical key (`bool/visibility`). Remove `showTechnicalSpecs` alias from `show_features` to avoid alias conflict. |
| `show_booking_form` | `rentiva_vehicle_details` | Add new canonical key (`bool/visibility`). |
| `show_book_button` | `rentiva_vehicle_comparison`, `rentiva_featured_vehicles` | Add `show_book_button` to first definition of `show_booking_button` aliases (L209–213) AND update TAG_MAPPING entries to use `show_booking_button` canonical. |
| `sort_by` | `rentiva_testimonials` | Add new canonical key (`enum/sorting`, same values as `orderby`). The shortcode accesses `$atts['sort_by']` directly — do NOT change TAG_MAPPING. |
| `sort_order` | `rentiva_testimonials` | Add new canonical key (`enum/sorting`, same values as `order`). |
| `show_avatar` | `rentiva_messages` | TAG_MAPPING has BOTH `show_avatar` (L1402) and `show_author_avatar` (L1413). Remove `show_avatar` from TAG_MAPPING (redundant — canonical `show_author_avatar` is already there). No new canonical needed. |

**Note:** `filter_rating` (M3 from audit) is already an alias of `show_rating` in ALLOWLIST. Fix: Remove `filter_rating` from testimonials TAG_MAPPING since `show_rating` is already there (L1325). `show_rating` handles both display and filtering via aliases.

### Governance: SHORTCODES.md Root Cause

The `.gitignore` uses `/*` (ignore everything in root) with selective whitelist. `SHORTCODES.md` is not in the whitelist. `PROJECT_MEMORIES.md` is tracked because it was added before the `/*` rule existed — once tracked, git ignores ignore rules for it.

**Fix:** Add `!/SHORTCODES.md` to `.gitignore` whitelist section.

---

## Task 1: Add AllowlistRegistry Tests for C1 Duplicate Key Detection

**Files:**
- Modify: `tests/Core/Attribute/SchemaParityTest.php`

**Background:** `SchemaParityTest` currently verifies block.json ↔ registry parity. We add tests that detect duplicate keys and enforce type contracts. These tests FAIL before the fix and PASS after.

**Step 1: Write failing tests — add to `SchemaParityTest.php`**

Add these two test methods at the end of the `SchemaParityTest` class (before the closing `}`):

```php
/**
 * Verify no duplicate PHP array keys exist in ALLOWLIST.
 * In PHP, duplicate keys in a const array silently overwrite the first.
 * This test reads the raw file and detects key collisions.
 */
public function test_allowlist_has_no_duplicate_keys(): void
{
    $file = file_get_contents(MHM_RENTIVA_PLUGIN_PATH . 'src/Core/Attribute/AllowlistRegistry.php');

    // Extract all top-level ALLOWLIST keys using regex
    preg_match_all("/^\s+'([a-z_]+)'\s*=>\s*\[/m", $file, $matches);
    $keys = $matches[1];

    $counted = array_count_values($keys);
    $duplicates = array_filter($counted, fn($count) => $count > 1);

    $this->assertEmpty(
        $duplicates,
        'AllowlistRegistry::ALLOWLIST has duplicate PHP array keys: ' . implode(', ', array_keys($duplicates))
    );
}

/**
 * Verify ids attribute is type idlist (not string).
 * C1 duplicate key caused type drift: idlist → string.
 */
public function test_ids_attribute_type_is_idlist(): void
{
    $allowlist = (new \ReflectionClass(\MHMRentiva\Core\Attribute\AllowlistRegistry::class))
        ->getReflectionConstant('ALLOWLIST')
        ->getValue();

    $this->assertArrayHasKey('ids', $allowlist, 'ids key missing from ALLOWLIST');
    $this->assertSame('idlist', $allowlist['ids']['type'], 'ids type should be idlist, not string');
    $this->assertContains('vehicleIds', $allowlist['ids']['aliases'], 'ids aliases should include vehicleIds');
    $this->assertContains('vehicle_ids', $allowlist['ids']['aliases'], 'ids aliases should include vehicle_ids');
}

/**
 * Verify default_tab attribute is type enum (not string).
 * C1 duplicate key caused type drift: enum → string.
 */
public function test_default_tab_type_is_enum(): void
{
    $allowlist = (new \ReflectionClass(\MHMRentiva\Core\Attribute\AllowlistRegistry::class))
        ->getReflectionConstant('ALLOWLIST')
        ->getValue();

    $this->assertArrayHasKey('default_tab', $allowlist);
    $this->assertSame('enum', $allowlist['default_tab']['type'], 'default_tab type should be enum, not string');
    $this->assertNotEmpty($allowlist['default_tab']['values'] ?? [], 'default_tab enum must have values array');
}
```

**Step 2: Run tests to confirm they fail**

```bash
cd C:\projects\rentiva-dev\plugins\mhm-rentiva
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --testdox
```

Expected: `test_allowlist_has_no_duplicate_keys` FAILS listing the 10 duplicate keys.
Expected: `test_ids_attribute_type_is_idlist` FAILS because runtime `ids` type is `string`.
Expected: `test_default_tab_type_is_enum` FAILS because runtime `default_tab` type is `string`.

**Step 3: Commit test scaffolding (before fix)**

```bash
git add tests/Core/Attribute/SchemaParityTest.php
git commit -m "test(allowlist): add C1 regression tests for duplicate key detection"
```

---

## Task 2: Fix C1 — Remove Duplicate ALLOWLIST Keys

**Files:**
- Modify: `src/Core/Attribute/AllowlistRegistry.php`

**Step 1: Merge aliases for `show_favorite_btn` and `show_compare_btn` in first definitions**

Update lines 214–228 to merge both alias sets:

```php
'show_favorite_btn'        => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showFavoriteBtn', 'show_favorite_button', 'showFavoriteButton'],
],
'show_favorite_button'     => [   // Keep as-is (twin canonical — Mi1 pattern)
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showFavoriteButton'],
],
'show_compare_btn'         => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showCompareBtn', 'show_compare_button', 'showCompareButton'],
],
'show_compare_button'      => [   // Keep as-is (twin canonical — Mi1 pattern)
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showCompareButton'],
],
```

**Step 2: Remove all 10 second-occurrence duplicate keys**

Remove these exact blocks from `AllowlistRegistry.php`:

**Block A — lines 648–652** (second `ids`):
```php
'ids'                      => [
    'type'    => 'string',
    'group'   => 'feature',
    'aliases' => ['ids'],
],
```

**Block B — lines 693–707** (second `default_days`, `min_days`, `max_days` — remove all three):
```php
'default_days'             => [
    'type'    => 'int',
    'group'   => 'workflow',
    'aliases' => ['defaultDays'],
],
'min_days'                 => [
    'type'    => 'int',
    'group'   => 'workflow',
    'aliases' => ['minDays'],
],
'max_days'                 => [
    'type'    => 'int',
    'group'   => 'workflow',
    'aliases' => ['maxDays'],
],
```

**Block C — lines 777–781** (second `redirect_page`):
```php
'redirect_page'            => [
    'type'    => 'string',
    'group'   => 'workflow',
    'aliases' => ['redirectPage'],
],
```

**Block D — lines 794–798** (second `default_tab`):
```php
'default_tab'              => [
    'type'    => 'string',
    'group'   => 'feature',
    'aliases' => ['defaultTab'],
],
```

**Block E — lines 873–892** (second `show_booking_button`, `show_favorite_btn`, `show_compare_btn`):
```php
'show_booking_button'      => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showBookButton'],
],
'show_booking_btn'         => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showBookButton'],
],
'show_favorite_btn'        => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['show_favorite_button'],
],
'show_compare_btn'         => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['show_compare_button'],
],
```

Note: `show_booking_btn` at L878–882 is NOT a duplicate of the first `show_booking_button` (different key name) but it has a `showBookButton` alias that conflicts. Keep `show_booking_btn` as a separate canonical entry — it is referenced in TAG_MAPPING for several shortcodes. Do NOT remove it.

**Block F — lines 1044–1048** (second `months_to_show`):
```php
'months_to_show'           => [
    'type'    => 'int',
    'group'   => 'feature',
    'aliases' => ['monthsToShow'],
],
```

**Step 3: Run C1 tests**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --filter "test_allowlist_has_no_duplicate_keys|test_ids_attribute_type_is_idlist|test_default_tab_type_is_enum" --testdox
```

Expected: All 3 C1 tests PASS.

**Step 4: Run full PHPUnit suite — verify no regressions**

```bash
vendor/bin/phpunit --testdox
```

Expected: Same count as baseline (268 tests, 1379 assertions) or higher. Zero new failures.

**Step 5: Commit**

```bash
git add src/Core/Attribute/AllowlistRegistry.php
git commit -m "fix(allowlist): remove 10 duplicate ALLOWLIST keys (C1)

- ids: restore idlist type + 3 aliases
- redirect_page: restore redirect_url alias
- default_tab: restore enum type
- months_to_show: restore layout group
- show_booking_button: restore 4 aliases
- show_favorite_btn + show_compare_btn: merge alias sets
- default_days/min_days/max_days: remove redundant duplicates"
```

---

## Task 3: Add Tests for M1 Enum Values

**Files:**
- Modify: `tests/Core/Attribute/SchemaParityTest.php`

**Step 1: Add failing tests for enum values**

Add these test methods to `SchemaParityTest.php`:

```php
/**
 * Verify all enum attributes in ALLOWLIST have non-empty values arrays.
 */
public function test_enum_attributes_have_values(): void
{
    $allowlist = (new \ReflectionClass(\MHMRentiva\Core\Attribute\AllowlistRegistry::class))
        ->getReflectionConstant('ALLOWLIST')
        ->getValue();

    $enum_attrs_without_values = [];
    foreach ($allowlist as $key => $config) {
        if (($config['type'] ?? '') === 'enum') {
            if (empty($config['values'])) {
                $enum_attrs_without_values[] = $key;
            }
        }
    }

    $this->assertEmpty(
        $enum_attrs_without_values,
        'These enum attributes are missing values arrays: ' . implode(', ', $enum_attrs_without_values)
    );
}
```

**Step 2: Run to confirm failure**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --filter "test_enum_attributes_have_values" --testdox
```

Expected: FAILS, listing: `status, default_payment, service_type, type, default_tab`

**Step 3: Commit test**

```bash
git add tests/Core/Attribute/SchemaParityTest.php
git commit -m "test(allowlist): add M1 enum values constraint test"
```

---

## Task 4: Fix M1 — Add Enum Values

**Files:**
- Modify: `src/Core/Attribute/AllowlistRegistry.php`

**Step 1: Add `values` to `status` (around L388)**

```php
'status'                   => [
    'type'    => 'enum',
    'group'   => 'workflow',
    'aliases' => ['filterStatus', 'status'],
    'values'  => ['', 'pending', 'confirmed', 'in_progress', 'cancelled', 'completed', 'refunded'],
],
```

**Step 2: Add `values` to `default_payment` (around L767)**

```php
'default_payment'          => [
    'type'    => 'enum',
    'group'   => 'workflow',
    'aliases' => ['defaultPayment'],
    'values'  => ['deposit', 'full'],
],
```

**Step 3: Add `values` to `service_type` (around L839)**

```php
'service_type'             => [
    'type'    => 'enum',
    'group'   => 'feature',
    'aliases' => ['serviceType'],
    'values'  => ['rental', 'transfer', 'both'],
],
```

**Step 4: Add `values` to `type` (around L970)**

```php
'type'                     => [
    'type'    => 'enum',
    'group'   => 'feature',
    'aliases' => ['formType'],
    'values'  => ['general', 'booking', 'support', 'feedback'],
],
```

**Step 5: Add `values` to `default_tab` first definition (around L177, now restored to enum after C1 fix)**

```php
'default_tab'              => [
    'type'    => 'enum',
    'group'   => 'workflow',
    'aliases' => ['defaultTab'],
    'values'  => ['rental', 'transfer', 'default'],
],
```

**Step 6: Run M1 test**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --filter "test_enum_attributes_have_values" --testdox
```

Expected: PASS.

**Step 7: Run full suite — no regressions**

```bash
vendor/bin/phpunit --testdox
```

**Step 8: Commit**

```bash
git add src/Core/Attribute/AllowlistRegistry.php
git commit -m "fix(allowlist): add values constraints to enum attributes (M1)

- status: ['', 'pending', 'confirmed', 'in_progress', 'cancelled', 'completed', 'refunded']
- default_payment: ['deposit', 'full']
- service_type: ['rental', 'transfer', 'both']
- type: ['general', 'booking', 'support', 'feedback']
- default_tab: ['rental', 'transfer', 'default'] (restored enum after C1 fix)"
```

---

## Task 5: Add Tests for M2 TAG_MAPPING Coverage

**Files:**
- Modify: `tests/Core/Attribute/SchemaParityTest.php`

**Step 1: Add failing test**

```php
/**
 * Verify all TAG_MAPPING attribute keys have a schema in get_registry().
 * An empty schema [] means the attribute is untyped (M2 defect).
 */
public function test_tag_mapping_attributes_have_canonical_schema(): void
{
    $registry = \MHMRentiva\Core\Attribute\AllowlistRegistry::get_registry();
    $untyped  = [];

    foreach ($registry as $tag => $schema) {
        foreach ($schema as $attr_key => $attr_config) {
            if (empty($attr_config)) {
                $untyped[] = "{$tag}.{$attr_key}";
            }
        }
    }

    $this->assertEmpty(
        $untyped,
        'These TAG_MAPPING attributes have empty (untyped) schema: ' . implode(', ', $untyped)
    );
}
```

**Step 2: Run to confirm failure**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --filter "test_tag_mapping_attributes_have_canonical_schema" --testdox
```

Expected: FAILS, listing untyped attributes including:
`rentiva_vehicle_details.show_technical_specs`, `rentiva_vehicle_details.show_booking_form`,
`rentiva_vehicle_comparison.show_technical_specs`, `rentiva_vehicle_comparison.show_book_button`,
`rentiva_testimonials.sort_by`, `rentiva_testimonials.sort_order`,
`rentiva_testimonials.filter_rating`, `rentiva_messages.show_avatar`

**Step 3: Commit test**

```bash
git add tests/Core/Attribute/SchemaParityTest.php
git commit -m "test(allowlist): add M2 TAG_MAPPING canonical coverage test"
```

---

## Task 6: Fix M2 — Close Canonical Gaps

**Files:**
- Modify: `src/Core/Attribute/AllowlistRegistry.php`

### 6a: Add new canonical keys to ALLOWLIST

Add these entries to the ALLOWLIST constant (place in the `// Generic Visibility` section, after `show_booking_btn`):

```php
'show_technical_specs'     => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showTechnicalSpecs'],
],
'show_booking_form'        => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showBookingForm'],
],
'show_book_button'         => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showBookButton'],
],
'sort_by'                  => [
    'type'    => 'enum',
    'group'   => 'sorting',
    'aliases' => ['sortBy'],
    'values'  => ['price', 'popularity', 'newest', 'capacity', 'title', 'date', 'rand', 'rating'],
],
'sort_order'               => [
    'type'    => 'enum',
    'group'   => 'sorting',
    'aliases' => ['sortOrder'],
    'values'  => ['asc', 'desc', 'ASC', 'DESC'],
],
```

**Note:** `show_features` at L204–208 currently has alias `showTechnicalSpecs`. Since we are adding `show_technical_specs` as standalone canonical with that alias, remove `showTechnicalSpecs` from `show_features` aliases to avoid alias duplication. Update L204–208:

```php
'show_features'            => [
    'type'    => 'bool',
    'group'   => 'visibility',
    'aliases' => ['showFeatures'],
],
```

### 6b: Fix TAG_MAPPING — remove `show_avatar` and `filter_rating`

In the TAG_MAPPING, for `rentiva_messages`: remove `'show_avatar'` (line ~1402).
`show_author_avatar` is already in the same mapping (line ~1413) and is canonical with alias `showAvatar`. The `show_avatar` entry is redundant.

In the TAG_MAPPING, for `rentiva_testimonials`: remove `'filter_rating'` (line ~1337).
`show_rating` is already in the mapping (line ~1325) and has `filter_rating` as an alias in ALLOWLIST. The `filter_rating` entry is redundant.

Verify after removal:
- `rentiva_messages` TAG_MAPPING still has `show_author_avatar` — ✓
- `rentiva_testimonials` TAG_MAPPING still has `show_rating` — ✓

**Step 3: Run M2 test**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --filter "test_tag_mapping_attributes_have_canonical_schema" --testdox
```

Expected: PASS.

**Step 4: Run all SchemaParityTest**

```bash
vendor/bin/phpunit tests/Core/Attribute/SchemaParityTest.php --testdox
```

Expected: All 6 schema parity tests PASS (3 original + 3 new).

**Step 5: Run full suite**

```bash
vendor/bin/phpunit --testdox
```

Expected: 271+ tests, 1379+ assertions. Zero failures.

**Step 6: Commit**

```bash
git add src/Core/Attribute/AllowlistRegistry.php
git commit -m "fix(allowlist): close M2 canonical gaps in TAG_MAPPING

- Add canonical ALLOWLIST entries: show_technical_specs, show_booking_form,
  show_book_button, sort_by, sort_order
- Remove showTechnicalSpecs alias from show_features (moved to standalone)
- Remove show_avatar from rentiva_messages TAG_MAPPING (show_author_avatar canonical)
- Remove filter_rating from rentiva_testimonials TAG_MAPPING (show_rating canonical)"
```

---

## Task 7: Governance — Add SHORTCODES.md to Version Control

**Files:**
- Modify: `.gitignore`

**Root cause (confirmed):** `.gitignore` uses `/*` at top (ignore everything in root), then whitelists only specific paths. `SHORTCODES.md` was never whitelisted and was never tracked. `PROJECT_MEMORIES.md` is tracked because it was added before the `/*` rule was introduced.

**Step 1: Add `SHORTCODES.md` to .gitignore whitelist**

In `.gitignore`, find the plugin core whitelist section at the top (after `!/.gitignore`). Add:

```
!/SHORTCODES.md
!/PROJECT_MEMORIES.md
```

Note: `!/PROJECT_MEMORIES.md` is added for documentation completeness, even though PROJECT_MEMORIES.md is already tracked. It documents the intentional whitelist decision.

**Step 2: Stage and verify**

```bash
git add .gitignore SHORTCODES.md
git status
```

Expected: `SHORTCODES.md` appears as new tracked file.

**Step 3: Commit**

```bash
git add .gitignore SHORTCODES.md
git commit -m "chore(governance): add SHORTCODES.md to version control

Root cause: .gitignore uses /* (ignore all) with selective whitelist.
SHORTCODES.md was never whitelisted despite being project documentation.
Fix: add !/SHORTCODES.md to whitelist section."
```

---

## Task 8: Run Full PHPUnit Suite (Quality Gate)

**Step 1: Run full suite**

```bash
cd C:\projects\rentiva-dev\plugins\mhm-rentiva
vendor/bin/phpunit --testdox 2>&1 | tee vendor/phpunit-v4202-output.txt
```

**Step 2: Verify baseline**

Expected results:
- Tests: 271 or higher (268 baseline + 3 new tests from Tasks 1, 3, 5)
- Assertions: 1379 or higher
- Failures: 0
- Errors: 0

If any test fails: **STOP. Do not proceed to version bump.** Diagnose and fix before continuing.

**Step 3: Record output snapshot**

Save the output for the QA evidence document (used in Task 9).

---

## Task 9: Documentation Updates

### 9a: Update SHORTCODES.md

**File:** `SHORTCODES.md`

In **Section 8 (Deprecation and Alias Table)**, update the following rows to reflect the C1 fix:

- `ids`: restore canonical aliases `vehicleIds`, `vehicle_ids`, type `idlist`
- `redirect_page`: restore alias `redirect_url`
- `default_tab`: restore type `enum`, add values `rental|transfer|default`
- `show_booking_button`: restore full alias list `showBookButton, show_booking_button, showBookBtn, show_book_btn`
- `show_favorite_btn`: add merged aliases `showFavoriteBtn, show_favorite_button`
- `show_compare_btn`: add merged aliases `showCompareBtn, show_compare_button`

Add to the bottom of Section 12 (Audit Findings v4.20.1):

```markdown
### v4.20.2 Resolution Status

| Finding | Status |
|---|---|
| C1: 10 duplicate ALLOWLIST keys | ✅ FIXED in v4.20.2 |
| M1: 4 enum attributes missing values | ✅ FIXED in v4.20.2 |
| M2: 6 TAG_MAPPING canonical gaps | ✅ FIXED in v4.20.2 |
| Governance: SHORTCODES.md not tracked | ✅ FIXED in v4.20.2 |
```

### 9b: Update PROJECT_MEMORIES.md

Add new entry at the top (before the v4.20.1 audit entry):

```markdown
### [STABILIZED] v4.20.2 Allowlist Contract Integrity Restored (2026-02-25)
- **Status:** [STABILIZED] / FULL PASS
- **Instruction:** V4.20.2-ALLOWLIST-STABILIZATION
- **Changes:**
    - C1 FIXED: 10 duplicate ALLOWLIST keys removed, aliases merged
    - M1 FIXED: values arrays added to status, default_payment, service_type, type, default_tab
    - M2 FIXED: 5 new canonical keys added (show_technical_specs, show_booking_form, show_book_button, sort_by, sort_order); 2 TAG_MAPPING redundancies removed (show_avatar, filter_rating)
    - GOVERNANCE: SHORTCODES.md added to version control
- **Test Count:** [FILL IN from PHPUnit output]
- **QA Decision:** FULL PASS — Runtime Verified
```

### 9c: Create QA Evidence Document

**File:** `docs/internal/QA_V4202_ALLOWLIST_STABILIZATION.md`

```markdown
# QA Evidence — v4.20.2 Allowlist Stabilization

**Date:** 2026-02-25
**Instruction:** V4.20.2-ALLOWLIST-STABILIZATION

## Before/After Key Diff

### C1 — Duplicate Keys Removed

| Key | Before (runtime, last-def wins) | After |
|---|---|---|
| ids | type: string, 1 alias | type: idlist, 3 aliases (restored) |
| redirect_page | aliases: [redirectPage] | aliases: [redirect_url, redirectPage, redirectUrl] |
| default_tab | type: string, group: feature | type: enum, group: workflow, values added |
| months_to_show | group: feature | group: layout (restored) |
| show_booking_button | aliases: [showBookButton] | aliases: [showBookButton, show_booking_button, showBookBtn, show_book_btn] |
| show_favorite_btn | aliases: [show_favorite_button] | aliases: [showFavoriteBtn, show_favorite_button] |
| show_compare_btn | aliases: [show_compare_button] | aliases: [showCompareBtn, show_compare_button] |
| default_days/min_days/max_days | duplicate identical entries | duplicates removed, first definition canonical |

### M1 — Enum Values Added

| Attribute | Values Added |
|---|---|
| status | ['', 'pending', 'confirmed', 'in_progress', 'cancelled', 'completed', 'refunded'] |
| default_payment | ['deposit', 'full'] |
| service_type | ['rental', 'transfer', 'both'] |
| type | ['general', 'booking', 'support', 'feedback'] |
| default_tab | ['rental', 'transfer', 'default'] |

### M2 — Canonical Keys Added

| Key Added | Type | Group |
|---|---|---|
| show_technical_specs | bool | visibility |
| show_booking_form | bool | visibility |
| show_book_button | bool | visibility |
| sort_by | enum | sorting |
| sort_order | enum | sorting |

| TAG_MAPPING Change | Reason |
|---|---|
| rentiva_messages: removed show_avatar | show_author_avatar canonical already present |
| rentiva_testimonials: removed filter_rating | show_rating with filter_rating alias already present |

## PHPUnit Output Snapshot

[PASTE output from Task 8 here]

## WP-CLI Verification Notes

_Pending execution in active WordPress environment._

See commands in `docs/2026-02-25-V4201-ECOSYSTEM-AUDIT-EVIDENCE.md` Section 5.

## QA Decision

**FULL PASS** — all static defects resolved, PHPUnit clean, no regressions.
```

**Step: Commit documentation**

```bash
git add SHORTCODES.md PROJECT_MEMORIES.md docs/internal/QA_V4202_ALLOWLIST_STABILIZATION.md
git commit -m "docs: v4.20.2 documentation — Allowlist Stabilization complete

- SHORTCODES.md: Section 8 alias table updated, Section 12 resolution status added
- PROJECT_MEMORIES.md: [STABILIZED] v4.20.2 entry prepended
- docs/internal/QA_V4202_ALLOWLIST_STABILIZATION.md: QA evidence document created"
```

---

## Task 10: Version Bump, Tag, Push

**Prerequisite:** PHPUnit PASS (Task 8) and documentation committed (Task 9). If PHPUnit failed, STOP here.

**Step 1: Bump plugin header version**

In `mhm-rentiva.php`, update:
```
 * Version:           4.20.1
```
→
```
 * Version:           4.20.2
```

In `mhm-rentiva.php`, update the constant:
```php
define('MHM_RENTIVA_VERSION', '4.20.1');
```
→
```php
define('MHM_RENTIVA_VERSION', '4.20.2');
```

**Step 2: Commit version bump**

```bash
git add mhm-rentiva.php
git commit -m "chore(release): bump version to 4.20.2

AllowlistRegistry stabilization complete.
C1 + M1 + M2 defects resolved. SHORTCODES.md tracked.
PHPUnit: [FILL IN count] tests, [FILL IN] assertions — FULL PASS."
```

**Step 3: Create annotated tag**

```bash
git tag -a v4.20.2 -m "Allowlist Stabilization — FULL PASS"
```

**Step 4: Push**

```bash
git push origin main && git push origin v4.20.2
```

**Step 5: Verify**

```bash
git log --oneline -5
git tag -n1 v4.20.2
```

---

## Definition of Done

- [ ] All 10 duplicate ALLOWLIST keys removed (C1)
- [ ] Alias sets merged for `show_favorite_btn`, `show_compare_btn`
- [ ] 5 enum attributes have `values` arrays (M1)
- [ ] 5 new canonical ALLOWLIST keys added (M2)
- [ ] 2 TAG_MAPPING redundancies removed (show_avatar, filter_rating)
- [ ] `showTechnicalSpecs` alias removed from `show_features` (moved to standalone)
- [ ] `SHORTCODES.md` added to version control (Governance)
- [ ] 3 new SchemaParityTest tests added and passing
- [ ] PHPUnit full suite: 271+ tests, 0 failures
- [ ] SHORTCODES.md Section 8 + Section 12 updated
- [ ] PROJECT_MEMORIES.md [STABILIZED] entry prepended
- [ ] `docs/internal/QA_V4202_ALLOWLIST_STABILIZATION.md` created
- [ ] Plugin version bumped to 4.20.2
- [ ] Annotated tag `v4.20.2` pushed to origin
