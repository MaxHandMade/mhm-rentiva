=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      6.9
Requires PHP:      8.1
Stable tag:        4.58.1
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Plugin URI:        https://wpalemi.com/rentiva/
Author URI:        https://wpalemi.com/

MHM Rentiva is a powerful and flexible vehicle rental management plugin with secure WooCommerce integration for all frontend bookings.

== Description ==

MHM Rentiva is a comprehensive vehicle rental management solution designed for car rental agencies, motorcycle rentals, and multi-location fleet operations. It provides a dedicated and streamlined experience for managing your fleet and bookings. Frontend booking and payment processing are handled securely via WooCommerce, while administrators retain full control over manual bookings.

**Key Features:**

*   **Vehicle Management:** Easily add, edit, and manage your vehicle fleet with detailed attributes (transmission, fuel type, seats, etc.).
*   **Booking System:** Robust booking engine with calendar view, availability checking, and automatic price calculation.
*   **Payment Integration:** Seamless WooCommerce integration for all frontend payments (Online & Offline methods). Admin-only native offline payment support for manual bookings.
*   **Customer Management:** Manage customer information and booking history.
*   **Vendor Marketplace (Pro):** Multi-vendor platform where vehicle owners apply, list vehicles, set route pricing, and manage finances through a dedicated vendor panel.
*   **Vehicle Lifecycle Management (Pro):** 90-day listing duration with auto-expiry, vendor self-service (pause/resume/withdraw/renew), progressive withdrawal penalties, reliability scoring, and anti-gaming date blocking (v4.24.0).
*   **VIP Transfer Module:** Point-to-point transfer booking with city-based location hierarchy, route-based pricing, and vendor-specific pricing support (v4.23.0).
*   **Email Notifications:** Customizable email templates for booking confirmations, cancellations, vendor lifecycle notifications, and more.
*   **Shortcode Support:** Easy-to-use shortcodes to display vehicle lists, search forms, and booking wizards anywhere on your site.
*   **REST API:** Full REST API support for mobile app or external integrations.
*   **Gutenberg Blocks:** 20 blocks with Render Parity architecture — identical output across Gutenberg, Elementor, and shortcodes.
*   **Elementor Widgets:** 21 widgets with advanced controls, live preview, and responsive design.
*   **Popular Routes Showcase (v4.34.0):** Homepage A → B route cards with origin → destination, average duration, distance, and starting price. New `[rentiva_popular_routes]` shortcode + Gutenberg block + Elementor widget delegating to a single canonical renderer. Admin "🌟 Vitrine Koy" checkbox pins routes to the showcase. Lite plans show up to 3 cards; Pro plans show as many as configured. Silent no-op when no routes exist.

== Project Structure ==
 
mhm-rentiva/
├── assets/             # Frontend & Admin assets (CSS, JS, Images)
├── docs/               # Technical documentation & API guides
├── languages/          # Translation files (.pot, .po, .mo)
├── src/                # PHP Source Code (PSR-4 logic)
│   ├── Admin/         # Admin Module Controllers & Services (Booking, Vehicle, Payment...)
│   ├── Api/           # Custom REST API Endpoints
│   ├── Blocks/        # Gutenberg Block definitions
│   ├── CLI/           # WP-CLI Commands
│   ├── Core/          # Base logic & Financial Engine
│   ├── Helpers/       # Utility & Sanitization classes (SecurityHelper, etc.)
│   ├── Integrations/  # External integrations (WooCommerce, etc.)
│   └── Plugin.php      # Main initialization class
├── templates/          # Frontend layouts & Email templates
├── mhm-rentiva.php     # Main plugin entry point
├── uninstall.php       # Cleanup on plugin deletion
├── changelog.json      # Version history (English)
├── changelog-tr.json   # Version history (Turkish)
├── LICENSE             # GPLv2 License
├── readme.txt          # WP.org metadata
├── README.md           # Developer documentation (EN)
└── README-tr.md        # Developer documentation (TR)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Settings menu to configure your vehicle features, equipment, and module preferences.

== Frequently Asked Questions ==

= Does it work with WooCommerce? =
Yes, MHM Rentiva is designed to work seamlessly with WooCommerce for secure payment processing and checkout.

= Can I add custom features to vehicles? =
Absolutely. You can add, rename, or remove custom features and equipment via the Vehicle Settings page.

= Is it mobile-ready? =
Yes, all frontend components and admin settings are fully responsive.

== Screenshots ==

1.  **Dashboard:** Overview of your rental business.
2.  **Vehicle List:** Manage your fleet easily.
3.  **Booking Calendar:** Visual calendar for managing reservations.
4.  **Settings:** Comprehensive configuration options.

== Changelog ==

