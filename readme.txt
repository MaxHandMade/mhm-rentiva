=== MHM Rentiva ===
Contributors:     mhmdevelopment
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      6.9
Requires PHP:      8.1
Stable tag:        4.25.0
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
*   **Gutenberg Blocks:** 19 blocks with Render Parity architecture — identical output across Gutenberg, Elementor, and shortcodes.
*   **Elementor Widgets:** 20 widgets with advanced controls, live preview, and responsive design.

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

= 4.25.0 =
* **Vendor Email Templates:** 22 vendor notification templates now editable from admin panel (Settings → Notification Templates → Vendor Notifications).
* **Email Layout:** Improved gradient rendering in Gold Standard layout; WCAG AA contrast fix for amber CTA button (2.97:1 → 6.8:1).
* **Fix:** Site Instance Check-in cron now visible in Cron Job Monitor with Run Now button.
* **Fix:** Plugin explicitly registers the 'weekly' WP cron interval — no longer relies on WooCommerce.
* **i18n:** 56 new Turkish translations for vendor email admin UI.

= 4.24.0 =
* **Vehicle Lifecycle Management:** Complete state machine — Active, Paused, Expired, Withdrawn states with enforced transition rules.
* **Listing Duration:** 90-day listing period with automatic cron-based expiry and 10-day/3-day warning emails.
* **Vendor Self-Service:** AJAX endpoints for pause, resume, withdraw, renew, and relist actions.
* **Progressive Penalties:** Withdrawal penalty system — 1st free, 2nd 10%, 3rd+ 25% of monthly avg revenue (12-month rolling window).
* **Anti-Gaming:** Vendor-cancelled booking dates re-blocked for 30 days to prevent price manipulation.
* **Reliability Score:** Vendor reliability score (0-100) with daily cron recalculation based on cancellations, withdrawals, pauses, and completions.
* **Email Notifications:** 9 new lifecycle email templates — activated, paused, resumed, withdrawn, expired, expiry warnings, renewed, relisted.
* **Admin UI:** Lifecycle status column on vehicle list table, lifecycle meta box on vehicle edit screen, vendor reliability column on users list.
* **Active Vehicle Filter:** All 6 frontend shortcodes now filter out maintenance/inactive vehicles via MetaQueryHelper.
* **Vendor Forms:** City selectWoo migration, vendor settings redesign, account holder/tax office fields, login redirect.
* **Database Migration:** v3.5.0 — vehicle lifecycle status migration for existing active vehicles.
* **Tests:** 665 tests, 2248 assertions (up from 567/2036).

= 4.23.0 =
* **Vendor Transfer Architecture:** City-based location hierarchy for vendor marketplace — vendors see only their city's transfer locations and routes.
* **Vendor Pricing:** Vendors set per-route transfer prices within admin-defined min/max bounds.
* **Transfer Search Engine:** Route-based vehicle filtering with vendor-specific pricing (fallback to route base price).
* **Database Migration:** Added `city` column to transfer locations table, `max_price` to routes table (v3.4.0 migration).
* **Admin Transfer UI:** City input on location form, max_price on route form, vendor city display on vehicle meta box.
* **Dashboard Widgets:** 11 bug fixes — timezone corrections, cache invalidation, WC email vehicle thumbnails, stats widget design, Lite gating.
* **Search & Filters:** Fixed strtotime('') silent success bug, location filter radio→checkbox, transfer tab JS validation.
* **Blocked Dates:** Fixed "Apply to All" AJAX sending unsaved browser state, PHP now reads from payload with DB fallback.
* **Export Module:** Fixed payment logs post_type mismatch, record count always 0, delete history placeholder, PHP 8 type error.
* **Vendor Forms:** Vehicle document verification (ruhsat upload), edit form with re-review on critical changes, vendor badge on cards.
* **Elementor Widgets:** Attribute exposure improved for VehicleComparison, MyBookings, MyFavorites, VehicleDetails, Testimonials, VehiclesGrid, FeaturedVehicles.
* **Settings:** Cache section deduplication, dead frontend settings removed, SetupWizard required pages fixed, AssetManager admin scope guard.
* **i18n:** 15+ new Turkish translations for vendor transfer form labels, .l10n.php regenerated for WP 6.5+ compatibility.
* **Tests:** 567 tests, 2036 assertions (up from 562/2024).

