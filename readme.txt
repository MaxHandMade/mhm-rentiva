=== MHM Rentiva ===
Contributors: mhmdevelopment
Tags: car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.4.3
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

= 4.4.2 =
This update ensures WordPress standards compliance and includes verified stability improvements.

= 4.4.1 =
This update includes critical security fixes and code improvements. It is highly recommended to upgrade immediately.