= 4.58.1 — 2026-05-21 =
* 🐛 **Critical fix:** Double-booking prevention on same-day returns — AutoComplete cron now compares full datetime (`_mhm_end_ts` UNIX primary, `CONCAT(dropoff_date, dropoff_time)` fallback) instead of date-only. Previously, same-day-dropoff bookings were auto-completed at midnight, allowing the vehicle to be double-booked for the remaining rental hours.
* 🐛 **Defense-in-depth fix:** `has_overlap()` now also flags `'completed'` bookings whose `end_ts` is still in the future, blocking availability even if a future cron bug or manual mishap marks a booking complete early.
* ✨ **Feat:** `Status` transition matrix allows `completed` → `in_progress` for early-completion correction.
* 🔧 **Tools:** `wp eval-file plugins/mhm-rentiva/bin/cleanup-early-completed-bookings.php` (positional `apply` to revert; default is dry-run).
* 🧪 **Tests:** 11 new PHPUnit tests — total 1231 tests, 0 NEW failures (7 documented saas_block env-quota baseline unchanged).

= 4.58.0 — 2026-05-15 =
* 🐛 **Critical fix:** Remaining-payment WC order no longer double-taxes when `prices_include_tax` is enabled — uses `wc_get_price_excluding_tax()`. Customer no longer overpays ~20% of the remaining amount.
* 🐛 **Critical fix:** Auto-cancelled bookings now correctly cancel their linked WC orders (deposit + remaining). One-time backfill helper (`sync_orphan_wc_orders()` / `sync_stale_past_bookings()`) recovers historical orphan pending orders.
* 🐛 **Fix:** Addon list/statistics use WooCommerce currency dynamically (was reading plugin setting instead).
* 🐛 **Fix:** Recent Bookings widget shows customer name (WC order billing fallback) and vehicle location.
* 🐛 **Fix:** Pending Payments widget shows deposit + remaining as separate rows, status-aware aggregation, WC order totals as source of truth.
* 🐛 **Fix:** Defensive null coalescing for optional `booking_data` keys in `WooCommerceBridge` — removes Undefined array key warnings in PHP debug log.
* ✨ **Feat:** Upcoming Operations widget adds plate, status badge (green/amber/blue), and end date — both PHP and React versions.
* ✨ **Feat:** Quick Actions grid adds Transfer and Vendors shortcut buttons.
* ✨ **Feat:** License page uses 2-column grid (account info / Lite-Pro comparison), version pill in header, flex action button row.
* ✨ **Feat:** About > Support tab — GitHub Issues card, Test & Verification stats card, version history accordion.
* ✨ **Feat:** Messages thread adopts modern chat bubble layout (right-aligned blue for self, left-aligned gray for other party) with "(You)" suffix.
* 🔧 **Chore:** Removed dead Revenue Chart widget and `Charts.php` (~85 lines orphan code). ReactDOM.render → React 18 `createRoot` migration in Messages bundle.
* 🧪 **Tests:** 12 new PHPUnit tests — 1215 tests total, 0 failures.
* 🌍 **i18n:** 88 new Turkish translations across Pro features, block editor settings, email templates, and chat UI.

= 4.57.0 — 2026-05-12 =
* 🐛 **Mobile responsive:** Horizontal scroll wrappers added to Setup Wizard system requirements table, Transfer Locations table, and Transfer Routes table.

= 4.56.0 — 2026-05-12 =
* ✨ **Unified KPI Card Style:** Consistent gradient KPI cards across all admin list pages (Vehicles, Bookings, Transfer, Additional Services).
* 🔧 **CSS variable scope** extended to `.mhm-stats-grid` so gradients work outside React roots.

= 4.55.0 — 2026-05-12 =
* 🐛 **Shortcode Pages mobile:** KPI cards use flex-wrap — all 3 stat cards visible at ≤782px instead of overflowing.
* 🐛 **Shortcode Pages mobile:** Action button column wrapped in horizontal scroll — Edit/View/Remove accessible at narrow widths.

= 4.54.0 — 2026-05-12 =
* 🐛 **Mobile responsive:** Customers table gains horizontal scroll wrapper at ≤782px.
* 🐛 **Mobile responsive:** Customer detail panel uses full-width and correct top offset (46px) for mobile admin bar.
* 🐛 **Mobile responsive:** Vendor Reports table — scroll wrapper + `table-layout:auto` at ≤900px fixes character-per-line crush.
* 🐛 **Fix:** Euro symbol renders as Unicode (was HTML entity) in JSON responses — `html_entity_decode()` applied to WooCommerce currency symbol.

