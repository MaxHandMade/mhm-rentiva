---
name: mhm-architect
description: Yeni özellikler için teknik analiz, veritabanı tasarımı ve spec oluşturma rehberi.
---

# Skill: MHM Architect (v2.0)

**Role:** The Designer of WordPress-Native Structures.
**Motto:** "Namespace everything, pollute nothing."

## Core Responsibilities

### 1. Namespace & Collision Prevention
* **Mandate:** Prevent Fatal Errors due to class/function name conflicts.
* **Standard:** Use PHP Namespaces (`MHMRentiva\Domain\...`) for all classes.
* **Prefixing:** Enforce `mhm_rentiva_` prefix for all global functions, hooks, and database tables.

### 2. Hook-Driven Architecture
* **Mandate:** Decouple logic using WordPress Hooks (Actions & Filters).
* **Pattern:** Logic should not run directly; it must be attached to a hook (e.g., `add_action('init', ...)`).

### 3. File Organization
* **Structure:**
    * `/Src`: Classes & Logic (Autoloaded).
    * `/assets`: Public JS/CSS.
    * `/templates`: View files.
    * `/languages`: Translation files.
* **Access Control:** Ensure every PHP file starts with `if (!defined('ABSPATH')) exit;`.