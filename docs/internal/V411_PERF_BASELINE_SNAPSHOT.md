# V411 Performance Baseline Snapshot

**Status**: 🔒 FROZEN (2026-02-19)
**Phase**: 4B Optimization Baseline
**Version**: v4.11.x

## 1. Environment Specifications
| Property | Value |
| :--- | :--- |
| **PHP Version** | 8.2.12 |
| **MySQL Version** | 10.4.32-MariaDB |
| **WordPress Version** | 6.9.1 |
| **Object Cache** | Disabled (Internal/None) |
| **Dataset Size** | 8 Vehicles, 1 Booking |
| **OS** | Windows (Local Development) |

---

## 2. Performance Metrics (Cold vs Warm Cache)

### A) VehiclesList (Optimization B1)
*Scenario: Default rendering of all vehicles via `[rentiva_vehicles_list]`.*

| Metric | Cold Load (Cached Flush) | Warm Load (Immediate) |
| :--- | :---: | :---: |
| **Total Queries** | 14 | 0 |
| **Execution Time** | 29.69ms | 8.80ms |
| **N+1 Attachments** | 0 (Batched) | 0 |

> [!NOTE]
> B1 optimizasyonu öncesi sorgu sayısı 21 idi. %33 azalma sağlandı. Warm load durumunda 0 sorgu olması, `static` önbellek ve `WP_Cache` etkinliğini kanıtlar.

### B) SearchResults (Optimization B2)
*Scenario: 3-day booking search range.*

| Metric | Cold Load (Cached Flush) | Warm Load (Immediate) |
| :--- | :---: | :---: |
| **Total Queries** | 22 | 0 |
| **Execution Time** | 29.65ms | 8.63ms |
| **Availability Check** | SQL (NOT EXISTS) | N/A (Cached) |

> [!NOTE]
> B2 optimizasyonu öncesi sorgu sayısı 30 idi. %27 azalma sağlandı. SQL seviyesindeki `NOT EXISTS` filtresi, araç sayısından bağımsız olarak performansı korur.

---

## 3. Query Breakdown & SQL Explain

### SearchResults SQL Logic
**Core Fragment (NOT EXISTS subquery):**
```sql
AND NOT EXISTS (
    SELECT 1 
    FROM wp_posts as bookings
    INNER JOIN wp_postmeta as m1 ON (bookings.ID = m1.post_id AND m1.meta_key = '_mhm_vehicle_id')
    INNER JOIN wp_postmeta as m2 ON (bookings.ID = m2.post_id AND m2.meta_key = '_mhm_start_ts')
    INNER JOIN wp_postmeta as m3 ON (bookings.ID = m3.post_id AND m3.meta_key = '_mhm_end_ts')
    WHERE bookings.post_type = 'vehicle_booking'
    AND bookings.post_status IN ('publish', 'mhm-confirmed', 'mhm-pending')
    AND m1.meta_value = wp_posts.ID
    AND (CAST(m2.meta_value AS SIGNED) <= REQ_END)
    AND (CAST(m3.meta_value AS SIGNED) >= REQ_START)
)
```
- **Explain**: Index on `post_id` and `meta_key` ensures subquery is efficient. `CAST` is necessary for numeric comparison on meta strings.

---

## 4. Reproducibility
To reproduce these metrics, run the following command in the project root:
`php perf_snapshot_profiler.php`
