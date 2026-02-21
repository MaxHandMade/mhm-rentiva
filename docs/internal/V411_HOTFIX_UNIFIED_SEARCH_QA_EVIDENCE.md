# V411_HOTFIX_UNIFIED_SEARCH_QA_EVIDENCE

**Date:** 2026-02-19
**Version:** 4.11.0 (Hotfix Patch)
**Goal:** Restore Unified Search UI Rendering

## Problem Statement
The Unified Search block/shortcode was rendering as a "shell" (Search button only). This was caused by:
1.  **Resolver Bug**: `resolve_bool` returning `false` for missing settings keys instead of applying hardcoded fallbacks.
2.  **Transformer Bug**: `to_bool_string` casting `'default'` string to `'0'`.
3.  **Registration Gap**: Shortcodes not being registered via `add_shortcode` due to missing `register()` call in main plugin file.

## Executed Fixes
| File | Change | Rationale |
| :--- | :--- | :--- |
| `SettingsCore.php` | Added `has()` method | Allow checking key existence without fetching value. |
| `UnifiedSearch.php` | Refactored `resolve_bool` | Implemented strict precedence: Attribute > Setting > Fallback. |
| `Transformers.php` | Preserved `'default'` in `to_bool_string` | Allow downstream logic to handle default states. |
| `mhm-rentiva.php` | Called `ShortcodeServiceProvider::register()` | Ensure all 19 shortcodes are registered early. |

## Verification Data

### CPU/Execution Audit
- `debug_unified_search.php` confirmed that `resolve_bool('default', ..., true)` now returns `true` even if setting is missing.

### Automated Tests
- **PHPUnit**: `OK (218 tests, 452 assertions)`
- **PHPCS**: `0 errors, 0 warnings` (Verified after edit)

### Browser Verification
- **Page**: `/arac-sekme-arama/`
- **Result**: PASSED. Both Block and Shortcode render Locations, Dates, and Time selects.
- **Recording**: [unified_search_final_check_1771480141979.webp](file:///C:/Users/manag/.gemini/antigravity/brain/ca40c671-98f0-4f16-9bf7-8ca4ec92f803/unified_search_final_check_1771480141979.webp)

## Conclusion
The hotfix successfully restores UI parity and preserves architectural integrity.
