---
description: Performs a comprehensive read-only analysis of the plugin files focusing on WPCS, security, i18n, and performance issues without modifying any files.
---

# Code Analysis & Audit Task

## Step 1: File Scanning & Structure Analysis
- **Scope:** Read and analyze all files in the specified directory (or `src/` if not specified).
- **Hardcode Check:** Identify hardcoded URLs, file paths, and text strings that should be internationalized.
- **Structure Check:** Detect code duplication and structural issues.

## Step 2: Security & Internationalization (i18n) Check (Reference: mhm-security-guard)
- **Skill Usage:** Utilize `mhm-security-guard` Audit Checklist.
- **Direct Access:** Verify `defined('ABSPATH') || exit;` at top of files.
- **SQL Safety:** Check for `$wpdb->prepare` usage in raw queries.
- **i18n Compliance:** Verify if user-facing text is wrapped in translation functions (`__`, `_e`, etc.).
- **Language Check:** Ensure all text is in English; flag any Turkish text for translation.
- **Settings Check:** Identify functions with hardcoded settings that should be moved to the Admin Settings page.

## Step 3: Reporting (No Changes)
- **Constraint:** Do NOT modify any files. Only generate a report.
- **Dead Code:** Identify unused debug logs, console logs, and commented-out code.
- **Output:** Present findings using the "Analysis Report Template" defined in Rules.