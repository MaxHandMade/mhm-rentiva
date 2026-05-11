=== MHM Rentiva ===
Contributors:     mhmdevelopment
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      6.9
Requires PHP:      8.1
Stable tag:        4.43.0
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Plugin URI:        https://maxhandmade.com/urun/mhm-rentiva/
Author URI:        https://maxhandmade.com/

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

= 4.39.0 — 2026-05-10 =
* 🆕 **Customers React SPA (Faz 2)** — Replaces the legacy jQuery/WP_List_Table Customers admin page with a full React SPA backed by REST API endpoints. Live search (300 ms debounce), sortable columns, slide-in customer detail panel, bulk delete, and Export CSV — all without page reloads.
* 🔌 **New REST API** — `GET /mhm-rentiva/v1/customers` (paginated, search, sort), `GET /customers/{id}` (detail), `DELETE /customers/bulk`. All routes require `manage_options` capability.
* 🗃 **CustomersOptimizer** extended with `sort_by` / `sort_dir` parameters and safe ORDER BY injection via a PHP-side column whitelist.
* 🗑 Legacy `customers.js`, `customers-calendar.js`, `customers.css`, `simple-calendars.css` removed; `CustomersListTable.php` and `CustomersListPage.php` deleted.
* 🧪 13 new PHPUnit integration tests (10 REST endpoint + 3 CSV exporter). Full suite: 1115 tests, 3425 assertions.

= 4.38.2 — 2026-05-09 =
* 🔧 **Dev mode fix** — `isDevelopmentEnvironment()` now recognises `*.localhost` domains (Traefik reverse-proxy convention: `rentiva.localhost`, `bozcon.localhost`, etc.). The Traefik migration from `localhost:port` URLs to `*.localhost` domains silently broke automatic Pro dev mode activation on all local Docker stacks — only `.local` / `.test` / `.dev` / `.staging` were in the allowlist. Adding `.localhost` restores the zero-config dev experience: no license key and no `wp-config.php` changes needed on any Traefik-based local stack.
* Tests: 1058 → **1070** PHPUnit (+12: 3 Traefik `*.localhost` detection + 3 existing dev domain regression guards + 2 production domain rejection + 2 `isActive()` auto-dev integration + 1 `disable_dev_mode` option coverage + 1 WP 6.7 `WP_Block_Bindings_Registry` notice whitelist), 3320 → 3332 assertions, 0 fail. PHPCS: 0 errors.

