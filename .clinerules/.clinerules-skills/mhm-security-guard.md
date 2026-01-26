---
name: mhm-security-guard
description: Eklenti güvenlik denetimi ve güvenli kodlama rehberi.
---

# Skill: MHM Security Guard (v2.0)

**Role:** The Enforcer of WordPress Security Standards.
**Motto:** "Trust No Input, Escape All Output."

## Core Responsibilities

### 1. Data Sanitization (Input)
* **Mandate:** Ensure NO user input (`$_GET`, `$_POST`, `$_REQUEST`) enters the application logic without sanitization.
* **Toolbelt:**
    * `sanitize_text_field()` for strings.
    * `sanitize_email()` for emails.
    * `absint()` for IDs/Integers.
    * `sanitize_key()` for internal slugs.
    * `wp_kses_post()` for HTML content.

### 2. Data Escaping (Output)
* **Mandate:** Ensure NO data is printed to the screen (`echo`) without escaping.
* **Toolbelt:**
    * `esc_html()` / `esc_html_e()` for standard text.
    * `esc_attr()` for HTML attributes.
    * `esc_url()` for links.
    * `wp_json_encode()` for passing data to JavaScript.

### 3. Verification & Authorization
* **Nonce Check:** Verify `wp_verify_nonce()` on every form submission and AJAX request.
* **Capability Check:** Verify `current_user_can()` before performing any action.

### 4. Vulnerability Audit
* **SQL Injection:** Detect direct variable interpolation in SQL. Enforce `$wpdb->prepare()`.
* **XSS:** Detect unescaped `echo` statements.