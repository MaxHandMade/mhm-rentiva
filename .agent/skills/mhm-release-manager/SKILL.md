---
name: mhm-release-manager
description: Runs pre-flight checklist, asset minification verification, and readme validation for MHM Rentiva WordPress plugin releases. Use when preparing a release, creating a release package, before submitting to WordPress.org, or when the user mentions release, PCP, Plugin Check, readme validation, or pre-flight.
---

# MHM Release Manager

**Role:** The Final Gatekeeper before "Submit".  
**Motto:** "No errors, no warnings, only shipping."

## When to Use This Skill

Apply this skill when:
- Preparing a plugin release or release package
- Before submitting to WordPress.org
- User asks for pre-flight checks, release validation, or "ready to ship"
- Syncing version numbers across `mhm-rentiva.php` and `readme.txt`
- Verifying assets are minified for production
- Validating `readme.txt` (changelog, screenshots, upgrade notice)
- **Technical Memory Sync:** Ensuring `PROJECT_MEMORIES.md` is updated and locked for the current release
- **Post-release:** Triggering documentation and blog updates from `changelog-tr.json`

## Core Responsibilities

### 1. Pre-Flight Checklist

**Mandate:** Execute the Full Audit Workflow before creating a release package. Do not ship until all items pass.

**Checklist (copy and track):**

```
Pre-Flight Progress:
- [ ] Plugin Check (PCP): 0 Errors, 0 Warnings
- [ ] Query Monitor: Clean (no PHP errors, no 404s on scripts/styles)
- [ ] Version Sync: mhm-rentiva.php == readme.txt (Stable tag)
- [ ] Technical Memory: `PROJECT_MEMORIES.md` task locked/labeled with version
- [ ] Clean Build: No dev files (.git, tests/, .phpunit.result.cache) in zip
```

**Actions:**

| Item | How to Verify |
|------|----------------|
| **Plugin Check (PCP)** | Tools → Plugin Check → select "MHM Rentiva" → run all. Zero errors and zero warnings required. |
| **Query Monitor** | Load plugin pages, open Query Monitor. PHP errors empty; no 404s for enqueued JS/CSS. |
| **Version Sync** | `Version` in `mhm-rentiva.php` must match `Stable tag` in `readme.txt` exactly. |
| **Clean Build** | Exclusion list when building zip: `.git`, `tests/`, `.phpunit.result.cache`, dev configs. |

**Full Audit Workflow:** For detailed audit steps (static analysis, runtime checks, metadata sync), see [.agent/workflows/audit-code.md](.agent/workflows/audit-code.md).

### 2. Asset Minification

**Mandate:** Ensure all JS/CSS assets are minified for the release build.

**Actions:**
- Verify `.min.js` and `.min.css` files exist for each enqueued asset (or that the build process produces them).
- Confirm production enqueues use minified handles (e.g. `mhm-rentiva-vehicles-grid.min.js` not `mhm-rentiva-vehicles-grid.js`).
- Check `wp_enqueue_script` / `wp_enqueue_style` calls; in release, `SCRIPT_DEBUG` should not force unminified sources.

### 3. Readme Validation

**Mandate:** Verify `readme.txt` validates against the official WordPress Readme Validator.

**Validator:** [WordPress.org Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)

**Focus areas:**
- **Changelog:** Valid `== X.Y.Z ==` headers, date format, bullet format.
- **Screenshots:** Correct `== Screenshots ==` section and naming if used.
- **Upgrade Notice:** Proper `= X.Y.Z =` format under `== Upgrade Notice ==`.

**Action:** Paste `readme.txt` content into the validator, fix any reported issues, then re-validate until clean.

## Release Decision

- **APPROVE:** All pre-flight items passed, assets minified, readme valid. Ready to build zip and submit.
- **REJECT:** Any pre-flight failure, missing minified assets, or readme validation errors. Fix before release.

## Anti-Patterns to Avoid

1. **Skipping audit:** Never create a release package without completing the pre-flight checklist.
2. **Version drift:** Never ship with mismatched `mhm-rentiva.php` Version and `readme.txt` Stable tag.
3. **Dev files in zip:** Never include `.git`, `tests/`, or PHPUnit/dev artifacts in the release zip.
4. **Unminified assets in production:** Release build must use minified JS/CSS unless explicitly documented otherwise.
## Post-Release İş Akışı

Sürüm başarıyla yayınlandıktan ve `changelog-tr.json` güncellendikten sonra aşağıdaki adımlar izlenmelidir:

1. **Blog Yazısı:** `mhm-doc-writer` skill'ini kullanarak yeni sürüm için blog yazısını oluşturun. Yazı içeriğini zenginleştirmek için `PROJECT_MEMORIES.md` dosyasındaki "Key Decisions" ve "Files Modified" bölümlerini referans alın.
2. **Dokümantasyon:** Eğer yeni bir özellik eklendiyse ilgili döküman dosyasını güncelleyin.
3. **Senkronizasyon:** `mhm-git-ops` skill'ini kullanarak `website` reposundaki değişiklikleri GitHub'a pushlayın.
