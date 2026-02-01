# PROJECT MEMORIES

## ACTIVE TASKS
- [COMPLETED] Booking Availability & License Audit (Priority: High)
  - **Context:** Integration between booking engine and licensing system audited and improved.
  - **Finding:** License limits (e.g., Max 50 Bookings) only worked in admin; Frontend/REST API bookings bypassed these checks.
  - **Fix:** Implemented `Restrictions::blockBookingOnFrontend` hooked to `mhm_rentiva_before_booking_create`. Lite version limit exceeded redirects frontend users to the license page with a soft notification.
  - **Performance:** Optimized `Util::has_overlap` SQL queries; no `numberposts => -1` bottlenecks found.
- [COMPLETED] Global Notification System Unification (v5.2)
  - **Premium UI:** Created `assets/css/frontend/notifications.css` based on Availability Calendar's high-quality design (Glassmorphism, shadow, icon badge).
  - **Centralization:** Unified all notification styles. Automatically loaded on all frontend pages via `AbstractShortcode.php`.
  - **Clean Code:** Removed redundant legacy notification styles in `my-account.css` and `booking-form.css`.
  - **JS Sync:** Updated `showNotification` functions in `availability-calendar.js`, `vehicles-grid.js`, `vehicles-list.js`, `my-account.js`, and `search-results.js`.
  - **Aesthetics:** Notifications now appear in bottom-right with premium animation (`slide-up`) and status-based badges.
- [COMPLETED] CSS Global Style Integration (v6.0)
  - **Objective:** Make all 15 shortcodes and Gutenberg blocks 100% compatible with WordPress Site Editor (FSE).
  - **Variables:** Replaced hardcoded HEX/RGB colors with master tokens in `assets/css/core/css-variables.css`.
  - **FSE Listeners:** Added `.has-link-color` and `.has-text-color` listeners to core CSS. Site Editor changes now override plugin defaults automatically.
  - **Cleanup:** Removed redundant `!important` tags for a modern CSS architecture.
- [COMPLETED] Centralized Block Registry (v6.1)
  - **Objective:** Moved to a centralized architecture (`BlockRegistry.php`) instead of separate classes for each block.
  - **Expansion:** Converted 14 shortcodes into Gutenberg blocks.
  - **Consistency:** All blocks depend on `mhm-rentiva-core-variables` for theme sync.
  - **Automation:** `render_callback` maps block attributes to corresponding shortcodes automatically.
- [COMPLETED] Theme & Plugin Color Synchronization (v6.2)
  - **Theme Expansion:** Added Success, Warning, Error, and Gray shades to `mhm-rentiva-theme/theme.json`.
  - **Variable Mapping:** Mapped plugin status colors to WordPress Global Presets (`--wp--preset--color--*`).
  - **Unified Control:** Unified theme and plugin color control through the Site Editor Styles panel.
- [COMPLETED] Granular CSS Color System (v7.0)
  - **Objective:** Decouple Prices, Buttons, and Titles from the primary brand color for finer Gutenberg control.
  - **Execution:** Refactored all 23 frontend CSS files to use `--mhm-price-color` and `--mhm-btn-bg`.
  - **Optimization:** Cleaned up `theme.json` to include dedicated "Price Color" and "Button Color" controls in the WordPress UI.


## TECHNICAL NOTES
- **Block Registry:** `src/Blocks/BlockRegistry.php` (Central logic for all 15 blocks)
- **Color Sync:** `theme.json` presets are now the source of truth for plugin variables.
- **Block Assets:** `assets/blocks/{{block-slug}}` (Contains `block.json` and `index.js`)
- **Rendering:** All blocks use `do_shortcode()` inside the registry for secure output.
- **Booking Engine:** `src/Admin/Booking/Helpers/Util.php` (Core overlap logic)
- **License Integration:** All entry points (Admin/Front/API) secured via `Restrictions.php`.
- **Security:** All booking inputs protected by `wp_unslash` and `Sanitizer::text_field_safe`.
- **Database:** Atomic conflict checks using `UNIX_TIMESTAMP` and `COALESCE`.
- **Performance:** `VehiclesList::get_vehicles` uses 1-hour transients with automatic cache busting on post save.
- **Color Override:** Added `!important` rules for `.has-*-color` classes to respect Site Editor overrides. Star rating remains #fbbf24.
- **DatePicker Modernization:** jQuery UI DatePicker now matches the high-end MHM design with custom header colors.

## [SCHEDULE & STATUS]
- **Current Version:** 4.6.7
- **Global Compliance:** 100% English primary language, i18n ready.
- **FSE Support:** Fully compatible with WordPress 6.2+ Site Editor.

## ARCHIVE
### v4.6.6 Technical Audit Findings (Resolved)
- **Redirection:** Replaced `wp_redirect` with `wp_safe_redirect` in `Handler.php` for WP.org compliance.
- **Inline Styles:** Refactored static `<style>` output in `Restrictions.php` to use `wp_add_inline_style()`.
- **Transient Management:** Optimized `AbstractShortcode.php` to use a **Versioned Cache** system via `delete_transient()`.
- **Output Security:** Enhanced `SecurityHelper::safe_output()` with explicit context validation.
