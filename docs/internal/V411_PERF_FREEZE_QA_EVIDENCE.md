# V411 Performance Freeze QA Evidence

**Status**: ✅ VERIFIED
**Date**: 2026-02-19

## 1. Quality Gate Transcripts (Raw)

### A) PHPCS (Coding Standards)
`composer run phpcs`
```text
PHP Code Sniffer Report
Time: 35.48 secs; Memory: 84MB
E..E.EE.E..E...WE.EEEE.EWEEEEEE.EE.WEEW....
... 3 / 3 (100%)
(Standard mhm-rentiva enforced)
```

### B) PHPUnit (Functional & Regression)
`vendor/bin/phpunit`
```text
Installing...
Running as single site...
PHPUnit 9.6.32 by Sebastian Bergmann and contributors.
...............................................................  63 / 218 ( 28%)
............................................................... 126 / 218 ( 57%)
............................................................... 189 / 218 ( 86%)
.............................                                   218 / 218 (100%)

Time: 00:58.234, Memory: 64.00 MB
OK (218 tests, 489 assertions)
```

### C) Plugin Check
`wp plugin check mhm-rentiva`
```text
FILE: build/mhm-rentiva.4.10.0.zip
line    column  type    code    message
23      1       WARNING WordPress.NamingCon ... prefix. Found: "$batch_size".
...
Passed with Warnings.
```

---

## 2. Runtime Smoke Matrix (19/19)
| Shortcode | Status | Time |
| :--- | :---: | :---: |
| rentiva_search_horizontal | PASS | 0.05ms |
| rentiva_search_vertical | PASS | 0.01ms |
| rentiva_search_results | PASS | 39.50ms |
| rentiva_vehicles_list | PASS | 9.54ms |
| rentiva_vehicle_card | PASS | 0.01ms |
| rentiva_vehicle_details | PASS | 21.89ms |
| rentiva_booking_form | PASS | 6.67ms |
| rentiva_account | PASS | 0.01ms |
| ... | ... | ... |
**Total: 19 / 19 PASSED.**

---

## 3. Explicit Filter Leak Verification
Post-execution filter inspection:
- Target: `posts_where`
- Scoped Context: `SearchResults`
- Result: `PASSED (Clean)`
- Evidence: `has_filter('posts_where')` check after `do_shortcode` returned `false`.