= 4.22.2 =
* **Notices:** Standardized all Lite limit notices to unified percentage format below KPI cards.
* **Lite/Pro:** Raised gallery images limit 3→5, redesigned comparison table, fixed emoji corruption.

= 4.22.0 =
* **Audit:** Comprehensive AllowlistRegistry, BlockRegistry, and Elementor widget audit.
* **Tests:** 13 shortcode render test files, 4 SettingsSanitizer test files added.
* **Fixes:** 6 PHPUnit failures resolved, block.json defaults corrected.
* **Quality:** 563 tests, 2022 assertions.

= 4.21.2 =
* **Security:** Hardened REST API security with SecurityHelper and AuthHelper enforcement.
* **Architecture:** Consolidated all shortcodes documentation via ShortcodeServiceProvider.
* **Compliance:** Standardized minimum requirements to PHP 8.1+ and WordPress 6.7+.
* **Transfer:** Updated VIP Transfer module with point-to-point route pricing engine.
* **Cleanup:** Removed obsolete mhm_ prefix from several frontend shortcodes.

= 4.21.0 =
* **Engine:** Added deterministic A/B variant resolver for upgrade experiments.
* **Telemetry:** Upgrade telemetry now records variant-aware events.
* **Analytics:** Funnel analytics aggregates variant performance metrics.

= 4.20.9 =
* **Dashboard:** Added Upgrade Funnel Analytics dashboard in the admin panel.
* **Services:** Introduced FunnelAnalyticsService for telemetry aggregation.

= 4.20.8 =
* **Telemetry:** Added deterministic Lite-to-Pro upgrade funnel telemetry (admin-only).
* **Security:** Secured upgrade CTA surfaces with nonce and capability enforcement.

= 4.9.9 =
* **Audit & Optimize (M1-M5):** Finalized the foundational hardening series (Mission Complete).
* **M3 Meta Migration:** Successfully retired legacy meta keys for Featured and Status fields with 100% runtime integrity.
* **Performance:** Optimized core vehicle lookup paths and eliminated asset leakage.
* **Cleanup:** Resolved all IDE linter warnings and hardened global variable access ($_GET/$_POST).
* **Quality Gates:** Passed full PHPUnit suite and release-readiness checks with 0 errors.

= 4.9.8 =
* **WooCommerce Integration:** Implemented absolute priority for physical page slugs in My Account endpoints (v1.1.0 logic).
* **UI/UX:** Fixed integrated dashboard layout to prevent unwanted redirects while maintaining custom URLs (e.g., /hesabim/favorilerim/).
* **Stability:** Forced rewrite rule flush on version update to ensure URL consistency.

= 4.9.7 =
* Feature: Standardized Gutenberg block sidebars (InspectorControls) for consistent UI/UX.
* Fix: Resolved console warnings in block editor for improved stability.
* Improvement: Updated project documentation and guidelines.

= 4.9.6 =
* **Performance:** Overall code optimization and performance improvements.
* **Refactoring:** CSS architecture cleanup and refactoring for better maintainability.
* **Markup:** Updated HTML structure for the Availability Calendar for better accessibility and design.
* **i18n:** Added new translation files and improved existing strings.
* **Transfer Module:** Finalized prefix standardization (rentiva_) and database migration logic.

= 4.9.2 =
* Optimization: Standardized the Transfer Search shortcode to `rentiva_transfer_search` for prefix consistency.
* Compatibility: Added a backward compatibility alias for the old transfer shortcode.
* Documentation: Updated "Kısa Kod Sayfaları" and official documentation to reflect naming standards.

