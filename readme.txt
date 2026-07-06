=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        4.63.2
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

= 4.63.2 — 2026-07-06 =
* 🐛 **Fix:** Vendor commission was silently never recorded for real bookings due to a booking-reference mismatch between the checkout flow and the commission ledger — vendor earnings were never credited at all.
* ✨ **Feature:** Vendor commission now automatically clears and becomes available for payout 7 days after payment, via a new scheduled job. Previously, commission stayed in a "pending" state indefinitely with no way for a vendor to ever withdraw it.
* 🐛 **Fix:** Refunds now correctly adjust the vendor's available balance, whether the refund happens before or after the commission clears, and whether it's a partial or full refund. Previously, refunds had no effect on the balance at all.
* ✨ **Feature:** Vendors now receive an email notification when a commission clears and becomes available for payout.
* 🐛 **Fix:** The vendor dashboard's "Financial Summary" cards (Available Balance, Pending Balance, Total Paid Out) always showed 0,00 regardless of the vendor's actual balance, due to a data-key mismatch. The payout-request section, which reads the balance directly, was unaffected.

= 4.63.1 — 2026-07-05 =
* 🐛 **Fix:** The Dashboard's and Reports page's "Revenue" charts were rendering blank — a pre-existing 404 on a vendor script that never shipped in any release. Chart.js is now bundled directly with the admin scripts.
* 🐛 **Fix:** The Reports page's "Monthly Revenue" KPI card showed a corrupted value (e.g. "1,87 €" instead of "1,870.00 €") due to a display value being re-parsed as a raw number.
* 🐛 **Fix:** Saving the Messages settings page from any tab other than the one being edited (e.g. saving Email settings while on the Categories tab) silently reset the other tabs' settings, including notification checkboxes, sender email, and category/status lists, to their defaults.
* 🐛 **Fix:** A vehicle that became unavailable at the moment of booking submission no longer silently drops the "similar vehicles" suggestion — the alternatives list now reaches the booking form as intended.
* 🐛 **Fix:** Corrected a Turkish mistranslation on the Reports page ("Booking Report" tab).
* 🐛 **Fix:** Removed two broken script/style references (booking calendar CSS, standalone customer-messages JS) that resulted in harmless but noisy 404 requests.

= 4.63.0 — 2026-07-02 =
* ✨ **Feature:** Admins can now generate a payable link for a booking's remaining amount directly from the "Deposit Management" box on the booking edit screen, and email it to the customer with one click. The link is shown on-screen for copying too (WhatsApp, SMS, phone), and works even for customers without a site account.
* 🐛 **Fix:** Bookings whose payment is full-payment-only (e.g. a VIP transfer configured without deposit support) no longer get mislabeled "Deposit" when the checkout's cart-wide payment-type selector is set to "Deposit Payment" for a mixed cart. The amount charged was always correct; only the internal label is corrected.

= 4.62.3 — 2026-07-02 =
* 🐛 **Fix:** Switching the checkout payment-type option from "Full Payment" back to "Deposit Payment" now correctly restores the remaining balance. Previously, once the full-payment option was selected even briefly (including an automatic page-load sync), the remaining balance stayed at 0 after switching back to a deposit, leaving no way to collect or track the rest of the payment.

= 4.62.2 — 2026-07-01 =
* 🔒 **Hardening:** The Lite vehicle-limit counter now reflects the true vehicle count independently of the public overflow-hiding filter (internal robustness; enforced limits unchanged).

= 4.62.1 — 2026-07-01 =
* 🐛 **Fix:** The Lite VIP transfer route limit now counts routes correctly. On installs using the current routes table, the limit notice showed "0 used" and new routes could still be created past the Lite limit because the counter read the wrong (legacy) database table.

= 4.62.0 — 2026-06-30 =
* ✨ **Added:** Lite limit enforcement on downgrade: catalog items added during Pro (vehicles, add-on services, transfer routes) that exceed the Lite limit are now hidden from the public site when a license expires, instead of staying live. Data is never deleted and is restored automatically when you upgrade to Pro again.
* ✨ **Added:** Admin lists mark over-limit items with a "Lite limit — hidden" badge, and the vehicle limit notice shows how many are hidden.
* ✨ **Added:** Over-limit (hidden) vehicles can no longer be booked on the public site.
* 🐛 **Fix:** The "Vendor Payout Requests" admin menu no longer appears in the Lite version.

