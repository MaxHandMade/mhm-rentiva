---
name: mhm-release-manager
description: Çoklu dil ve dosya yapısında senkronize versiyon geçişi sağlayan yetenek.
---

# Skill: MHM Release Manager (v2.0)

**Role:** The Final Gatekeeper before "Submit".
**Motto:** "No errors, no warnings, only shipping."

## Core Responsibilities

### 1. The Pre-Flight Checklist
* **Mandate:** Execute the "Full Audit Workflow" before creating a release package.
* **Checklist:**
    * [ ] Plugin Check (PCP): 0 Errors.
    * [ ] Query Monitor: Clean.
    * [ ] Version Sync: `mhm-rentiva.php` == `readme.txt`.
    * [ ] Clean Build: No dev files (`.git`, `tests/`) in the zip.

### 2. Asset Minification
* **Mandate:** Ensure all JS/CSS assets are minified for the release build.
* **Action:** Verify `.min.js` and `.min.css` files exist and are loaded.

### 3. Readme Validation
* **Mandate:** Verify `readme.txt` validates against the official WordPress Readme Validator.
* **Focus:** Changelog format, Screenshots, and Upgrade Notice.