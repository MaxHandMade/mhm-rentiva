# V411_PERF_B2_WP_QUERY_EXHAUSTION.md

## 1. Baseline Performance Analysis
- **Current Query Count**: 30 queries for a standard search.
- **Top Inefficiencies**:
    - **Attachment N+1**: 2 queries per vehicle for thumbnails/images.
    - **Booking URL Lookup**: Repeated REGEXP queries for finding page IDs.
    - **Global Missing Filter**: The actual search result includes vehicles that are already booked, because `WP_Query` does not filter by `vehicle_booking` overlap across post types.

## 2. WP_Query Optimization Denemesi
### A. Meta Query Flattening
Current `meta_query` structure in `SearchResults.php`:
```php
'meta_query' => array(
    'relation' => 'AND',
    array('key' => '_mhm_rentiva_fuel_type', 'value' => [...], 'compare' => 'IN'),
    ...
)
```
- **Finding**: Meta queries are correctly hitting indices (`idx_mhm_status_lookup` etc.), but as the number of filters grows, MySQL join count increases.
- **Limit**: Standard `meta_query` is excellent for static attributes but **cannot** perform cross-post-type availability checks.

### B. The Date Overlap Problem (The "Exhaustion" Point)
- **Requirement**: Find `vehicle` posts where NO `vehicle_booking` exists with matching ID and overlapping timestamps.
- **WP_Query Constraint**: `WP_Query` operates on a single primary post type. Checking against `vehicle_booking` requires:
    - **Subquery**: `ID NOT IN (SELECT vehicle_id FROM bookings WHERE overlap)`
    - **Join**: `LEFT JOIN bookings ON ... WHERE bookings.ID IS NULL`
- **Exhaustion Proof**: There is no standard `WP_Query` argument for this logic. Native `date_query` only applies to the primary post's `post_date`.

## 3. Alternative Approaches
### Approach 1: WP_Query + `posts_where` Filter (Recommended)
- **Pro**: Preserves `WP_Query` benefits (pagination, caching, hooks).
- **Mech**: Inject a `NOT EXISTS` clause into the SQL `WHERE` via WordPress filter.
- **Risk**: Slightly higher complexity than standard args, but safer than full `$wpdb`.

### Approach 2: Custom $wpdb Query (Contingency)
- **Pro**: Maximum performance, exact control over joins.
- **Con**: Breaks pagination parity, skip object caching, requires manual security handling.

## 4. Proposed Direction
1. **Attachment Priming**: (Like B1) to solve N+1.
2. **Static Caching**: (Like B1) for URLs and Settings.
3. **Availability Injector**: Use `posts_where` filter to inject the overlap detection SQL fragment while keeping the main `WP_Query` structure.

## 5. Decision Recommendation
**WP_Query + Filter Injection** achieves the same performance as Custom SQL while maintaining architectural integrity.

## 6. Technical Hard Gates
- **Scoped Injection**: Filters MUST be added just before `WP_Query` and removed immediately after.
- **Prepared Overlap Subquery**: All SQL fragments MUST use `$wpdb->prepare` with strict sanitization.
- **Index Requirements**:
    - `vehicle_booking` postmeta MUST have indices on `_mhm_vehicle_id`, `_mhm_start_ts`, and `_mhm_end_ts`.
    - Verification shows these indices currently exist as `idx_mhm_vehicle_id`, `idx_mhm_start_ts`, and `idx_mhm_end_ts`.