= 4.9.1 =
* Optimization: Refactored deprecated jQuery methods (.bind, .unbind) to modern standards (.on, .off).
* Performance: Implemented passive event listeners for scroll and touch events to improve PageSpeed scores.
* Fix: Resolved a critical Red Error in Vehicle Editor by fixing nonce mismatch and missing script dependencies.
* Fix: Synchronized plugin version constants and headers.

= 4.9.0 =
* **Default Value Persistence:** Ensured that the default 10% deposit is saved as a real value in the database even if the field is left empty.
* **Auto-Correction Logic:** Implemented logic to treat "0" or empty deposit inputs as the standard 10% fallback during save and load cycles.
* **UI Clarification:** The "10" value now appears as solid black text (active value) instead of a ghost placeholder when no value is set.

= 4.8.9 =
* **Universal Default Deposit:** Set the global default deposit to 10% for all vehicles without a defined value.
* **Safety Cap Enforcement:** Implemented a strict 50% maximum limit for deposit values to prevent over-collection.
* **Pre-filled Meta:** New vehicles now automatically pre-fill the "Deposit" field with "10" in the admin dashboard.

= 4.8.7 =
* **Deposit Logic Refactoring:** Restricted the "Deposit" field to strictly accept percentage values (%).
* **Legacy Support Removal:** Removed support for fixed currency amounts for deposits to ensure pricing consistency.
* **UI Suffix Upgrade:** Added a permanent "%" unit suffix to the deposit input field in the vehicle meta editor.
* **Calculation Synchronization:** Optimized the real-time pricing logic to interpret input as a multiplier of the total booking amount.
* **Sanitization Shield:** Implemented strict 0-100 range validation for deposit values at the database level.

