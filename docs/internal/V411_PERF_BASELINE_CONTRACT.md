# Performance Baseline Contract (v4.11.0 Phase 4B)

**Status:** ACTIVE 🔍
**Objective:** Establish a measurable performance baseline and define improvement targets.

---

## 1. Profiling Methodology
Measurements will be performed in a standardized environment (Local XAMPP) with the following baseline tools:
- `Query Monitor` (Logic-level query analysis)
- `microtime(true)` (High-resolution TTFB/Execution timing)
- `memory_get_peak_usage()` (Memory footprint)
- WP-CLI `scaffold` / `profile` (if available)

## 2. Target Pages & Scenarios
The following pages represent the highest traffic and most complex rendering paths:

| Page | Type | Logic Payload |
| :--- | :--- | :--- |
| **Vehicles List** | Grid/List | Default attribute mapping, 10+ Meta Queries |
| **Search Results** | Search/Filter | Dynamic date/location overlap logic |
| **Booking Form** | Transactional | Multiple transient hits, date calculation |
| **Single Vehicle** | Detail | Heavy post meta loading, related vehicles |

## 3. Capture Baseline Metrics (Observed 18.02.2026)
| Scenario | Time (ms) | Queries | Assets (S/J) |
| :--- | :--- | :--- | :--- |
| **Vehicles List (6)** | 18.45 | 21 | 2/1 |
| **Search Results (Empty)** | 11.20 | 1 | 1/1 |
| **Booking Form** | 6.49 | 2 | 2/1 |

> [!IMPORTANT]
> **Bottleneck Identified:** The "Vehicles List" scenario executes 21 queries for just 6 items. SQL Dump analysis confirms N+1 meta fetching pattern. Target: Reduce to < 10 queries via batch priming.

## 4. Optimization "Allowed List"
The following changes are authorized without public contract breaks:
- Query optimization (Joins instead of loops).
- Transient caching implementation for heavy calculations.
- Class-level memoization.
- Batching of meta-data fetching.
- Asset localization/conditional loading (UI Parity must remain 100%).

## 5. Success Thresholds
- **Minimum 15% Reduction** in total query time on main Search Results.
- **Sub-100ms** rendering for the Vehicles List (attributes only).
- **0 Regressions** in existing functional tests (PHPUnit).

---
*Authored by Performance Architect Agent.*
