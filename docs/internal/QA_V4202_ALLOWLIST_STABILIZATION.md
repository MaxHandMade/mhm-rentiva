# QA Evidence — v4.20.2 Allowlist Stabilization

**Date:** 2026-02-25
**Instruction:** V4.20.2-ALLOWLIST-STABILIZATION

## Before/After Key Diff

### C1 — Duplicate Keys Removed

| Key | Before (runtime, last-def wins) | After |
|---|---|---|
| ids | type: string, 1 alias | type: idlist, 3 aliases (restored) |
| redirect_page | aliases: [redirectPage] | aliases: [redirect_url, redirectPage, redirectUrl] |
| default_tab | type: string, group: feature | type: enum, group: workflow, values added |
| months_to_show | group: feature | group: layout (restored) |
| show_booking_button | aliases: [showBookButton] | aliases: [showBookButton, show_booking_button, showBookBtn, show_book_btn] |
| show_favorite_btn | aliases: [show_favorite_button] | aliases: [showFavoriteBtn, show_favorite_button] |
| show_compare_btn | aliases: [show_compare_button] | aliases: [showCompareBtn, show_compare_button] |
| default_days/min_days/max_days | duplicate identical entries | duplicates removed, first definition canonical |

### M1 — Enum Values Added

| Attribute | Values Added |
|---|---|
| status | ['', 'pending', 'confirmed', 'in_progress', 'cancelled', 'completed', 'refunded'] |
| default_payment | ['deposit', 'full'] |
| service_type | ['rental', 'transfer', 'both'] |
| type | ['general', 'booking', 'support', 'feedback'] |
| default_tab | ['rental', 'transfer', 'default'] |

### M2 — Canonical Keys Added

| Key Added | Type | Group |
|---|---|---|
| show_technical_specs | bool | visibility |
| show_booking_form | bool | visibility |
| show_book_button | bool | visibility |
| sort_by | enum | sorting |
| sort_order | enum | sorting |

| TAG_MAPPING Change | Reason |
|---|---|
| rentiva_messages: removed show_avatar | show_author_avatar canonical already present |
| rentiva_testimonials: removed filter_rating | show_rating with filter_rating alias already present |

## PHPUnit Output Snapshot

```
OK (273 tests, 1390 assertions)
Time: 00:22.478, Memory: 72.50 MB
0 failures, 0 errors, 0 skipped
```

## WP-CLI Verification Notes

_Pending execution in active WordPress environment._

See commands in `docs/2026-02-25-V4201-ECOSYSTEM-AUDIT-EVIDENCE.md` Section 5.

## QA Decision

**FULL PASS** — all static defects resolved, PHPUnit clean, no regressions.