= 4.8.6 =
* **Ultimate UI Clarity:** Fixed "faint/dimmed" checkboxes and ticks on mobile.
* **High Contrast Borders:** Implemented 2px thick, dark gray borders (#8c8f94) for better visibility.
* **Sharp White Ticks:** Switched to a CSS-based border tick for maximum sharpness on blue background.
* **Opacity Lockdown:** Enforced 100% opacity on all checkbox components to prevent visual dimming.

= 4.8.5 =
* **Mobile Visual Fix:** Corrected checkbox checkmark (tick) misalignment on mobile viewports.
* **Absolute Centering:** Implemented an absolute positioning strategy for the `:before` pseudo-element to ensure the tick remains perfectly centered within the 16px box.
* **CSS Robustness:** Added SVG-based checkmark fallback to guarantee consistent rendering across different browsers.

= 4.8.4 =
* **UI Precision:** Fixed the misalignment of "Remove" (X) buttons on the Vehicle Settings page.
* **Flexbox Alignment:** Standardized the checkbox item structure using a master flex container to ensure buttons are pinned to the right and vertically centered.
* **Mobile Regression Fix:** Forced 100% width and box-sizing on mobile viewports to prevent layout shifting and horizontal overflow.
* **Asset Versioning:** Incremented version to clear browser and server-side caches.

= 4.8.3 =
* **UI Standardization:** Introduced the `.mhm-ui-checkbox` global component in `core.css` for consistent design across the whole plugin.
* **Proportional Scaling:** Standardized checkbox size to 16px and label text to 14px to fix disproportionate scaling issues.
* **Mobile Fix:** Enforced absolute row alignment for checkboxes and labels on all viewports, preventing vertical stacking bugs on mobile.
* **Refactoring:** Converted Vehicle Settings and Vehicle Meta boxes to use the new global UI standard.

= 4.8.2 =
* **UI Reorganization:** Relocated the "Deposit" (Depozito) field to the "Core Details" (Temel Detaylar) section.
* **Security:** The Deposit field is now permanent and protected against removal to maintain plugin data integrity.
* **Bug Fix:** Explicitly blocked 'deposit' key deletion in the AJAX removal handler.

= 4.8.1 =
* **Restoration:** Missing "Deposit" (Depozito) field restored in core configurations and database fallback.
* **Data Recovery:** Hardened metadata label retrieval to prevent "Empty Label" syndrome in Add/Edit Vehicle editor.
* **Hardening:** Added defensive data recovery checks to ensure standard features (Klima, ABS, etc.) are never lost due to corrupted stored values.
* **Bug Fix:** Fixed destructive save logic in AJAX settings that could overwrite master definitions with empty labels.

= 4.8.0 =
*   **UI Symmetry & Proportional Harmonization:** Araç Ayarları sayfasındaki tüm checkbox bileşenleri için yeni bir nested mimari (div > label) uygulandı. Tüm bölümlerde (Detaylar, Özellikler, Ekipmanlar) yapısal birlik sağlandı. Ölçüler Checkbox için 16px, metinler için 14px olarak standardize edildi. Mobil görünümde yazıların alta kayması engellendi ve dikey ortalama sorunları giderildi.
*   **Bug Fix:** Fixed JavaScript SyntaxError in Messages module.

= 4.7.8 =
*   **Total UI Unification:** Araç Ayarları sayfasındaki checkbox stilleri standardize edildi. Mavi kutu görünümleri kaldırılarak "Görüntüleme Seçenekleri"ndeki temiz beyaz görünüme geçildi. Özel SVG tikleri yerine standart WordPress işaretçileri kullanılarak tam bir görsel bütünlük sağlandı. PHP templatelerindeki tüm inline CSS'ler temizlendi.

= 4.7.7 =
*   **Tab-Specific Settings Reset:** "Varsayılanlara Dön" butonu artık sekmeye duyarlı çalışıyor. "Alan Tanımları" sekmesindeyken yapılan reset işlemi "Görüntüleme Seçenekleri"ni bozmuyor, aynı şekilde tersi de geçerli. Dosya sonundaki sözdizimi hataları temizlendi.

= 4.7.6 =
*   **Data Persistence & Auto-Recovery:** Araç özelliklerinin kaybolmasına neden olan yıkıcı kaydetme mantığı kaldırıldı. Veritabanından gelen boş etiketlerin ana listeyi bozmasını engelleyen "yalnızca doluysa birleştir" mantığı getirildi. Standart etiketler için otomatik kurtarma sistemi devreye alındı.

= 4.7.5 =
*   **Data Persistence & Label Recovery:** Ayar kaydedildiğinde etiketlerin ve özel alanların veritabanından silinmesine neden olan mantık hatası düzeltildi. Seçim durumu (checked) ile alan tanımı (label/key) arasındaki mantıksal ayrım güçlendirildi. Standart etiketlerin bozulma durumunda otomatik kurtarılması sağlandı.

= 4.7.4 =
*   **Functional Restoration & Tick Alignment:** Checkbox tıklanabilirlik sorunu giderildi. Tıklanamayan onay kutuları `appearance: none` refaktörü ile düzeltildi. Tik işareti sadece seçili olduğunda görünecek şekilde CSS mantığı güncellendi ve merkezlendi.

= 4.7.3 =
*   **UI Precision & Structural Sync:** Checkbox onay işaretlerinin (tick) aşağı kayma sorunu CSS pseudo-element müdahalesiyle çözüldü. "Araç Özellikleri" bölüm yapısı "Araç Ekipmanları" ile senkronize edilerek mobildeki sıkışmalar giderildi ve görsel simetri sağlandı.

= 4.7.2 =
*   **UI Polish & Scaling:** Araç Ayarları sayfası için kompakt tasarım ve kesin mobil taşma koruması uygulandı. Padding ve font boyutları WordPress standartlarına çekildi, mobildeki tüm taşmalar (overflow) agresif CSS kurallarıyla engellendi.

= 4.7.1 =
*   **Vehicle Settings UI Hotfix:** Gereğinden fazla özelleştirilmiş "mavi" checkbox stilleri geri çekilerek standart WordPress görünümü sağlandı. Mobil görünümde uzun etiketlerin taşmasını engellemek için metin kaydırma (white-space: normal) ve üst hizalama (align-items: flex-start) kuralları uygulandı. Sürüm senkronize edildi.

= 4.6.7 =
* Security: Replaced wp_redirect with wp_safe_redirect for enhanced safety.
* Compliance: Refactored inline styles to use the native wp_add_inline_style() API.
* Performance: Optimized shortcode caching with a versioned system, eliminating the need for direct SQL cleanup.
* Security: Hardened SecurityHelper safe_output method for stricter XSS protection.

= 4.6.6 =
* Standards: Successfully applied 110,000+ automated style fixes for strict WPCS compliance.
* Security: Resolved 50+ WPCS security issues (Input Sanitization & Database Interpolation).
* Security: Hardened Handler.php and AccountController.php against XSS attacks.
* Engine: Migrated from legacy error_log to high-performance AdvancedLogger.
 
= 4.6.4 =
* Security: Hardened output escaping in About, System Info, and Admin Tabs using esc_html and wp_kses_post.
* Data Integrity: Enhanced sanitization for price and ID fields in AddonManager and Booking Meta Boxes.
* Localization: Translated remaining Turkish administrative strings in Manual Booking and Vehicle management to English.
* UI Fix: Resolved hardcoded currency symbol issues in Booking Edit screens for better multi-currency support.
* Cleanup: Optimized SQL queries with proper preparation in Vehicle Columns and System Info classes.
 
= 4.6.3 =
* Security: SQL Injection protection hardened for message search queries using prepared statements.
* REST API: Completely refactored Integration Settings to ensure AJAX button reliability.
* Standards: Full source code documentation (comments/docblocks) translated to English for WPCS compliance.
* Reliability: Improved AJAX hook registration to prevent '-1' errors on asynchronous requests.
* Bug Fix: Resolved IP list sanitization errors in REST settings.
 
= 4.6.2 =
* Critical: Comprehensive DatabaseCleaner meta key protection to prevent potential data loss.
* WooCommerce: Atomic Overlap Lock mechanism added to prevent duplicate bookings.
* Fix: Resolved tax calculation issue for deposit payments (calculates on total amount).
* Security: SQL queries hardened with prepared statements.
* UI: Added informative notice in Payment Settings when WooCommerce integration is active.

= 4.6.0 =
* New Module: VIP Transfer Module (Chauffeur Service).
* Transfer: Point-to-point booking with distance or fixed pricing.
* Transfer: Pickup/Dropoff location management and route definitions.
* Transfer: Seamless integration with WooCommerce Cart and Checkout.
* Search: New AJAX-based transfer search shortcode [rentiva_transfer_search].
* Feature: Added Buffer Time logic for operational control.

= 4.5.5 =
* Frontend Polish: Fixed "Out of Use" badge logic and mobile overflow on Vehicle Details.
* Search UI: Standardized button colors and added status indicators.
* Comparison UI: Improved card alignment and styling.
* Favorites UI: Added "Out of Use" badge and disabled booking for unavailable vehicles.
* Bookings UI: Optimized table layout to prevent horizontal scrolling.
* Localization: Added missing strings to POT file.

= 4.5.4 =
* Refactoring: Major settings core refactoring for improved structure.
* UX: Payment Settings hidden appropriately when WooCommerce is active.
* Fix: Restored access to Email Settings when WooCommerce is active.
* Bug Fix: Fixed Fatal Error in Offline Payment Emails tab (missing class).
* i18n: Regenerated POT file with new localized strings.

= 4.5.0 =
*   Version Update: Major update to 4.5.0 focusing on User Account and Payment History improvements.
*   Account Endpoints: Added customizable endpoints for Bookings, Favorites, Payments, Messages, and Edit Account.
*   Payment History: Enhanced with receipt upload preview and "Remove Receipt" functionality.
*   Receipt Management: Secure backend logic for deleting receipt attachments.
*   Booking Details: Improved "View" button functionality in My Account.
*   UI Modernization: Redesigned Bookings table with better spacing and hover effects.
*   Password Toggles: Fixed visibility and styling of password toggles on Account Details page.
*   Bug Fixes: Resolved Fatal Error in BookingColumns.php and WooCommerce endpoint conflicts.
*   URL Handling: Updated settings to allow relative paths.
*   Payment Display: Improved status translation and payment method display.

= 4.4.5 =
*   Version Update: Version bump to 4.4.5 for maintenance and stability improvements.
*   Elementor Widgets: Enhanced Elementor integration with 19 comprehensive widgets.
*   Widget Category: All widgets organized under "MHM Rentiva" category.
*   Widget Controls: Advanced styling options added to all widgets.
*   Responsive Design: Mobile-first approach for all widgets.
*   Widget Integration: Seamless live preview and drag-and-drop support.

= 4.4.4 =
*   Vehicle Limits: All hardcoded vehicle limits made configurable (engine size: 0.0-20.0L, seats: max 100, doors: max 20, gallery: max 50 images).
*   Fuel Types: Added LPG, CNG, and Hydrogen fuel types to support alternative fuel vehicles.
*   Transmission Types: Added Semi-Automatic and CVT transmission options for modern vehicles.
*   WooCommerce Refunds: Complete WooCommerce refund integration - automatic booking status updates when refunds are processed.
*   Refund System: RefundValidator now supports WooCommerce payments (previously only offline).
*   Dynamic Currency: Currency defaults now dynamically retrieved from WooCommerce or plugin settings.
*   Thank You Page: New dedicated thank you page shortcode [rentiva_thank_you] for booking confirmations.
*   Booking Enhancements: Added booking reference number, booking type, and special notes/requests fields.
*   Vehicle Selection: Vehicle field in booking edit is now editable with license plate display.
*   Form Cleanup: Removed redundant fields from booking form - now handled by WooCommerce.
*   Checkout Integration: Payment type options moved to WooCommerce checkout page.
*   Single Column Form: Booking form redesigned as single-column layout for better mobile experience.
*   Time Fields: Pickup time is now mandatory, return time automatically matches pickup time.
*   Modern UI: Availability check result area redesigned with modern gradients and animations.
*   Duplicate Prevention: Enhanced atomic overlap checking to prevent duplicate reservations.
*   Auto-Cancel: Fixed automatic cancellation system for unpaid bookings.
*   Meta Key Standardization: Unified date/time meta keys with backward compatibility.
*   Availability Validation: Added final availability check before WooCommerce payment processing.
*   Email System: Fixed email template auto-loading in admin booking edit page.
*   i18n: Updated .pot file with all new translatable strings.

= 4.4.3 =
*   Legacy Removal: Removed all legacy payment gateway code (Stripe, PayPal, PayTR) to focus on Offline payments and WooCommerce integration.
*   Cleanup: Removed unused assets and rate limiting logic related to legacy gateways.
*   Documentation: Updated READMEs and documentation to reflect the new payment system focus.
*   Bug Fix: Fixed changelog language detection to show Turkish changelog for Turkish users.
*   UI Fix: Fixed duplicate toggle buttons in changelog display.

= 4.4.2 =
*   Security: Removed hardcoded secure token and implemented dynamic key generation.
*   Refactoring: Separated settings page view logic into SettingsView class.
*   Bug Fix: Resolved automatic booking cancellation cron job issue.
*   Compliance: Added WordPress.org standard readme.txt and license headers.
*   Cleanup: Centralized sanitization helpers and removed manual require_once calls.

= 4.4.1 =
*   Global Readiness: Completed English translations across Elementor widgets, Gutenberg blocks, payment gateways.
*   Elementor & Gutenberg: Vehicles List, Booking Form, and Vehicle Card widgets expose English titles.
*   I18n QA: Added translator comments and positional placeholders.
*   Currency & Deposits: Deposit meta boxes and helpers now format prices via Settings-based currency.
*   Documentation: README badges and release notes updated.

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
