=== MHM Rentiva ===
Contributors: mhmdevelopment
Tags: car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

MHM Rentiva is a powerful and flexible vehicle rental management plugin independent of WooCommerce.

== Description ==

MHM Rentiva is a comprehensive vehicle rental management solution designed for car rental agencies. It operates independently of WooCommerce, providing a dedicated and streamlined experience for managing your fleet and bookings.

**Key Features:**

*   **Vehicle Management:** Easily add, edit, and manage your vehicle fleet with detailed attributes (transmission, fuel type, seats, etc.).
*   **Booking System:** Robust booking engine with calendar view, availability checking, and automatic price calculation.
*   **Payment Integration:** Built-in support for Stripe, PayPal, and PayTR payment gateways.
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
No, MHM Rentiva is a standalone plugin and does not require WooCommerce to function.

= Can I accept credit card payments? =
Yes, the plugin supports Stripe, PayPal, and PayTR for accepting online payments.

= Is it compatible with the latest WordPress version? =
Yes, we actively test and update the plugin to ensure compatibility with the latest WordPress releases.

== Screenshots ==

1.  **Dashboard:** Overview of your rental business.
2.  **Vehicle List:** Manage your fleet easily.
3.  **Booking Calendar:** Visual calendar for managing reservations.
4.  **Settings:** Comprehensive configuration options.

== Changelog ==

= 4.4.2 =
*   Compliance: Added WordPress.org standard `readme.txt`.
*   Compliance: Added License headers to main plugin file.
*   Verified: Passed automated syntax and class existence checks.

= 4.4.1 =
*   Security Fix: Removed hardcoded secure token and implemented dynamic key generation.
*   Refactor: Separated settings page view logic for better performance and maintainability.
*   Fix: Resolved automatic booking cancellation cron job issue.
*   Improvement: Centralized sanitization helper methods.
*   Cleanup: Removed unnecessary manual file inclusions.

= 4.4.0 =
*   Major update: Improved booking algorithm.
*   Added PayTR integration.

== Upgrade Notice ==

= 4.4.2 =
This update ensures WordPress standards compliance and includes verified stability improvements.

= 4.4.1 =
This update includes critical security fixes and code improvements. It is highly recommended to upgrade immediately.
