# M1 Cache Policy

**Generated:** 2026-02-14
**Source Version:** 4.9.8
**Context:** M1 Shortcode Engine Audit & Optimization

This document defines the **Split-Layer Caching Strategy** for MHM Rentiva shortcodes and data.

## 1. Caching Layers

### Layer 1: output HTML Cache (Presentation)
*   **Manager:** `AbstractShortcode`
*   **Storage:** WP Transients
*   **Key format:** `mhm_rv_cache_v_{ver}_{hash}`
    *   `ver`: Plugin Version / Asset Version
    *   `hash`: MD5 of (Attributes + Page ID + User ID + Is Admin + Lang + Theme)
*   **TTL:** **5 Minutes** (300s) default.
*   **Purpose:** drastic reduction of PHP processing for render loops.
*   **Invalidation:** Auto-expiration or Version change.

### Layer 2: Data Cache (Logic)
*   **Manager:** `PerformanceHelper`
*   **Storage:** WP Transients (Wrapper with Tags)
*   **Key prefix:** `_transient_mhm_shortcode_`
*   **Structure:** `['data' => ..., 'tags' => [...], 'timestamp' => ...]`
*   **Purpose:** Prevent expensive SQL queries (e.g., Availability calculation, Vehicle lists).

## 2. Standard TTLs

| Data Type | TTL | Invalidation Trigger |
| :--- | :--- | :--- |
| **Shortcode HTML** | 5 mins | Version Bump / Time |
| **Availability Matrix** | 60 sec | `save_post_vehicle_booking` |
| **Vehicle Lists** | 30 mins | `save_post_vehicle` |
| **Feature Lists** | 1 hour | Plugin Update |

## 3. Invalidation Protocol (Tag-Based)

`PerformanceHelper` implements a custom tag invalidation system using direct SQL `DELETE`.

*   **Vehicle Updates:** MUST invalidate tag `vehicle_{id}`.
*   **Global Updates:** MUST invalidate tag `vehicles` (for lists).
*   **Booking Updates:** MUST invalidate tag `vehicle_{id}` (triggers availability refresh).

## 4. Security & Isolation

1.  **User Isolation:** HTML Cache keys **MUST** include `get_current_user_id()` to prevent data leakage between users (e.g., "My Bookings" data).
2.  **Context Isolation:** Keys include Page ID to support context-aware rendering.
3.  **Language Isolation:** Keys include `get_locale()` to support localized content.

## 5. Performance Note
The Tag-Based Invalidation uses `LIKE %...%` queries on the options table. While functionally correct for keeping data fresh, this is an O(N) operation on the options table size.
*   **Risk:** low on typical installs, medium on massive multisites.
*   **Mitigation:** TTLs are short (60s) for volatile data to ensure eventual consistency even if invalidation misses.
