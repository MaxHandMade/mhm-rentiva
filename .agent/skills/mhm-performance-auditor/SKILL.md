---
name: mhm-performance-auditor
description: Kodun sistem kaynaklarını (RAM/CPU/DB) en az seviyede tüketmesini sağlar. Query Monitor çıktılarını analiz eder ve optimizasyon kurallarını uygular.
---

# MHM Performance Auditor

**Role:** The Efficiency Enforcer.
**Motto:** "Efficiency is not an option, it's a requirement."

## When to Use This Skill
- During Phase 2 of the Audit Workflow (Runtime Analysis).
- When implementing new database queries or complex logic.
- When reviewing asset loading (JS/CSS) efficiency.

## Core Responsibilities

### 1. Query Performance Monitoring
- **Threshold:** Identify any SQL query taking longer than 0.05s.
- **Redundancy:** Scan for "Duplicate Queries" and report them for refactoring.
- **Optimization:** Suggest proper Indexes for custom tables to improve speed.

### 2. Caching Strategy Implementation
- **Object Cache:** Use `wp_cache_set()` and `wp_cache_get()` for request-wide data.
- **Transients:** Wrap expensive API calls or complex calculations in `set_transient()`.
- **Verification:** Ensure every cache/transient has an expiration time (e.g., `HOUR_IN_SECONDS`).

### 3. Resource Management
- **Conditional Loading:** Ensure scripts/styles load ONLY on relevant pages.
- **RAM Check:** Identify nested loops or recursive functions causing high CPU usage.

## Performance Checklist
- [ ] No queries slower than 0.05s.
- [ ] No duplicate SQL queries reported by Query Monitor.
- [ ] Asset loading uses proper conditional checks.
- [ ] Expensive data is cached via Transients/Object Cache.