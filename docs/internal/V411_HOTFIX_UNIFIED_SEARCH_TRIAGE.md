# Triage Report: Unified Search UI Regression (Updated)

**Status**: ISOLATED
**Issue**: Unified Search components (Locations, Dates, Pax) are missing from the DOM because PHP-side visibility flags (`$show_location_select` etc.) are resolving to `false`.

## 1. Findings
- **Template Logic**: Template uses `if ($show_location_select)` etc. to render fields.
- **Shortcode Logic**: `UnifiedSearch::prepare_template_data` calls `resolve_bool`.
- **Resolution Logic**: `resolve_bool` calls `SettingsCore::get('mhm_rentiva_enable_location_select')`.
- **SettingsCore::get**: If the key is missing from DB, it falls back to `get_defaults()`.
- **Suspected Bug**: `FrontendSettings` or `SettingsCore` defaults might be missing or mapping to `0/false` incorrectly for these specific keys, causing the shell-only render.

## 2. Evidence
The browser console shows `Dropdown Count: -1`. This means when jQuery ran, `.rv-unified-search select[name="pickup_location"]` was NOT in the DOM.

## 3. Plan
1. Inspect `FrontendSettings.php` for correct default keys and values.
2. If defaults are correct, investigate why `SettingsCore::get` fails to return them.
3. Apply fix to `UnifiedSearch::prepare_template_data` to ensure fallback is a hard-coded `true` if settings are ambiguous.