= 4.38.1 — 2026-05-06 =
* 🛡️ **Audit-driven patch** — closes 2 findings (1 YÜKSEK + 1 ORTA) and 3 deferred Phase 5/6/7 paper-cuts surfaced by post-merge `/wp-conductor` audit of the v4.37.0 → v4.38.0 release chain. Plus 1 dormant production defect surfaced by the new test coverage.
* 🔴 **YÜKSEK fix** — Admin vendor profile slug edit now routes through `VendorSlugManager::change_slug()`. Previously a `// later` TODO left the save path writing slugs directly via `update_user_meta()`, bypassing collision suffix and history sanitize. Two admins submitting the same raw slug to different vendors would both succeed, breaking the second vendor's public profile URL.
* 🟡 **ORTA fix** — Vendor Directory card `vehicle_count` now respects `_mhm_vehicle_lifecycle_status='active'` parity with the Profile vehicle list. Previously paused/withdrawn vehicles inflated the directory card count, so a vendor showed "5 araç" on the directory but only 2 active vehicles on the profile.
* 🐛 **Dormant defect fix** — Pagination partial `str_replace($big, ...)` passed `$big` as int (999999999); PHP 8.1+ strict types throw `TypeError` whenever pagination renders. Existing tests never seeded enough vendors to cross `per_page=12`, so the defect lay dormant and would have crashed the directory the moment a 13th active vendor appeared on production. Fixed via explicit `(string)` cast.
* 📌 **Phase 5 paper-cut** — `VendorDirectorySeo::register()` now early-returns when an SEO plugin owns the contract or `mhm_rentiva_vendor_directory_seo_disable` filter is true (parity with `VendorProfileSeo`'s register guard). Plus DocBlock spelling out the 3-arg filter signature for `build_meta_description($default, $vendor_count, $vehicle_count)`.
* 📌 **Phase 6 paper-cut** — `VendorDirectoryCacheInvalidator` two parity guards: `!is_string($meta_key)` short-circuit on the user_meta hooks (rare WP edge case where meta_key is an array — Profile sibling already had this guard), and a no-op early-return when `$new_status === $old_status` on the lifecycle hook (parity with the existing `on_comment_status` no-op in the same class). Avoids spurious cache flushes that would defeat the 30-min directory cache.
* 📌 **Phase 7 paper-cut** — 3 new combination tests covering pagination overflow + alpha sort with city filter + paged query string on page 2. Verifies CSS `.current` selector stays scoped under `.mhm-vendor-directory-pagination` (theme `.current` rules can't hijack styling).
* Tests: 1044 → **1058** PHPUnit (+14: 2 slug admin edit + 2 vehicle count lifecycle + 4 SEO register guard + 3 cache invalidator parity + 3 pagination/sort combination), 3289 → 3320 assertions, 0 fail. PHPCS: 0 errors (full project, CI parity).

= 4.38.0 — 2026-05-06 =
* 🆕 **Vendor Directory Page** — Pro-gated public catalogue at `/vendors/` (EN) / `/bayiler/` (TR). Customers discover all active vendors in one place: filter by city (combined from vendor headquarters + vehicle pickup locations), badge status (Verified / New), minimum rating (3+ / 4+ / 5). Sort by rating (default, with newest member tiebreaker), newest, or alphabetical. 12 vendors per page, classic page-number pagination.
* 🆕 `[rentiva_vendor_directory]` shortcode + Gutenberg block (`mhm-rentiva/vendor-directory`) + Elementor widget. All three delegate to a single canonical renderer (render-parity rule). 8 attributes: `per_page`, `default_sort`, `show_filter_bar`, `show_breadcrumb`, `show_pagination`, `empty_message`, `class`, `id`.
* 🎯 **Schema.org SEO** — `ItemList` (vendor profile URLs) + `BreadcrumbList` JSON-LD emitted in `<head>` when no SEO plugin is active. Probes Yoast SEO, Rank Math, AIOSEO, SEOPress, The SEO Framework, SmartCrawl via class/constant detection — yields to them automatically when present. Filters reflected in schema (canonical/render parity for crawlers).
* 🌍 **i18n base slug** — `_x('vendors', 'URL slug', 'mhm-rentiva')` translates to `bayiler` in TR. Filter override `mhm_rentiva_vendor_directory_url_base` lets site owners customize (e.g., `dealers`, `firmalar`). Defensive ASCII sanitize + empty fallback. Locale change auto-flushes rewrite rules.
* 🔒 **Two-layer Pro gate** — dispatch-time 404 (Lite users see WordPress 404 on `/vendors/`) + render-time empty string (block/widget/manual shortcode usage by Lite returns nothing).
* ⚡ **30-min transient cache** with prefix-wildcard SQL invalidation. Cache key includes filter args (city + badge + min_rating + sort + paged) for per-query isolation. Invalidated on vendor status / city / reliability_score changes, vehicle save, comment status transition, lifecycle changes, profile updates. Bio and avatar changes intentionally NOT invalidated (deliberate scope reduction — not rendered on cards).
* 🎨 **Compact card design** — avatar (SVG initials fallback inherited from v4.37.2), display name, city, vehicle count, badge label (✓ Doğrulanmış Bayi / Yeni Bayi), rating bar with count, "Profili görüntüle →" CTA. Whole card clickable (Popular Routes v4.34.1 pattern parity). Theme-agnostic CSS namespace `.mhm-vendor-directory-*` (no `.label`, `.card`, `.filter-bar`, `.pagination` generic class collisions).
* 📱 **Mobile-first layout** — 1-col stack on phones (`<600px`), 2-col on tablets, 4-col on desktop (`≥1024px`). Native HTML5 `<details>` filter sheet, no-JS friendly. Mobile breakpoint matches v4.37.2 B10 (phone-landscape 568px collapses cleanly).
* 🆕 5 new filter hooks for developers: `mhm_rentiva_vendor_directory_url_base`, `mhm_rentiva_vendor_directory_per_page`, `mhm_rentiva_vendor_directory_empty_message`, `mhm_rentiva_vendor_directory_page_title`, `mhm_rentiva_vendor_directory_meta_description`, plus `mhm_rentiva_vendor_directory_seo_disable` global kill switch.
* ✨ Reused infrastructure (no duplication) — `VendorBadgeEligibility::evaluate()` for badge filter, new public `VendorProfileProvider::aggregate_rating_for_vendor()` wrapper for rating aggregation (additive, mirrors v4.37.1 B3 multi-source meta key fallback), `Mode::canUseVendorMarketplace()` Pro flag, `AllowlistRegistry` + CAM pipeline for shortcode/block/widget parity.
* PHPCS: 0 errors (full project, CI parity). PHPUnit: 1044 tests, 3289 assertions, 7 skipped (+37 new directory tests vs v4.37.3 baseline 1007). 8-checkpoint Chrome DevTools MCP browser smoke green.

= 4.37.3 — 2026-05-05 =
* 🔧 CI hotfix: WPCS "Opening PHP tag must be on a line by itself" error in `templates/frontend/partials/vendor-profile-hero.php` line 19. The v4.37.2 avatar src dual-escape branch placed `<?php if (...) :` on the same line as the multi-line statement body that followed it. Refactored so the PHP tag is alone on its line and the variable assignments live in a separate block. **No functional change** — v4.37.2 was working in production smoke tests; the issue was lint-only and surfaced when CI runs the full `composer phpcs` (broader than `composer phpcs:release`).
* PHPCS: 0 errors (full project, including tests/). PHPUnit: 1007 tests, 3208 assertions, 7 skipped.

= 4.37.2 — 2026-05-05 =
* 🎨 **Vendor profile UX polish — seven theme-agnostic improvements** designed to play nicely with any host theme and the rest of the WordPress ecosystem (Yoast / Rank Math / AIOSEO, Simple Local Avatars, third-party review imports, etc.).
* 🖼 NEW `VendorAvatarFallback` — vendors without a custom avatar and without a real Gravatar now get a deterministic SVG circle bearing their initials. Hue is hashed from the display name (Slack/Gmail pattern) so each vendor has a stable unique colour without depending on the host site's brand palette. Hooks `get_avatar_data` at priority 99 so 3rd-party avatar plugins (Simple Local Avatars, Avatar Privacy, etc.) keep their precedence and only the Gravatar mystery-man placeholder is substituted.
* 🚗 Vehicle thumbnail cascade — `Provider::collect_vehicles()` now falls back from featured image to the first attachment in the Rentiva gallery (`_mhm_rentiva_gallery_images` + legacy aliases), then to a compact text-only card. No more empty placeholder rectangles when one car is missing a photo and others aren't.
* ⭐ Vehicle card rating layout now mirrors the hero — full half-star bar (`★★★★½`) instead of a single glyph + numeric rating. Rating colour, count chip, and compact `--compact` modifier styled separately.
* 📍 `show_location` shortcode/block/widget attribute default flipped from `yes` to `no`. The hero already shows the city; the dedicated section duplicated the meta until the v4.40.0+ Transfer Map can populate it. Layouts that want the section render with `show_location="yes"`.
* 🎯 NEW `VendorProfileSeo` — page title (`document_title_parts`) + `<meta name="description">` defaults for the rewrite-routed profile URLs **only when no real SEO plugin is active**. Yoast SEO, Rank Math, AIOSEO, SEOPress, The SEO Framework, and SmartCrawl all detected via class/constant probes; we yield to them. Bio is excerpted at 155 characters on a word boundary. Globally disable with `add_filter('mhm_rentiva_vendor_profile_seo_disable', '__return_true')`.
* 🔘 Hero CTA stack — primary "View vehicles" button anchored to `#mhm-vendor-vehicles`, optional secondary "Send message" button only when a third-party messaging surface is wired via `mhm_rentiva_vendor_profile_message_url` filter. Both label + URL fully filterable.
* 🏷 `.label` CSS class renamed to `.mhm-vendor-section-label` to avoid collisions with theme/plugin generic `.label` styles.
* 📱 Mobile vehicle grid breakpoint moved from 480px → 600px so phone-landscape (568px) and small tablets collapse to a single column instead of leaving a solo card on its own row.
* 🐛 Fix: avatar `<img src>` was being stripped by `esc_url()` for the new SVG data URIs (data: scheme is not on the WordPress URL whitelist). Hero partial now branches on the scheme — `esc_attr()` for our own-class-built SVG payload, `esc_url()` for everything else.
* Tests: 987 → **1007 PHPUnit (+20 regression)**, 3208 assertions, 7 skipped. PHPCS: 0 errors.

= 4.37.1 — 2026-05-05 =
* 🐛 Fix: **Vendor profile badge stuck on "New Vendor"** — every vendor displayed the "New Vendor" badge on `/vendor/<slug>/` even when their reliability score, account age, and completed bookings all met the verified-vendor thresholds. Root cause: `mhm_rentiva_vendor_completed_bookings_count` filter callback was registered inside `VendorProfileExtension::register()` (admin-only init bucket), so on public frontend requests the filter had no callback and the count defaulted to 0. `VendorBadgeEligibility::evaluate()` now seeds the apply_filters() default from `ReliabilityScoreCalculator::count_completed_bookings($id, 9999)` so the badge works out of the box; filter callbacks may still override.
* 🐛 Fix: **Hero rating count label "X reviews" was misleading** — the number on the profile hero (e.g. "( 25 reviews )") was the sum of `_mhm_rentiva_rating_count` across the vendor's vehicles (booking-time star ratings), not WP review comment count. Vendors with 25 booking ratings but only 4 written reviews appeared inconsistent. The label now reads "X rating" / "X ratings" in English (Turkish: "X değerlendirme") to match the underlying data.
* 🛡 Defensive: Vendor profile review rating now resolves through `mhm_rating` (Rentiva canonical, set by VehicleRatingForm + ReviewEnforcer) first, then falls back to the WC-standard `rating` meta key. Reviews imported from external sources (WC product reviews migrated into vehicles, Site Reviews / Customer Reviews for WooCommerce, etc.) now surface their stars on the public profile.
* Tests: 982 → **987 PHPUnit (+5 regression)**, 3170 assertions, 7 skipped. PHPCS: 0 errors. Browser smoke green: vendors meeting thresholds now render "✓ Doğrulanmış Bayi" with star ratings on every review item.

= 4.37.0 — 2026-05-05 =
* 🌟 NEW: **Vendor Public Profile Page** — every approved vendor now gets a dedicated public-facing profile at `/vendor/<slug>/` (EN) and `/bayi/<slug>/` (TR). i18n-aware base URL pattern mirrors WooCommerce's translatable `/shop/`, `/product/` slugs. Site-owner override via the `mhm_rentiva_vendor_profile_url_base` filter.
* New canonical `[rentiva_vendor_profile]` shortcode + Gutenberg block (`mhm-rentiva/vendor-profile`) + Elementor widget (`mhm_rentiva_vendor_profile`) — all three delegate to a single renderer. Render Parity preserved.
* Schema.org `LocalBusiness` JSON-LD emitted in `<head>` for SEO. Yoast / Rank Math canonical filter compatibility (`wpseo_canonical` + `rank_math/frontend/canonical` at `PHP_INT_MAX-10`).
* Vendor admin profile: avatar upload (Media Library) + ASCII slug field with live collision suffix and slug-history tracking. Slug changes register a 301 redirect from the previous URL.
* Verified Vendor / New Vendor badges on the profile hero, with admin-configurable thresholds (completed bookings + reliability score).
* Pro-gated via `Mode::canUseVendorMarketplace`. Idempotent slug backfill migration runs once on upgrade. 7-trigger transient cache invalidator (booking save / user meta / comment status / lifecycle / profile_update).
* 🌐 i18n cleanup: 19 TR translations refreshed (4 new strings filled, 15 fuzzy markers cleared), including "No reviews yet — be the first to leave one." → "Henüz değerlendirme yok — ilk değerlendirmeyi siz yapın.", "Verified Vendor" → "Doğrulanmış Bayi", "View all vehicles →" → "Tüm araçları görüntüle →".
* Tests: **904 → 982 PHPUnit (+78 across nine implementation phases)**, 3162 assertions, 7 skipped. PHPCS: 0 errors.

= 4.36.0 — 2026-04-29 =
* New: Add-ons now support context (rental / transfer / both) and pricing types (per_booking / per_day / per_passenger)
* New: Transfer booking flow exposes a modal add-on picker with live total
* New: Admin add-on edit screen has Context radio + Pricing Type select with dynamic UI guard
* Migration: Existing add-on records auto-assigned to "rental" context with "per_booking" pricing
* I18n: 30+ new strings (English source, Turkish translations included)

= 4.35.0 — 2026-04-29 =
**Vendor Report / Appeal System (Pro)**

* New: shared infrastructure for vendor → admin reports across five contexts (booking, vehicle, vehicle_action, penalty, general). Single custom table (`{prefix}mhm_rentiva_vendor_reports`), single AJAX endpoint, single shared modal triggered from the vendor panel — Sorun Bildir on booking cards, İtiraz Et on paused/withdrawn vehicles and on each penalty row in the score history, Yöneticiye Yaz from the vendor panel footer.
* New: **Withdrawal/pause reason capture (Not 2)** — when a vendor pauses or withdraws a vehicle, a modal captures the reason and creates a `vehicle_action` report. The new `mhm_rentiva_before_apply_penalty` filter checks for an open report and suspends the score deduction + ledger entry until an admin resolves it. Resolved → vendor wins (no penalty). Rejected → deferred penalty applied retroactively.
* New: Admin "Bayi Raporları" page (Mode::canUseVendorMarketplace gated) — list with status + context filters, detail view with description, optional admin note, and resolve/reject/in-review actions. Form actions submit via admin-post handlers.
* New: two email notifications via the existing `VendorNotifications` registry — `vendor_report_received_admin` (new report → admin) and `vendor_report_resolved` (status change → vendor).
* New: penalty pipeline integration via two new filter hook points in `VehicleLifecycleManager::withdraw()` and `PenaltyRecorder::record_penalty()`. Cache invalidation on report CRUD; per-request memo so the dual hook check costs one DB query.
* Database: new `{prefix}mhm_rentiva_vendor_reports` table via dbDelta. `DatabaseMigrator::CURRENT_VERSION` bumped 3.5.0 → 3.6.0 to trigger the migration on existing installs. `context_id` is VARCHAR(64) so it can carry integer IDs (booking/vehicle), UUIDs (penalty ledger transaction), or NULL (general).
* Tests: 840 → 864 PHPUnit (+24 — Repository 10, Service 8, PenaltySuspensionHook 6). PHPCS: 0 errors. i18n: 30+ new TR strings translated.

= 4.34.2 — 2026-04-29 =
**Transfer-card favorite/compare button styling hotfix**

* Fixed: on the transfer-search results page, the favorite (heart) and compare buttons rendered as oversized solid-blue blocks instead of the small round white icons used on the rental vehicles grid/list. The styling rule in `vehicle-card.css` was scoped to `.mhm-vehicle-card` only — a deliberate specificity hack to outweigh Astra's generic `button` style — but the transfer cards use `.mhm-transfer-card` as their parent class, so the rule never applied and the theme defaults bled through. Both selectors are now listed (`.mhm-vehicle-card .mhm-card-favorite, .mhm-transfer-card .mhm-card-favorite`), keeping the icon styling identical across surfaces.

= 4.34.1 — 2026-04-29 =
**Popular Routes — clickable cards & deep-link pre-fill**

* New: each route card is now a single click target. Clicking a card opens the transfer-search page with the chosen `origin_id` and `destination_id` pre-filled in the form selects, so the visitor only adds date/time and submits.
* New: `mhm_rentiva_popular_routes_search_url` filter — themes/integrations can override the card-link base URL independently of the section "Search transfers" link.
* New: ARIA label per card ("Search transfers from {origin} to {destination}") for screen readers and crawlers.
* Changed: section header link relabeled from "View all" to "Search transfers" — more honest about what the link actually does (it lands on the transfer-search form, not a list of all routes).
* Changed: Transfer Search shortcode now reads `origin_id` / `destination_id` from the URL (`$_GET`) and pre-selects them in the form selects. Backwards compatible — existing installations without query params behave exactly as before.
* Tests: 837 → 840 PHPUnit (+3 — card link wrapper, query params, view-all label rename). PHPCS: 0 errors.

= 4.34.0 — 2026-04-29 =
**Popular Routes Showcase (Homepage Conversion)**

* Added: `[rentiva_popular_routes]` shortcode + Gutenberg block + Elementor widget — A → B transfer route cards with origin → destination, average duration, distance, and starting price. Single canonical renderer; block and widget delegate via do_shortcode().
* Added: Admin "🌟 Vitrine Koy" (Showcase) checkbox on the Transfer Routes form pins selected routes to the homepage block; pinned routes appear first with `order="featured"` (default) and exclusively when `featured_only="true"`.
* Added: 5 sort orders — `featured` (pinned first), `price_asc`, `price_desc`, `alphabetical`, `newest`.
* Added: Origin filters — `filter_origin_city` (e.g. "Istanbul") and `filter_origin_type` (airport / train / hotel / marina / city_center).
* Added: 16 attributes for full rendering control (heading, subheading, columns 2/3/4, theme light/dark, show_view_all, view_all_url, show_duration, show_distance, show_traffic_note, show_price, currency_symbol).
* Added: `mhm_rentiva_popular_routes_view_all_url` filter (themes/integrations supply transfer-search URL when no explicit `view_all_url` is set) and `mhm_rentiva_popular_routes_type_icon` filter (override emoji per location type).
* Database: new `is_featured tinyint(1)` column + index on `{prefix}rentiva_transfer_routes` (dbDelta idempotent migration; no downtime).
* Lite/Pro: Lite plans render up to 3 cards (matches the existing 3-route quota); Pro plans render up to the configured `limit` (default 6).
* Empty state: silent no-op — section never renders when no eligible routes exist (eligibility = both endpoints active + transfer-allowed).
* Performance: `TransferRouteProvider` repository with 1-hour transient cache, JOIN-based eligibility filtering, automatic cache invalidation on admin route CRUD.
* Tests: 824 → 837 PHPUnit (+13). PHPCS: 0 errors. i18n: 35+ new TR strings (frontend + admin + block + widget).

= 4.33.2 — 2026-04-29 =
**Turkish Terminology Cleanup**

* i18n (TR): Vendor translation migrated from "Satıcı" (seller) to "Bayi" (dealer/agency) — "Satıcı" was contextually misleading for a rental marketplace. ~130 strings touched with proper Turkish inflection (Bayinin, Bayiye, Bayilerden, etc.) and vowel harmony.
* No EN string changes, no code changes — pure translation polish.

= 4.33.1 — 2026-04-29 =
**Search Polish & UX Fixes**

* Fixed: the Lifecycle meta box on the vehicle edit screen now shows the vendor's name above the reliability score (display_name with user_login fallback).
* Fixed: Featured Vehicles slider no longer flickers on short lists — `loop` mode now activates only when slide count exceeds `columns × 2`.
* Added: `view_all_url` and `view_all_text` attributes for `rentiva_featured_vehicles` (shortcode + Elementor widget + Gutenberg block) — parity with Vehicles Grid.
* Tests: 822 → 824 PHPUnit (+2). PHPCS: 0 errors. i18n: 2 new TR strings.

= 4.33.0 — 2026-04-27 =
**Pro Gate Unification**

* Fixed: Pro feature gates now verify the RSA token at all 22 callsites (previously Mode::featureEnabled bypassed verification, vulnerable to cracked binary attacks).
* Fixed: License Admin shows actual granted Pro features instead of static "All Pro features active" string.
* Added: Developer Mode bypass — `define('MHM_RENTIVA_DEV_PRO', true)` + WP_DEBUG=true allows local Pro feature testing without a real token.
* Changed: BREAKING — Lite users can no longer use xlsx/pdf export formats. CSV/JSON remain free. Pro users unaffected (server v1.11.2 prerequisite).
* Note: Requires mhm-license-server v1.11.2 — without it, Pro users lose export for up to 24h. Click "Re-validate Now" on License page to refresh.

= 4.32.0 =
* **New: "Manage Subscription" button on the License page.** Opens the Polar customer portal in a new tab — cancel auto-renewal, update payment, switch plans, or resubscribe without leaving WP admin. Renders next to "Re-validate Now" and "Deactivate License" inside the License Management section, only when the license is active.
* **State-driven button emphasis.** Standard primary blue at >30 days, yellow at ≤30 days, amber + glow at ≤7 days. Customers always see how close their renewal is, regardless of whether they read the email reminders.
* **New: `LicenseManager::createCustomerPortalSession()` public method.** Calls the new `mhm-license-server v1.11.0+` endpoint `/licenses/customer-portal-session` (RSA-signed roundtrip, same pipeline as activate/validate). Returns `customer_portal_url` + `expires_at` on success, or `success=false` + `error_code` on any failure path.
* **New: `?license=manage_unavailable&reason=<code>` admin notice.** When the portal session mint fails (license not active, server 4xx, tampered response, network error), the admin lands back on the License page with a customer-friendly translated warning instead of a raw redirect. Five reason codes mapped to short Turkish labels via `get_manage_unavailable_label()`.
* **Pairs with `mhm-license-server v1.11.0+` and `mhm-polar-bridge v1.9.0+`.** Older servers without the customer-portal endpoint return a graceful `manage_unavailable` notice instead of breaking.
* **Tests:** 793 → 807 PHPUnit (+14: 5 LicenseManagerCustomerPortalSession, 4 LicenseAdminManageSubscription, 5 EmphasisClass). PHPCS: 0 errors. i18n: 9 new strings, all translated.

= 4.31.2 =
* **"Re-validate Now" button on the License page:** Clicking it bypasses the 5-minute throttle and forces an immediate server check, useful when an admin just had a licence revoked or re-issued on the licence-server side and does not want to wait for the next throttle/cron tick. Renders next to the existing "Deactivate License" button when a license is active. GET request guarded by a WordPress nonce (`mhm_rentiva_revalidate`).
* **New success notice:** `?license=revalidated` shows a "License re-validated" confirmation after the manual trigger completes.

= 4.31.1 =
* **Immediate license revocation:** A licence deactivated from the licence-server admin now propagates to the customer site within minutes instead of up to 24 hours. Three reinforcing layers: (a) the daily validation cron rotated to every 6 hours (existing schedules upgrade automatically on next plugin load), (b) a force-validate fires when an admin opens the License page (5 minute throttle so reloads do not hammer the server), (c) the cached `feature_token` is dropped on any non-active server response so `Mode::canUse*()` fails closed on the next page render even before the cron fires.
* **Defense-in-depth:** Combined with the v4.31.0 RSA verify chain this closes the "fake activate stays Pro for 24 h" window surfaced by manual server-side revocations.

= 4.31.0 =
* **BREAKING — Asymmetric crypto licence security:** Pro features now require an RSA-signed feature token from `mhm-license-server` v1.10.0+. The legacy `isPro()`-only fallback (engaged whenever `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` was unset, which was the zero-config default) has been removed. A cracked binary that patched `Mode::canUse*()` or `LicenseManager::isActive()` to always-return-true could re-enable Pro features on a real-license site under v4.30.x; v4.31.0 closes that hole because public keys can verify but cannot mint, so a forged token is rejected.
* **New:** `Admin/Licensing/LicenseServerPublicKey` — embedded RSA-2048 public key (no operator config required, ships with the build).
* **Changed:** `FeatureTokenVerifier` migrated from HMAC to `openssl_verify`. New API surface — `verify($token, $expectedSiteHash): bool` + `hasFeature($token, $featureName): bool`.
* **Changed:** `Mode::featureGranted()` requires an active license AND a valid RSA-signed token whose `site_hash` matches the local site AND which carries the requested feature flag. No legacy fallback.
* **Removed:** `ClientSecrets::getFeatureTokenKey()` and the `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY` wp-config constant — both were the symmetric remnants of v4.30.x. Safe to delete from wp-config.
* **Deploy ordering:** Upgrade `mhm-license-server` to v1.10.0 BEFORE upgrading clients to v4.31.0. New clients against an old server cannot verify the HMAC-signed token the old server still emits — Pro silently goes dark.
* **Tests:** 781 → 793 (+12). RSA verify roundtrip with paired fixture key, foreign-key forge rejection, signature-byte tamper, payload tamper, expired-token, site_hash mismatch, no-legacy-fallback enforcement, BootIsolation pro-state seeding token. PHPCS clean.

= 4.30.2 =
* **License notice rendering — defensive fix:** When the License page URL had `?license=error` but no `&message=...` (stale URL state from browser back/forward, bookmark, or truncated copy-paste), the notice rendered "License activation failed: " with an empty trailing space. v4.30.2 skips the notice entirely when the error code is missing.
* **License notice — friendly mappings for v1.8.0+/v1.9.x server error codes:** `site_unreachable`, `site_verification_failed`, `tampered_response`, `product_mismatch`, `product_slug_required` now produce customer-friendly Turkish-translated messages instead of falling through to the raw "License activation failed: <technical_code>" default.
* **Notice default — generic message + data attribute:** Unknown future error codes render a generic "License activation failed. Please try again." with the technical code exposed via `data-error-code` HTML attribute (debug/support only — not shown to end users).
* **Tests:** 5 new unit tests under `LicenseAdminAdminNoticesTest`. 776 → 781 PHPUnit, PHPCS clean.

= 4.30.1 =
* **Reverse-validation UX fix:** v4.30.0 made `MHM_RENTIVA_LICENSE_PING_SECRET` mandatory — without it, the verify endpoint returned `ping_secret_not_configured` and the license server rejected activation with `site_unreachable`. That meant every customer site needed an operator-pinned secret in `wp-config.php`, which is unworkable for an end-customer product. v4.30.1 falls back to the per-activation `site_hash` (already shared between server and client via the activate body) when `PING_SECRET` is unset. Customers can now activate licenses without any `wp-config.php` edits.
* **Backward compatible:** When `MHM_RENTIVA_LICENSE_PING_SECRET` is defined the endpoint still uses it, so v4.30.0 deploys with the operator config baked in keep working unchanged.
* **Pairs with `mhm-license-server v1.9.1+`:** The server applies the matching `site_hash` fallback in `Security\SiteVerifier::verify()`. Older v1.9.0 servers that already pin `PING_SECRET` work unchanged via the legacy path.
* **Tests:** Updated `VerifyEndpointTest` to cover the site_hash fallback path. 776/776 PHPUnit, PHPCS clean.

= 4.30.0 =
* **Security hardening — Phase B (client side):** Adds three new defenses against source-edit Pro feature bypass, paired with `mhm-license-server v1.9.0+`. Layer 1: every successful activate/validate response is now HMAC-verified against `MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET`; tampered responses return `tampered_response` instead of unlocking Pro. Layer 2: a new public REST route `/wp-json/mhm-rentiva-verify/v1/ping` answers the server's `X-MHM-Challenge` during activation, proving the site is reachable and shares the ping secret. Layer 3: `Mode::canUseVendorMarketplace()`, `canUseMessages()`, `canUseAdvancedReports()`, `canUseVendorPayout()` no longer trust `LicenseManager::isActive()` alone — they require a feature flag inside a server-issued, HMAC-signed feature token (24h TTL). A `return true;` patch on `isActive()` no longer unlocks Pro features.
* **Required wp-config constants:** Add `MHM_RENTIVA_LICENSE_RESPONSE_HMAC_SECRET`, `MHM_RENTIVA_LICENSE_FEATURE_TOKEN_KEY`, `MHM_RENTIVA_LICENSE_PING_SECRET` to `wp-config.php`. Each value MUST match the corresponding constant on the license server. If `FEATURE_TOKEN_KEY` is omitted, gates fall back to legacy `isPro()` behavior so existing customers are not broken during rollout.
* **Backward compatible with v1.8.x license servers:** Responses without a `signature` field are accepted unchanged so this client can talk to a not-yet-upgraded license server during the rollout window.
* **New helpers:** `Admin\Licensing\ClientSecrets`, `Admin\Licensing\ResponseVerifier`, `Admin\Licensing\FeatureTokenVerifier`, `Admin\Licensing\VerifyEndpoint`. Activate request now sends `client_version` so the server can apply per-version reverse-validation enforcement.
* **Tests:** 740 → 776 (+36 new), 2715 assertions, 6 skipped, all green. PHPCS: 0 errors.

= 4.27.6 =
* **WordPress.org submission polish:** Cleaned up Plugin Check results to zero ERROR for plugin directory submission. Replaced `unlink()` with `wp_delete_file()` in the demo image importer. Added gerekçeli `phpcs:ignore` comments for false-positive warnings that the project's `phpcs.xml` ruleset already excluded (error_log audit logging, `mhm_rentiva_` prefixed hooks, `meta_query` / `post__not_in` accepted performance tradeoffs, interpolated SQL with core-controlled table names bound via `$wpdb->prepare()`). Renamed a template-scope variable to honor Plugin Check's prefix convention. Trimmed the readme changelog to fit WP.org's 5000-character limit, linking older releases to GitHub. No functional changes — 740/740 PHPUnit tests still pass.

= 4.27.5 =
* **Security:** License client now participates in per-product license binding introduced by `mhm-license-server v1.8.0+`. Requires that same server version.
* **UI fix:** Additional Services list rendered two Documentation buttons; the global `all_admin_notices` docs-link hook no longer fires on the addon screen (the custom page header already provides one).

= 4.27.4 =
* **Fix (architectural):** The v4.27.1 and v4.27.2 data cleanup migrations never actually executed on upgraded sites — only on fresh installs. The `plugins_loaded` migration trigger bailed out of ALL migrations whenever `get_option('mhm_rentiva_plugin_version') === MHM_RENTIVA_VERSION`, but `mhm_rentiva_single_site_activation()` stamps that version BEFORE the first `plugins_loaded` fires. After a ZIP-replace upgrade (the common case), the version stamp is already current, the drift check short-circuits, and new flag-guarded cleanups are skipped forever. This is why mhmrentiva.com still showed Brand Name = "1" and Currency = "1" (stats cards rendering "0,00 1") after upgrading from v4.27.0 → v4.27.2. Fix: the migration trigger now runs on two independent lanes. Lane A — schema drift — still runs `DatabaseMigrator::run_migrations()` only when the version differs. Lane B — per-flag data cleanups (`migrate_remove_auto_populated_labels`, `migrate_clean_test_pollution`) — runs on every admin request; each migration returns immediately once its own flag is set, so the overhead is a single `get_option()` call on steady state. Three new regression tests in `tests/Migration/MigrationLaneIndependenceTest.php` prove both migrations execute when the version stamp is already current.

= 4.27.3 =
* **Fix:** v4.27.2's attempt to de-duplicate the Lite "Additional Services limit" admin notice was incomplete; on production sites the notice still rendered twice. Root cause (confirmed on the live DOM): the custom page header inside `add_addon_page_title()` emitted its own `<hr class="wp-header-end">` marker, and WordPress core also emits one for the built-in post-type list heading. WP's `wp-admin/js/common.js` relocator calls `$( 'hr.wp-header-end' ).before( $notice )` — jQuery's `.before()` clones its argument for every matched target except the last, so the single notice got duplicated to one copy per marker. Fix: `AdminHelperTrait::render_admin_header()` now accepts a `$skip_wp_header_end` parameter; `AddonMenu::add_addon_page_title()` passes `true` on the addon list screen so only WordPress's own marker remains in the DOM. Verified in the production browser after deploy — notice now renders exactly once.

= 4.27.2 =
* **Fix (critical, fresh install):** Running the Settings → Settings Testing "Run All Diagnostics" page even once could leak test payloads into the live settings. On a fresh install this showed up as Brand Name = "1", Cancellation Deadline = 1, Payment Deadline = 1, and other Text / Email / URL fields set to "1". Root cause: the diagnostic harness flipped empty strings to "1" to force a "changed" save, fed that through the real sanitizer (which rewrites every field of the targeted tab, not just the tested keys), and then restored only the keys explicitly under test. Collateral writes to other tab fields survived. **Fix:** the harness now snapshots and restores the entire `mhm_rentiva_settings` option so the test is truly read-only, and a one-time migration (`migrate_clean_test_pollution`) clears the `"1"` / `"0"` pollution fingerprint from Text / Email / URL / currency fields on upgrade. Numeric and user custom values are left alone.
* **Fix:** The Additional Services admin page rendered the Lite-tier limit notice twice — once above the stats cards and once below — because it was emitted inside a nested `<div class="wrap">` that confused WordPress's core notice-relocator JS. The notice is now emitted by a dedicated `admin_notices` callback at priority 20, so WordPress places it exactly once.
* **Fix:** Settings Testing "Defaults Set" check no longer flags phantom settings that were never registered anywhere (`mhm_rentiva_timezone`, `mhm_rentiva_db_auto_optimize`, `mhm_rentiva_wp_optimization_enabled`, `mhm_rentiva_my_account_url`). Cascading "Email Address Valid" / "Email Validation Works" failures caused by the underlying pollution also clear once the migration runs.
* **Tests:** Five regression tests in `tests/Migration/SettingsTestPollutionMigrationTest.php` — pollution removal on text/email/URL/currency, preservation of legitimate user values, numeric fields untouched, idempotency across legitimate post-migration edits, fresh-install flag seeding.

= Older versions =

Full changelog for 4.27.1 and earlier releases: https://github.com/MaxHandMade/mhm-rentiva/releases

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
