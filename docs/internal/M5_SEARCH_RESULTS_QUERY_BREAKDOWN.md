# M5 Search Results Query Breakdown

**Date:** 2026-02-14
**Scenario:** `[rentiva_search_results]` (Hot Run)
**Total Queries:** 6 (Persistent)

## 1. The Queries (Meta Discovery)

All 6 queries are executed by `MHMRentiva\Admin\Frontend\Shortcodes\SearchResults::get_filter_options`. They scan the `wp_postmeta` table to build dynamic filter dropdowns.

| ID | Purpose | SQL (Simplified) | Time (ms) | Cacheable? |
| :--- | :--- | :--- | :--- | :--- |
| 1 | **Fuel Type** | `SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_fuel_type' ...` | ~2.3 | **YES** (Global) |
| 2 | **Transmission** | `SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_transmission' ...` | ~2.4 | **YES** (Global) |
| 3 | **Seats** | `SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_seats' ...` | ~2.0 | **YES** (Global) |
| 4 | **Brand** | `SELECT DISTINCT meta_value FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_brand' ...` | ~2.1 | **YES** (Global) |
| 5 | **Year Range** | `SELECT MIN(...), MAX(...) FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_year' ...` | ~0.4 | **YES** (Global) |
| 6 | **Price Range** | `SELECT MIN(...), MAX(...) FROM wp_postmeta WHERE meta_key = '_mhm_rentiva_price_per_day' ...` | ~0.4 | **YES** (Global) |

## 2. Root Cause Analysis
*   **Caller:** `SearchResults::get_filter_options()`
*   **Behavior:** The code fetches fresh distinct values on *every* request to ensure the sidebar filters match available inventory.
*   **Issue:** It lacks a caching layer (Transient or Object Cache). Even in a "Hot" run, these queries hit the DB.
*   **Impact:** 
    *   Adds ~10ms overhead (local DB).
    *   Scales poorly with `wp_postmeta` size (table scans).
    *   Blocks scalability on high-traffic search pages.

## 3. Optimization Strategy
*   **Solution:** Wrap the result of `get_filter_options()` in a **Transient** (e.g., `mhm_rentiva_search_filters_cache`).
*   **Expiration:** Long (e.g., 24 hours) or indefinite.
*   **Invalidation:** Clear this transient on:
    *   `save_post_product` (Vehicle update/insert).
    *   `delete_post` (Vehicle deletion).
*   **Target:** 6 Queries -> **0 Queries** (Cache Hit).