= 4.61.0 — 2026-06-29 =
* ✨ **Added:** Vendor payout statements. Approving a vendor payout now generates a printable, sequentially-numbered payment statement for the period — earnings, penalties, and net — frozen as an immutable record, and emails the vendor a link to view and print it from their panel. Admins see the statement number and a view link on the Payout Requests list; vendors see it in their payout history.
* ✨ **Added:** Commission breakdown on the statement. Each earning line shows Gross, Commission (rate % and amount), and Net, plus a period "Total commission deducted" total. Statements issued before this version show net only.
* ✨ **Added:** Statement branding. Your company identity (name, address, tax office and number, phone, email, logo) and a custom footer note appear on the statement, configured under the new statement branding settings and applied at view time.
* ✨ **Added:** Vendor agreement gate. You can require applicants to read and accept a vendor agreement on the application form; the acceptance (with timestamp) is stored and shown on the application detail. The toggle and agreement text live in the vendor settings.
* ✨ **Added:** "Added by" column for vehicles. The admin vehicle list now shows and filters who added each vehicle (vendor or operator), so operator and vendor listings are easy to tell apart.
* ✨ **Added:** Close or re-open a vehicle's day for reservations directly from the Monthly Reservation Calendar — click an empty day to block it, click a blocked day to open it, no vehicle-edit needed. Booked days keep their reservation popup.
* 🐛 **Fix:** When a vendor edits critical fields of a live listing, the listing is unpublished for admin re-review instead of staying live with unreviewed changes.
* 🐛 **Fix:** An admin can now edit a vendor's city, and the vendor's city is read from the canonical field across the panel.
* 🐛 **Fix:** Booking reminder emails are no longer sent for inactive bookings.

= 4.60.0 — 2026-06-22 =
* 🐛 **Fix:** Matured vendor payouts were silently skipped on single-site installs — a dead SaaS control-plane `is_operational()` gate in `MaturedPayoutJob` prevented any payout from processing unless a multi-tenant registry row existed (which it never does on a normal install).
* 🔧 **Change:** Removed unused multi-tenant SaaS scaffolding: the control-plane gate and metering classes (ControlPlaneGuard, MeteredUsageTracker, CycleManager, TenantProvisioner), usage-metering call sites, and a drop-migration (v3.9.0) that removes the two now-empty orchestration tables (`mhm_rentiva_tenants`, `mhm_rentiva_usage_metrics`) on upgrade. `src/Core/Tenancy/` (TenantResolver/TenantContext) is retained.

