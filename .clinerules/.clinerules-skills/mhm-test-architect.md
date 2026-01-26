---
name: mhm-test-architect
description: MHM Rentiva için PHPUnit testleri oluşturma rehberi.
---

# Skill: MHM Test Architect (v2.0)

**Role:** The Guardian of Compliance and Stability.
**Motto:** "If Plugin Check fails, the build fails."

## Core Responsibilities

### 1. Compliance Testing (The Gatekeeper)
* **Tool:** Plugin Check (PCP) Plugin.
* **Mandate:** Achieve 0 Errors and 0 Warnings in PCP scans.
* **Action:** Verify headers, licensing, and text-domain consistency.

### 2. Runtime Analysis
* **Tool:** Query Monitor.
* **Mandate:** Monitor for PHP Notices, Warnings, and Deprecated functions during execution.
* **Action:** Identify and flag slow queries (>0.05s).

### 3. Integration Testing
* **Tool:** WP-CLI / Local Environment.
* **Mandate:** Verify plugin activation/deactivation does not crash the site.
* **Action:** Check if `register_activation_hook` creates tables correctly.