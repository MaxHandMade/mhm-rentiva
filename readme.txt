=== MHM Rentiva ===
Contributors: mhmdevelopment
Tags: car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MHM Rentiva is a powerful and flexible vehicle rental management plugin with secure WooCommerce integration for all frontend bookings.

== Description ==

MHM Rentiva is a comprehensive vehicle rental management solution designed for car rental agencies, bike rentals, and equipment rental businesses. It provides a dedicated and streamlined experience for managing your fleet and bookings. Frontend booking and payment processing are handled securely via WooCommerce, while administrators retain full control over manual bookings.

**Key Features:**

*   **Vehicle Management:** Easily add, edit, and manage your vehicle fleet with detailed attributes (transmission, fuel type, seats, etc.).
*   **Booking System:** Robust booking engine with calendar view, availability checking, and automatic price calculation.
*   **Payment Integration:** Seamless WooCommerce integration for all frontend payments (Online & Offline methods). Admin-only native offline payment support for manual bookings.
*   **Customer Management:** Manage customer information and booking history.
*   **Email Notifications:** Customizable email templates for booking confirmations, cancellations, and more.
*   **Shortcode Support:** Easy-to-use shortcodes to display vehicle lists, search forms, and booking wizards anywhere on your site.
*   **VIP Transfer Module:** Integrated point-to-point transfer booking system with distance-based pricing and vehicle selection.
*   **REST API:** Full REST API support for mobile app or external integrations.

== Project Structure ==

mhm-rentiva/
├── assets/             # Frontend assets (CSS, JS, Images)
├── languages/          # Translation files (14 files)
├── src/                # PHP Source Code (PSR-4 logic)
│   ├── Admin/         # Plugin Core Components
│   │   ├── Booking, Vehicle, Payment, Customers, 
│   │   ├── Licensing, REST, Reports, Settings,
│   │   └── Transfer, Utilities, PostTypes...
│   └── Plugin.php      # Main initialization class
├── templates/          # Frontend & Email Templates
├── mhm-rentiva.php     # Main entry point
├── uninstall.php       # Cleanup on deletion
├── changelog.json      # English version history
├── changelog-tr.json   # Turkish version history
├── LICENSE             # GPL License
├── readme.txt          # WP.org metadata
├── README.md           # Documentation (English)
└── README-tr.md        # Documentation (Turkish)

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to the 'Rentiva' menu to configure your settings and add vehicles.
4.  Use the shortcodes `[mhm_rentiva_vehicles]`, `[mhm_rentiva_search]` or `[mhm_rentiva_transfer_search]` to display your fleet and booking forms.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
Yes, MHM Rentiva requires WooCommerce for all frontend booking and payment processing actions. This ensures secure and standard payment handling for your customers. However, administrators can create and manage manual bookings in the backend without WooCommerce.

= Can I accept credit card payments? =
Yes, via the WooCommerce integration. You can use any payment gateway supported by WooCommerce (Stripe, PayPal, Bank Transfer, Cash on Delivery, etc.) to accept payments on your rental site.

= Is it compatible with the latest WordPress version? =
Yes, we actively test and update the plugin to ensure compatibility with the latest WordPress releases.

== Screenshots ==

1.  **Dashboard:** Overview of your rental business.
2.  **Vehicle List:** Manage your fleet easily.
3.  **Booking Calendar:** Visual calendar for managing reservations.
4.  **Settings:** Comprehensive configuration options.

== Changelog ==

= 4.6.1 =
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
* Search: New AJAX-based transfer search shortcode [mhm_rentiva_transfer_search].
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
