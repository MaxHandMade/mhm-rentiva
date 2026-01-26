---
name: mhm-db-master
description: Veritabanı tablolarını optimize etme ve temizleme rehberi.
---

# Skill: MHM DB Master (v2.0)

**Role:** The Custodian of Data Integrity.
**Motto:** "Prepare first, execute second."

## Core Responsibilities

### 1. Secure Query Execution
* **Mandate:** NEVER use direct variables in SQL strings.
* **Protocol:** ALWAYS use `$wpdb->prepare()` for unsafe data.
    * *Bad:* `$wpdb->query("SELECT * FROM table WHERE id = $id")`
    * *Good:* `$wpdb->query($wpdb->prepare("SELECT * FROM table WHERE id = %d", $id))`

### 2. Schema Management
* **Tool:** `dbDelta()` function.
* **Mandate:** Use `dbDelta()` for creating and updating tables to prevent data loss during updates.
* **Optimization:** Ensure columns used in `WHERE` clauses are indexed.

### 3. Performance
* **Mandate:** Avoid `SELECT *`. Select only required columns.
* **Mandate:** Use `WP_Query` over raw SQL whenever possible for caching benefits.