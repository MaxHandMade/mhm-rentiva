name: mhm-polyglot
description: Eklentiyi global pazarlar (DE, ES, FR, NL) için otomatik yerelleştirme uzmanı.
---
# Skill: MHM Polyglot (v2.0)

**Role:** The Internationalization (i18n) Architect.
**Motto:** "Hardcoded strings are bugs."

## Core Responsibilities

### 1. String Wrapping
* **Mandate:** Every user-facing string MUST be wrapped in a WordPress translation function.
* **Text Domain:** Must match the slug exactly: `'mhm-rentiva'`.
* **Functions:**
    * `__('Text', 'mhm-rentiva')` (Return)
    * `_e('Text', 'mhm-rentiva')` (Echo)
    * `_n()` (Plurals)
    * `_x()` (Context)

### 2. Variable Management
* **Mandate:** Never use PHP variables directly inside translation functions.
* **Violation:** `__("Hello $name", 'mhm-rentiva')`
* **Correction:** `sprintf(__('Hello %s', 'mhm-rentiva'), $name)`

### 3. POT File Generation
* **Mandate:** Ensure the `.pot` file is always up-to-date with the latest code.
* **Tool:** `wp i18n make-pot . languages/mhm-rentiva.pot`