= 4.59.0 — 2026-06-20 =
* 🐛 **Fix:** "Popular Routes" prices now use the active WooCommerce currency (symbol, position, thousand/decimal separators) via `CurrencyHelper::format_price()` — was hardcoded to `₺`. The Gutenberg block, Elementor widget, and shortcode all render through the same WC-aware formatter.
* 🔧 **Change:** Removed the redundant "Currency symbol" control from the Popular Routes Gutenberg block + Elementor widget (and the `currency_symbol` shortcode attribute). Currency is sourced from WooCommerce/plugin settings, so the manual override was dead config. Removed across `block.json`, `AllowlistRegistry` (schema + TAG_MAPPING), and the shortcode — schema parity preserved.
* 🐛 **Fix:** `render_shortcode()` (ElementorWidgetBase) skips non-scalar settings (icon/url/`__globals__` arrays), preventing "Array to string conversion" warnings across all Elementor widgets that delegate to a shortcode.
* 🐛 **Fix:** `AbstractShortcode` cache getter normalizes a transient miss (`false`) to `null`, so callers reliably distinguish "no cache" from a cached falsey value.
* 🐛 **Fix:** Featured Vehicles slider now initialises in the Elementor editor preview (render-time-enqueued initialiser wasn't replayed into the preview iframe, so the slider showed empty in the editor — front-end was unaffected). Added an `elementor/preview/enqueue_scripts` loader + `frontend/element_ready` re-init hook.
* 🐛 **Fix:** Operator-owned vehicles (added by an admin/editor, not a vendor) no longer enter the vendor listing lifecycle. Previously every published vehicle got a 90-day timer and was eventually auto-expired off the catalogue — including the operator's own fleet. The lifecycle (expiry, renewal, auto-withdrawal, reliability score, "vehicle is live" email) now applies only to vendor-owned listings (`rentiva_vendor` author). Operator vehicles never expire and show no countdown; existing ones are protected without a data migration.
* 🐛 **Fix:** Vendor payouts can now be approved in a single admin click. "Approve Selected" previously routed through a multi-stage maker-checker / SaaS control-plane governance stack that never finalized a payout on a single-site install (the payout stayed Pending while the UI falsely reported success, and the final stage was blocked by an unprovisioned tenant `saas_block`). Approval now performs the atomic ledger debit + publish directly; the control-plane quota gate was removed from the financial path. Also eliminates 7 long-standing `saas_block` test failures.
* 🐛 **Fix:** Suspended vendors are visible again in Vendor Management. Suspending removes the `rentiva_vendor` role but keeps the `_rentiva_vendor_status` meta; the list enumerated by role, so suspended vendors never showed (even under "All Statuses"). The list now enumerates by the status meta.
* ✨ **Feat:** Vendor names in the Active Vendors table open a read-only "biography" panel — contact, masked IBAN, status, score, available balance, lifetime stats, last 10 payouts (with "View all"), last 10 reliability/penalty events, and the vendor's vehicles incl. archived — via a new `GET /vendors/vendors/{id}` endpoint. The misleading row pointer is scoped to the clickable Applications table only.
* ✨ **Feat:** An admin can reverse an already-applied penalty for a valid reason — resolving an "Appeal Penalty" report writes a compensating ledger entry that credits the penalty back to the vendor (idempotent — no double refund).
* 🐛 **Fix:** Payout approval re-checks the vendor's balance — if it dropped since the request (e.g. a penalty was applied), approval is blocked instead of overdrawing the vendor into a negative balance.
* 🐛 **Fix:** The vehicle withdrawal/pause reason is now optional in the vendor panel — leaving it empty withdraws and applies the penalty directly (no admin work); entering a reason files an appeal that suspends the penalty for the admin to resolve/reject. (Previously every withdrawal forced an appeal.)
* 🐛 **Fix:** The vendor penalty-appeal / waiver workflow now works — the `vendor_reports` table was never created on existing installs (un-bumped schema version), so withdraw-with-reason failed silently. Now a reasoned withdrawal opens an appeal that suspends the penalty, and the admin can reject (apply) or resolve (waive) it from Vendor Reports. The deferred penalty applies at the tier computed at withdrawal time (no self-counting overcharge).
* 🐛 **Fix:** Vendor withdrawal penalties now actually apply. The progressive penalty (10%/25% of monthly average revenue, debited from the vendor's balance) was calculated and wired but silently never recorded: PenaltyRecorder's ledger write hit the SaaS control-plane gate (now removed) and its broad catch swallowed the error, and the penalty UUID overflowed the `transaction_uuid` CHAR(36) column for realistic IDs. UUID is now a bounded hash, failures are logged, and the chain is covered by tests.
* ✨ **Feat:** In the Payout Requests list, the payout ID and vendor name now link to that vendor's detail/biography panel (balance, payout history, penalties, vehicles) — full vendor context in one click.
* ✨ **Feat:** Archive visibility for vehicles past the 90-day lifecycle. The admin vehicle list gains a Lifecycle/Archive filter (surfaces withdrawn drafts), and the vendor panel groups expired/withdrawn listings under an "Archived Listings" section.
* 📝 **Docs:** SHORTCODES.md inventory updated to current counts (27 shortcodes, 22 blocks, 23 Elementor widgets / TAG_MAPPING entries); README / README-tr parity touch-ups.
* 🧪 **Tests:** PopularRoutesShortcodeTest updated for WC-driven currency (regression guard: old `₺850` → `850 $`); schema parity suite green.

= 4.58.2 — 2026-05-21 =
* 🐛 **Critical fix:** Transfer search now filters vehicles by the route origin's city. A vehicle parked in Ankara no longer appears for an İstanbul route. Uses the same 3-layer hybrid location filter (vehicle → vendor → global default) as rental, expanded to the origin city.
* ✨ **Feat:** `QueryHelper::get_location_subquery()` gains an optional `expand_to_city` parameter (backward-compatible).
* 🛡️ **Fix:** About page developer contact info hardcoded — defensive against a DB option leaking the WP `admin_email`.
* 🔧 **Fix:** About test stats updated (1,237 tests / 3,736 assertions); plugin header + readme "Tested up to" set to WP 7.0.
* 💅 **Style:** KPI card top spacing on list-table pages; dashboard Pending Payments / Revenue cards bound height (no empty gap); Vehicles calendar spacing parity with Bookings.
* 🧪 **Tests:** 6 new PHPUnit tests — total 1,237 tests, 0 NEW failures (7 documented saas_block env-quota baseline unchanged).

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
