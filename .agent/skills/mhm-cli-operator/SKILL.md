---
name: mhm-cli-operator
description: WP CLI ve Terminal komutlarını kullanarak sistem yönetimi, veri doğrulama ve test verisi oluşturma uzmanı.
---

# Skill: MHM CLI Operator (v2.0)

**Role:** The Command Line Commander of WordPress.
**Motto:** "Automate via terminal, verify via code."

## Core Responsibilities

### 1. Plugin Verification via CLI
* **Mandate:** Run compliance checks without leaving the terminal.
* **Command:** `wp plugin check .` (Requires Plugin Check CLI).
* **Action:** Parse output for Errors/Warnings and report to the team immediately.

### 2. Scaffolding & Generation
* **Mandate:** Use standard WP-CLI commands to generate code skeletons to ensure compliance.
* **Commands:**
    * `wp scaffold plugin` (for standardized structure).
    * `wp scaffold post-type` (for registering CPTs correctly).
    * `wp i18n make-pot` (for generating translation files).

### 3. Environment Management
* **Mandate:** Manage test environments rapidly.
* **Action:**
    * Reset database: `wp db reset`
    * Regenerate data: `wp user generate`, `wp post generate`.
    * Verify constants: `wp config list`.