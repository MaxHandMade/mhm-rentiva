=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.0
Requires PHP:      8.1
Stable tag:        5.1.0
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Plugin URI:        https://wpalemi.com/rentiva/
Author URI:        https://wpalemi.com/

Vehicle rental management for WordPress: fleet, availability, bookings and customers, with WooCommerce handling frontend payments.

== Description ==

MHM Rentiva is a vehicle rental management plugin for car and motorcycle rental businesses. You manage your fleet, availability and bookings from the WordPress admin; frontend payments are handled by WooCommerce, and administrators can also create and manage bookings manually.

Everything described below works in full. There are no vehicle, booking or listing caps, no feature timers, and no locked screens.

**Features:**

*   **Vehicle Management:** Add, edit and manage your fleet with detailed attributes (transmission, fuel type, seats, and your own custom features and equipment).
*   **Booking System:** Booking engine with calendar view, availability checking and automatic price calculation.
*   **Payment via WooCommerce:** Frontend payments go through WooCommerce, so you keep whatever payment gateways you already use. Native offline payment is supported for admin-created manual bookings.
*   **Customer Management:** Customer records, booking history, and CSV export of your customer list.
*   **Email Notifications:** Editable email templates for booking confirmations, cancellations, refunds and reminders.
*   **Customer Account Pages:** Bookings, favourites and payment history in the customer's WooCommerce account area.
*   **16 Shortcodes:** Search, results, vehicle grids and lists, vehicle details, booking form, availability calendar, comparison, testimonials, ratings, contact form, and the customer account views.
*   **16 Gutenberg Blocks:** One for each frontend shortcode, built on a Render Parity architecture — a block, its Elementor widget and its shortcode all delegate to the same renderer, so they produce identical output.
*   **17 Elementor Widgets:** The same components as Elementor widgets, with Elementor's own controls and live preview.
*   **REST API:** Endpoints under `mhm-rentiva/v1` for availability checks, customer records and admin dashboard data, with API key management.
*   **Translation Ready:** Ships with a full Turkish translation.

**Requirements:** WooCommerce is required for frontend booking payments.

= External services =

This plugin does not send your data anywhere. It makes no requests to any third-party service: no analytics, no geolocation lookups, no remotely-hosted fonts or scripts. Every asset it loads, including its webfont, is served from your own site.

= A paid version exists =

A separate paid Rentiva plugin adds a multi-vendor marketplace, VIP transfers with location-based routes, customer messaging, advanced reports and vendor payouts. It is a separate add-on plugin. Nothing described on this page is withheld or limited to promote it, and this plugin never advertises it to you in the admin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Install and activate WooCommerce if you want customers to book and pay from the frontend.
4. Use the Settings menu to configure your vehicle features, equipment, and module preferences.

== Source code ==

Most of this plugin is plain, human-readable PHP with no build step.

Four admin screens (Dashboard, Customers, About and Shortcode Pages) are built in React. Their compiled bundles ship in `build/admin/` and are generated from the un-minified React sources that are also included with this plugin, under `src-react/`. No obfuscated code is bundled.

The complete development sources, including the build configuration (`package.json`, `webpack.config.js`), are available in the public GitHub repository:

https://github.com/MaxHandMade/mhm-rentiva

To rebuild the admin bundles from source, run the following from the plugin directory (or a checkout of the repository above):

`npm install`
`npm run build`

The build uses [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack). Run `npm start` instead of `npm run build` for a watched development build.

= Bundled third-party libraries =

Two front-end libraries ship pre-built (minified) under `assets/vendor/`, both permissively licensed and with public upstream source:

*   **flatpickr** v4.6.13 — date picker, MIT License. Source: https://github.com/flatpickr/flatpickr
*   **Swiper** 11.2.10 — touch slider, MIT License. Source: https://github.com/nolimits4web/swiper

Neither library is modified from its upstream release; their full source is public on the repositories linked above.

== Frequently Asked Questions ==

= Does it work with WooCommerce? =
Yes. WooCommerce handles frontend payment processing and checkout, so your existing payment gateways work as they are. Admin-created manual bookings can also be handled offline without WooCommerce.

= Can I add custom features to vehicles? =
Absolutely. You can add, rename, or remove custom features and equipment via the Vehicle Settings page.

= Are there any limits on how many vehicles or bookings I can have? =
No. There are no caps of any kind.

= Does the plugin contact any external server? =
No. It makes no third-party requests at all — see the "External services" section above.

= Is it mobile-ready? =
Yes, all frontend components and admin settings are fully responsive.

= Which page builders are supported? =
Gutenberg and Elementor, plus plain shortcodes for any other theme or builder. All three render identical output.

== Screenshots ==

1.  **Dashboard:** Overview of your rental business.
2.  **Vehicle List:** Manage your fleet easily.
3.  **Booking Calendar:** Visual calendar for managing reservations.
4.  **Settings:** Comprehensive configuration options.

== Changelog ==

= 5.1.0 =
* Security: hardened contact-form file-path handling, capability checks for customer-account creation, REST route permissions, output escaping and settings sanitization across the plugin.
* Changed: testimonial and account avatars now render locally from initials with no external Gravatar request; the plugin makes no third-party calls.
* Removed: the demo-data seeder and its bundled sample images.
* Added: a "Source code" section documenting the React build and the public repository.
* Maintenance: WordPress.org guideline compliance. The free plugin's behaviour is unchanged.

= 5.0.2 =
* Changed: the admin menu now sits lower in the WordPress menu, so it no longer competes with core items.
* Fixed: about 105 error and status messages are now translatable and fully translated to Turkish.
* Changed: internal AJAX action names on the Vehicle Settings screen are now plugin-prefixed.
* Removed: a developer-only diagnostics test runner that used to ship with the plugin.
* Maintenance: various WordPress.org compliance housekeeping. The free plugin's behaviour is unchanged.

= 5.0.1 =
* Maintenance: internal fixes to shared admin assets and an updated Turkish translation. The free plugin's behaviour is unchanged.

= 5.0.0 =
* First public release of MHM Rentiva on WordPress.org.
* Rental core: fleet management, availability, bookings, customers, WooCommerce payments, email notifications, and the customer account pages.
* 16 shortcodes, 16 Gutenberg blocks and 17 Elementor widgets, all sharing one renderer (Render Parity).
* No third-party requests: the webfont is bundled with the plugin and there are no geolocation, analytics or CDN calls.
* Full Turkish translation.

== Upgrade Notice ==

= 5.1.0 =
Security hardening and WordPress.org compliance. No action required; your settings and data are unaffected.

= 5.0.2 =
Compliance housekeeping and translation improvements. No action required; your settings and data are unaffected.

= 5.0.1 =
Maintenance and translation update. No action required.

= 5.0.0 =
First WordPress.org release.
