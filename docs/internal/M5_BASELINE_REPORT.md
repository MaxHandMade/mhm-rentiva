# M5 Performance Baseline Report

**Date:** 2026-02-14
**Version:** v1.3.5 (Post-M2)
**Environment:** Local Development (XAMPP/Windows) - CLI Benchmark

## 1. Scenario: Booking Form (`[rentiva_booking_form]`)

### 1.1 Metrics
| Run Type | TTFB (ms) | Peak Memory (MB) | Query Count | Asset Count (CSS/JS) |
| :--- | :--- | :--- | :--- | :--- |
| **Cold** | 17.02 | 0 | 19 | CSS:3 / JS:1 |
| **Warm** | 3.53 | 0 | 0 | CSS:3 / JS:1 |
| **Hot** | 4.53 | 0 | 0 | CSS:3 / JS:1 |

### 1.2 Observations
*   **Performance:** Extremely fast (<20ms).
*   **Caching:** 100% effective (0 queries warm).
*   **Assets:** Minimal footprint (3 CSS, 1 JS).

---

## 2. Scenario: Search Results (`[rentiva_search_results]`)

### 2.1 Metrics
| Run Type | TTFB (ms) | Peak Memory (MB) | Query Count | Asset Count (CSS/JS) |
| :--- | :--- | :--- | :--- | :--- |
| **Cold** | 44.50 | 0 | 36 | CSS:8 / JS:8 |
| **Warm** | 36.37 | 0 | 6 | CSS:8 / JS:8 |
| **Hot** | 28.25 | 0 | 6 | CSS:8 / JS:8 |

### 2.2 Observations
*   **Query Load:** Moderate (36 queries cold).
*   **Cache Efficiency:** Good (dropped to 6 queries).
*   **Residual Queries:** 6 queries persist in Warm/Hot runs (likely non-cacheable filters or user session checks).
*   **Assets:** Heavier payload (8 CSS, 8 JS) - *Candidate for Dedup Audit*.

---

## 3. Scenario: Vehicles List (`[rentiva_vehicles_list]`)

### 3.1 Metrics
| Run Type | TTFB (ms) | Peak Memory (MB) | Query Count | Asset Count (CSS/JS) |
| :--- | :--- | :--- | :--- | :--- |
| **Cold** | 27.46 | 0 | 20 | CSS:9 / JS:8 |
| **Warm** | 14.08 | 0 | 0 | CSS:9 / JS:8 |
| **Hot** | 16.66 | 0 | 0 | CSS:9 / JS:8 |

### 3.2 Observations
*   **Performance:** Fast (~27ms cold).
*   **Caching:** 100% effective (0 queries warm).
*   **Assets:** Highest asset count (9 CSS). *Investigate duplicate enqueues*.

---

## 4. Scenario: Availability Calendar (`[rentiva_availability_calendar]`)

### 4.1 Metrics
| Run Type | TTFB (ms) | Peak Memory (MB) | Query Count | Asset Count (CSS/JS) |
| :--- | :--- | :--- | :--- | :--- |
| **Cold** | 19.89 | 0 | 15 | CSS:4 / JS:2 |
| **Warm** | 4.17 | 0 | 0 | CSS:4 / JS:2 |
| **Hot** | 4.60 | 0 | 0 | CSS:4 / JS:2 |

### 4.2 Observations
*   **Performance:** Fast (<20ms).
*   **Caching:** Highly effective.

---

## 5. Overall Health Check
*   **Database Isolation Risk:** Low (Read-only benchmarks).
*   **Cache Strategy:** **Highly Effective** (Most render paths hit 0 queries warm).
*   **Optimization Targets:** 
    1.  `[rentiva_search_results]` residual queries (6).
    2.  `[rentiva_vehicles_list]` asset count (9 CSS).
