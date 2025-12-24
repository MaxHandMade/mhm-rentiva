=== MHM Rentiva ===
Contributors: mhmdevelopment
Tags: car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.5.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MHM Rentiva is a powerful and flexible vehicle rental management plugin with offline payment support and optional WooCommerce integration.

== Description ==

MHM Rentiva is a comprehensive vehicle rental management solution designed for car rental agencies, bike rentals, and equipment rental businesses. It provides a dedicated and streamlined experience for managing your fleet and bookings with built-in offline payment support and optional WooCommerce integration for online payments.

**Key Features:**

*   **Vehicle Management:** Easily add, edit, and manage your vehicle fleet with detailed attributes (transmission, fuel type, seats, etc.).
*   **Booking System:** Robust booking engine with calendar view, availability checking, and automatic price calculation.
*   **Payment Integration:** Built-in offline payment system with receipt upload and admin approval. Optional WooCommerce integration for online payment gateways.
*   **Customer Management:** Manage customer information and booking history.
*   **Email Notifications:** Customizable email templates for booking confirmations, cancellations, and more.
*   **Shortcode Support:** Easy-to-use shortcodes to display vehicle lists, search forms, and booking wizards anywhere on your site.
*   **REST API:** Full REST API support for mobile app or external integrations.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Navigate to the 'Rentiva' menu to configure your settings and add vehicles.
4.  Use the shortcodes `[mhm_rentiva_vehicles]` or `[mhm_rentiva_search]` to display your fleet on the frontend.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =
No, MHM Rentiva works independently and includes a built-in offline payment system. WooCommerce integration is optional and only needed if you want to accept online payments through WooCommerce payment gateways.

= Can I accept credit card payments? =
Yes, the plugin supports "Offline Payment" natively. For online payments (credit cards, etc.), you can use our seamless WooCommerce integration to leverage any payment gateway supported by WooCommerce.

= Is it compatible with the latest WordPress version? =
Yes, we actively test and update the plugin to ensure compatibility with the latest WordPress releases.

== Screenshots ==

1.  **Dashboard:** Overview of your rental business.
2.  **Vehicle List:** Manage your fleet easily.
3.  **Booking Calendar:** Visual calendar for managing reservations.
4.  **Settings:** Comprehensive configuration options.

== Changelog ==

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
