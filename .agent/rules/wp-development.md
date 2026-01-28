---
trigger: always_on
---

# MHM Rentiva - Development Rules & Standards (v2.0)

This document defines the development standards, code quality rules, and WordPress.org compliance requirements for the MHM Rentiva project.

**Core Philosophy:** "Adapting modern PHP architecture to fit the strict ecosystem rules of WordPress (The WordPress Way)."

---

## 1. WordPress Repo Compliance (The Golden Rules)
*Strict adherence to Plugin Check (PCP) and Review Team standards is mandatory.*

### 1.1. Header and Readme Synchronization
* **Rule:** Metadata in the main plugin file (`mhm-rentiva.php`) MUST match the `readme.txt` file exactly.
* **Mandatory Fields:** `Tested up to`, `Requires PHP`, `Stable tag`, and `Version`.
* **Violation Example:** `readme.txt` says "Stable tag: 4.5" while the main file says `Version: 4.4`.

### 1.2. Text Domain and i18n
* **Rule:** All user-facing strings MUST be wrapped in translation functions.
* **Text Domain:** MUST use the string literal `'mhm-rentiva'` explicitly. NEVER use variables (`$domain`) or constants (`CONST_DOMAIN`) for the text domain.
* **Examples:**
    * ❌ **Wrong:** `echo 'Vehicle not found';`
    * ❌ **Wrong:** `__('Vehicle', $this->domain);`
    * ✅ **Correct:** `_e('Vehicle not found', 'mhm-rentiva');`

### 1.3. Prefixing (Namespace Protection)
* **Rule:** All items in the global namespace (Functions, Classes, Hooks, Database Tables, Option Names) MUST be unique.
* **Prefix:** `mhm_rentiva_` (snake_case) or `MHMRentiva` (PascalCase).
* **Reason:** Prevents fatal errors if another plugin uses generic names like `BookingForm`.

---

## 2. Security Rules (Security First)
*Trust no data; validate everything.*

### 2.1. Sanitization (Input Cleaning)
* **Rule:** NO data received from users (`$_POST`, `$_GET`, `$_REQUEST`) can be processed without sanitization.
* **Functions:**
    * Strings: `sanitize_text_field()`
    * Emails: `sanitize_email()`
    * Keys/Slugs: `sanitize_key()`
    * Integers: `absint()`

### 2.2. Escaping (Output Security)
* **Rule:** ALL data output to the screen (echo) MUST be passed through an escaping function contextually appropriate for the output type.
* **Functions:**
    * Inside HTML: `esc_html()`, `esc_html_e()`
    * HTML Attributes: `esc_attr()`, `esc_attr_e()`
    * URLs: `esc_url()`
    * JavaScript/JSON: `wp_json_encode()`

### 2.3. Nonces and Capabilities
* **Rule:** All form submissions and AJAX requests MUST include `nonce` verification.
* **Rule:** Every admin action MUST verify user capabilities using `current_user_can('manage_options')` (or the relevant capability).

---

## 3. Code Architecture & Modern PHP
*Standards based on WordPress 6.2+ and PHP 7.4+.*

### 3.1. Strict Types
* **Rule:** All PHP files MUST start with `declare(strict_types=1);`.
* **Rule:** Function parameters and return types MUST be explicitly typed.
    ```php
    public function get_price(int $vehicle_id): float { ... }
    ```

### 3.2. Prevent Direct Access
* **Rule:** Every PHP file (including Class files) MUST verify `ABSPATH` at the very beginning.
    ```php
    if (!defined('ABSPATH')) { exit; }
    ```

### 3.3. Database (SQL) Security
* **Rule:** Direct raw SQL queries are strictly PROHIBITED.
* **Rule:** `$wpdb->prepare()` usage is **MANDATORY** for any query containing variables.
    * ❌ **Wrong:** `$wpdb->query("SELECT * FROM table WHERE id = $id");`
    * ✅ **Correct:** `$wpdb->query($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id));`

---

## 4. File and Folder Structure
* **Src/**: Core business logic (Classes, Controllers).
* **Assets/**: JS, CSS, Images (Publicly accessible).
* **Templates/**: Frontend HTML parts (Partial views).
* **Languages/**: .pot and .mo files.

---

## 5. Anti-Patterns (MUST AVOID)
1.  **Extract:** Never use the `extract()` function.
2.  **Eval:** The `eval()` function is strictly forbidden.
3.  **Direct Global:** Avoid `global $wpdb;` where possible; prefer Dependency Injection or Singleton instances.
4.  **Generic Naming:** Generic names like `function log_error()` are forbidden. Use `mhm_rentiva_log_error()`.

## 6. Pre-Commit Checklist
Before committing code, the following must be verified:
- [ ] Does the **Plugin Check (PCP)** tool return 0 Errors?
- [ ] Is **Query Monitor** free of PHP Notices/Warnings?
- [ ] Do `mhm-rentiva.php` and `readme.txt` versions match?
- [ ] Are all new strings wrapped in `__('text', 'mhm-rentiva')`?