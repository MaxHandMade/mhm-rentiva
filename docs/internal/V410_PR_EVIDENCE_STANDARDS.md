# 🔒 PR Evidence Requirements (Mandatory for v4.10.0 Parity Cleanup)

## Purpose
To ensure deterministic Block ↔ Shortcode parity before introducing the Canonical Attribute Mapper. 
**No parity-related PR may be merged without the evidence requirements defined below.**

---

## 1. Default Parity Diff Table (Mandatory)
Every parity PR must include a **Default Parity Diff** section in the PR description.

### Required Sources
- **Shortcode defaults reference:** `ClassName::get_default_attributes()` (include file + line reference)
- **Block defaults reference:** `assets/blocks/<slug>/block.json`

### Required Table Format
| Key | Shortcode Default | Block Default (Before) | Block Default (After) | Match After |
| :--- | :--- | :--- | :--- | :--- |
| `limit` | `10` | `12` | `10` | YES |
| `show_filters` | `1` | `(missing)` | `true` | YES |

**Rules:**
- If attribute did not exist previously → use `(missing)`
- If unchanged → Before = After
- **"Match After" must be YES for all keys.**
- PRs without this table will be rejected.

---

## 2. Type Normalization Disclosure (Mandatory)
Each PR must document:
- **Where boolean normalization occurs** (BlockRegistry / Mapper / Shortcode class)
- **Expected normalization behavior:**
  - `true` / `"true"` / `1` / `"1"`  →  `'1'`
  - `false` / `"false"` / `0` / `"0"` →  `'0'`
- If type handling was modified, include before/after explanation.

---

## 3. M4 Hard Gate Evidence (Mandatory)
Each PR must include CLI verification logs:
- `composer run phpcs`
- `composer run plugin-check:release`
- `vendor/bin/phpunit`

**Include:**
- Exit code
- Error count summary
- PHPUnit pass count
- **No evidence → no merge.**

---

## 4. Smoke Test Confirmation
Each PR must confirm:
- Gutenberg SSR preview renders correctly
- Attribute changes reflect in preview
- Frontend output matches shortcode output (functional parity)
- If toggle-related parity is implemented, visual confirmation must be noted.

---

## 5. Reviewer Enforcement Policy
If any of the following are missing:
1. Default Parity Diff table
2. Type normalization note
3. CLI hard gate logs
4. Smoke confirmation

The PR must be marked: **❌ Rejected — Evidence Incomplete**. No exceptions.

*This enforcement standard remains active until all parity gaps are resolved and the Canonical Attribute Mapper is implemented.*
