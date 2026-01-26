---
description: Analysis, Auto-Fixing, Database Cleanup, Dead Code Removal, and Final Testing.
---

# MHM Rentiva - Full Optimization Workflow (v2.0)

This workflow focuses on performance tuning, database efficiency, and resource management within the WordPress ecosystem.

## 1. Database Optimization
*Goal: Minimize SQL load and prevent bloat.*

1.  **Query Efficiency:**
    * Replace direct SQL (`$wpdb`) with `WP_Query` or `get_posts()` where possible.
    * Ensure all custom tables have proper Indexes (especially on `booking_id`, `vehicle_id`, `date`).

2.  **Data Autoloading:**
    * Check `wp_options` table usage.
    * Ensure large option arrays have `autoload='no'` to prevent slowing down every page load.

## 2. Caching Strategy (Object Cache & Transients)
*Goal: Reduce database hits for repeated data.*

1.  **Transient Implementation:**
    * Identify expensive queries (e.g., external API calls, complex availability checks).
    * Wrap them in `set_transient()` and `get_transient()`.
    * **Rule:** Always set an expiration time (e.g., `HOUR_IN_SECONDS`).

2.  **Object Caching:**
    * Use `wp_cache_set()` and `wp_cache_get()` for frequent, short-lived data within a single request.
    * Group cache keys logically (e.g., `mhm_rentiva_vehicles`).

## 3. Asset Optimization (JS/CSS)
*Goal: Reduce frontend payload and improve Core Web Vitals.*

1.  **Conditional Loading:**
    * **Rule:** Never load scripts/styles globally (on all site pages).
    * **Action:** Use `wp_enqueue_scripts` hook with conditional checks:
        ```php
        if ( is_page('booking') || has_shortcode($content, 'rentiva_booking') ) {
            wp_enqueue_script('mhm-rentiva-app');
        }
        ```

2.  **Minification & Dependencies:**
    * Ensure production builds use `.min.js` and `.min.css`.
    * Declare dependencies correctly (e.g., `['jquery']`) to prevent race conditions.

## 4. PHP Performance
1.  **Code Profiling:**
    * Use Query Monitor to identify slow PHP functions.
    * Refactor nested loops or recursive functions causing high CPU usage.

2.  **Autoloader Optimization:**
    * Ensure Composer autoloader is optimized (`composer dump-autoload -o`).

## 5. Final Verification
* Run a final **Audit Code** cycle after optimization to ensure no regressions were introduced.