= 4.53.0 — 2026-05-12 =
* 🐛 **Vendor Management mobile:** Vendors table fixed at 390px (`table-layout:auto` + horizontal scroll wrapper at ≤900px). Name and Email columns enforced to minimum 110px / 150px at narrow breakpoints.
* 🐛 **Vendor Management mobile:** Settings textarea/number inputs use `max-width:100%`. Commission label input respects container width at ≤600px.

= 4.52.0 — 2026-05-12 =
* ✨ **Export page React SPA:** Replaced legacy 780-line PHP render with a full React SPA. Three new REST endpoints (`GET /admin/export/history`, `DELETE /admin/export/{id}`, `POST /admin/export/preview`) backed by WP transient storage.
* ✨ **ExportCards:** Visual card selector for Bookings, Vehicles, and App Logs post types.
* ✨ **AdvancedFilters:** Collapsible date filter panel with preset ranges and custom from/to date inputs.
* ✨ **PreviewBar:** Live record count and 5-row sample table before export commit.
* ✨ **ExportHistory:** REST-loaded export log table with per-entry delete action.
* 🔧 **i18n:** 33 new TR translations for all export React strings.
* 🧪 **Tests:** 22 new PHPUnit integration tests for Export REST endpoints.

= 4.51.0 — 2026-05-12 =
* ✨ **Vendor Management (Faz B):** Vendors, Commission, and Settings tabs migrated from PHP render to React SPA. Three new REST controllers (`GET /vendors/vendors`, `POST /vendors/{id}/suspend`, `POST /vendors/{id}/unsuspend`, `GET /vendors/commission`, `POST /vendors/commission`, `GET /vendors/settings`, `POST /vendors/settings`).
* ✨ **VendorTable component:** Paginated vendor list with search, status filter (Active/Suspended), vehicle count, reliability score, and Suspend/Unsuspend quick actions.
* ✨ **CommissionTab component:** Live rate display, policy history table, and new rate form.
* ✨ **SettingsTab component:** Editable marketplace settings (payout freeze, min payout, photo limits, doc size, vehicle year, bio length, service cities).
* 🐛 **Performance:** Eliminated N+1 query in vendor list endpoint — vehicle counts now fetched in a single GROUP BY query.

= 4.50.0 — 2026-05-12 =
* 🔧 **Maintenance:** Support URLs updated from maxhandmade.com to wpalemi.com. Support email updated to support@wpalemi.com.
* 📝 **Docs:** Changelog updated for v4.40.0–v4.49.0. README updated with React SPA migration section.
* 🐛 **Mobile responsive:** Breakpoint standardised at 782px across all admin React pages.

= 4.49.0 — 2026-05-12 =
* 🐛 **Mobile responsive:** Standardised all WP admin React page breakpoints from 768px to 782px — accounts for WP admin sidebar width offset so media queries fire correctly on mobile devices.
* 🐛 **Mobile responsive:** Dashboard widget rows (Quick Actions, Recent Bookings, Transfer Summary, Revenue Chart) now stack to single column on mobile.

= Older versions =

For the full version history (v4.4.x – v4.48.x), see the [GitHub Releases page](https://github.com/MaxHandMade/mhm-rentiva/releases) or the structured [changelog.json](https://github.com/MaxHandMade/mhm-rentiva/blob/main/changelog.json) file shipped with the plugin.

== Upgrade Notice ==

= 4.23.0 =
Major Update: Vendor Transfer Location architecture with city-based hierarchy, vendor route pricing, 11 dashboard widget fixes, and 567 tests. Recommended for all users.

= 4.22.0 =
Quality Update: Comprehensive attribute registry, block registry, and Elementor widget audit with 563 tests. Recommended for all users.

= 4.6.1 =
Critical Update: Includes essential database protection fixes and WooCommerce tax calculation corrections. Highly recommended for all users.

= 4.6.0 =
Major Update: Introducing VIP Transfer Module with point-to-point booking, distance pricing, and WooCommerce partial payment support.

= 4.5.5 =
Frontend enhancements for mobile responsiveness, better UI consistency across Search/Favorites/Bookings, and localization updates.

= 4.5.4 =
Critical bug fix for Email Settings/Templates and extensive code refactoring. Recommended update.

= 4.5.0 =
This major update includes significant improvements to the User Account area, Payment History features, and crucial bug fixes. Recommended for all users.

= 4.4.5 =
This is a maintenance update with stability improvements. Recommended for all users.

= 4.4.4 =
This update includes major improvements: configurable vehicle limits, WooCommerce refund integration, enhanced booking form, and better mobile experience. Recommended for all users.

= 4.4.2 =
This update ensures WordPress standards compliance and includes verified stability improvements.

= 4.4.1 =
This update includes critical security fixes and code improvements. It is highly recommended to upgrade immediately.
