=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.0
Requires PHP:      8.1
Requires Plugins:  woocommerce
Stable tag:        6.0.0
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
*   **17 Elementor Widgets:** The same components as Elementor widgets, with Elementor's own controls and live preview. (Two of the seventeen — Vehicle Card and Vehicles List — render the same underlying component with different presets, so the seventeen widgets cover sixteen distinct components.)
*   **REST API:** Endpoints under `mhm-rentiva/v1` for availability checks, customer records and admin dashboard data.
*   **Translation Ready:** Ships with a full Turkish translation.

**Requirements:** WooCommerce. It is declared in the plugin header and checked on activation, so the plugin will not activate without it. WooCommerce provides the cart, checkout and payment gateways used for frontend bookings.

= External services =

This plugin does not send your data anywhere. It makes no requests to any third-party service: no analytics, no geolocation lookups, no remotely-hosted fonts or scripts. Every asset it loads, including its webfont, is served from your own site. Nothing about your site, your bookings or your customers leaves your server.

For completeness, the plugin's admin screens do contain ordinary hyperlinks to pages outside your site, which open only if you click them and send nothing when you do:

*   Its documentation at https://maxhandmade.github.io/mhm-rentiva-docs/ (source: https://github.com/MaxHandMade/mhm-rentiva-docs)
*   Its issue tracker at https://github.com/MaxHandMade/mhm-rentiva/issues, linked from the About screen
*   The author's site and support page at https://wpalemi.com/ — terms: https://wpalemi.com/terms/ , privacy policy: https://wpalemi.com/privacy/
*   This plugin's own support forum at https://wordpress.org/support/plugin/mhm-rentiva/ and, in the setup wizard's e-mail step, links to the wp-mail-smtp and fluent-smtp plugin pages on WordPress.org
*   The author's YouTube channel

These are links, not integrations: the plugin performs no HTTP request to any of them. A check in our build guards against reintroducing the specific third-party services earlier versions used — geolocation lookups, CDN-hosted fonts and scripts, analytics and Gravatar — by failing if any of those hosts reappears in the PHP we ship.

= Privacy =

MHM Rentiva stores its booking and customer records (such as names, e-mail addresses and phone numbers) locally in your WordPress database and does not transmit them anywhere.

Four other things it keeps are worth naming, because in most jurisdictions an IP address counts as personal data in its own right.

If you publish the plugin's **contact form**, each submission is saved as a private record holding the sender's name, e-mail address, telephone number, company, the message itself, a link to any file they attached, and the **IP address** and **browser user-agent** it was sent from. The record also keeps the rest of what the form submitted: which vehicle the enquiry was about, a preferred date, a priority, a rating, and which variant of the form was used. These records have no retention setting and are never removed automatically. The attached file itself is placed in your site's ordinary uploads folder, where it is reachable by anyone who has the URL, and it stays there even if you delete the message.

If you publish the plugin's **rating form**, each review is stored as an ordinary WordPress comment, so WordPress itself records the reviewer's IP address and browser user-agent alongside it, exactly as it does for any comment — and for a review left by someone who is not logged in, the name and e-mail address they typed. Those guest reviews are reachable through Tools → Export/Erase Personal Data, which matches comments by e-mail address. A review left by a logged-in customer is saved against their user ID without an e-mail address on the comment, so those tools will not find it; delete it from the Comments screen instead.

The **activity log** records, for each entry, the IP address and browser user-agent of the request that produced it, along with the WordPress user ID where there is one. Unlike contact messages, log entries are deleted automatically after a retention period you set in the plugin's settings (30 days by default).

The **e-mail log** records, for each message sent through the plugin's notification system, the recipient address, the subject, whether delivery succeeded, and the booking details the message was built from — which for a booking e-mail means the customer's name, contact details and rental dates. The assembled message body itself is not stored. This log has its own retention setting on the same terms (also 30 days by default).

Booking records themselves do not store an IP address on the current booking flow: bookings placed through WooCommerce checkout, and those an administrator enters by hand, record none. A direct booking endpoint that no part of the current interface posts to does write one, so records created by older versions or by custom integrations may carry an IP address and user-agent; where they exist they have no expiry and go only when the booking is permanently deleted.

If you keep a privacy policy, these are the parts of the plugin it should describe.

The plugin registers no personal-data exporter or eraser of its own; advanced GDPR export and erasure tooling is provided by the separate paid Rentiva add-on.

= A paid version exists =

A separate paid Rentiva plugin adds a multi-vendor marketplace, VIP transfers with location-based routes, customer messaging, advanced reports and vendor payouts. It is a separate add-on plugin. Nothing described on this page is withheld or limited to promote it, and this plugin never advertises it to you in the admin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Install and activate WooCommerce first. It is a required dependency: activation is refused without it.
4. Use the Settings menu to configure your vehicle features, equipment, and module preferences.

== Source code ==

Most of this plugin is plain, human-readable PHP with no build step.

Four admin screens (Dashboard, Customers, About and Shortcode Pages) are built in React. Their compiled bundles ship in `build/admin/` and are generated from the un-minified React sources that are also included with this plugin, under `src-react/`. No obfuscated code is bundled.

The build tooling itself (`package.json`, `webpack.config.js`) is not included in this plugin's ZIP -- it lives only in the public GitHub repository, alongside the same `src-react/` sources:

https://github.com/MaxHandMade/mhm-rentiva

To rebuild the admin bundles from source, clone or download that repository and run the following from the repository root:

`npm install`
`npm run build`

The build uses [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack). Run `npm start` instead of `npm run build` for a watched development build.

= Bundled third-party libraries =

Three front-end libraries and one webfont ship with the plugin, all permissively licensed and with public upstream source.

Two are pre-built (minified) under `assets/vendor/`:

*   **flatpickr** v4.6.13 — date picker, MIT License. Source: https://github.com/flatpickr/flatpickr
*   **Swiper** 11.2.10 — touch slider, MIT License. Source: https://github.com/nolimits4web/swiper

One is compiled into the admin dashboard bundle (`build/admin/dashboard.js`) by the build described above, and is declared as a dependency in `package.json`:

*   **Chart.js** v4.5.1 — dashboard charts, MIT License. Source: https://github.com/chartjs/Chart.js

One webfont is bundled under `assets/vendor/fonts/`, served from your own site rather than from a font CDN:

*   **Plus Jakarta Sans** — SIL Open Font License 1.1, with the license text shipped alongside it at `assets/vendor/fonts/LICENSE-Plus-Jakarta-Sans.txt`. Source: https://github.com/tokotype/PlusJakartaSans

None of these libraries is modified from its upstream release; their full source is public on the repositories linked above.

== Frequently Asked Questions ==

= Does it work with WooCommerce? =
It requires it. WooCommerce handles frontend checkout and payment, so your existing payment gateways
work as they are, and the plugin will not activate without it. Bookings an administrator creates by
hand can still be settled offline, without going through checkout.

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

= Why does wp-login.php send me to the My Account page, and can I turn that off? =
By default the plugin routes wp-login.php to your WooCommerce My Account page, so customers meet one
login form styled like the rest of your site rather than the plain WordPress one. It deliberately steps
aside where the real form is needed: submitting the login form itself, logging out, password reset and
lost-password links, e-mail confirmation links, anyone who can manage plugins, and any site where
WooCommerce is inactive.

If you would rather keep the standard WordPress login page, add this to your theme's functions.php or a
small plugin of your own:

`add_filter( 'mhmrentiva_takeover_login', '__return_false' );`

The default is `true`. The filter is read while the plugin sets up its hooks, on the `init` action at
priority 2, so add it somewhere that runs before then — a theme's functions.php or an ordinary plugin
file is early enough.

== Changelog ==

= 6.0.0 =
**This is a major release. If you have custom code that hooks into this plugin, read the next paragraph before you update.**

* Breaking: 113 of this plugin's hooks were renamed. Anything attached to one of them — a snippet in your theme's functions.php, a code-snippets plugin, a custom integration, bespoke work done for you — will simply stop running after the update. There is no error message and nothing in the log; the customisation just quietly stops happening. (Two further hooks, `mhm_rentiva_enable_governance_log` and `mhm_rentiva_governance_violation`, were removed rather than renamed, along with the feature behind them.)

* How to convert your own code. **Apply these in the order given — the order matters.**
  1. First, in the older slash-style names, replace every `/` with `_`. So `mhm_rentiva/testimonials/limit` becomes `mhm_rentiva_testimonials_limit`.
  2. Then replace the prefix `mhm_rentiva_` with `mhmrentiva_`. So that name finishes as `mhmrentiva_testimonials_limit`.
  3. Two hooks used a bare `mhm_` prefix and follow the same idea: `mhm_message_created` and `mhm_message_status_changed` become `mhmrentiva_message_created` and `mhmrentiva_message_status_changed`.
  Doing step 2 before step 1 produces names that do not exist — `mhm_rentiva_` cannot match a name that begins `mhm_rentiva/`, and you would end up with a plausible-looking hook that never fires.

* Breaking, and easy to miss because it is not one of our hook names: **the plugin's post types and taxonomies were renamed too**, which changes the WordPress core hooks built from them. `save_post_vehicle` is now `save_post_mhmrentiva_vehicle`; the same applies to `add_meta_boxes_*` and to the `manage_*_posts_columns` and `manage_*_posts_custom_column` pairs. If you have code that reacts to a vehicle or booking being saved, this is the line most likely to be affected, and neither rule above will find it. The renames are: `vehicle` → `mhmrentiva_vehicle`, `vehicle_booking` → `mhmrentiva_booking`, `vehicle_addon` → `mhmrentiva_addon`, `mhm_app_log` → `mhmrentiva_app_log`, `mhm_email_log` → `mhmrentiva_email_log`, `mhm_contact_message` → `mhmrentiva_contact`; and for taxonomies `vehicle_category` → `mhmrentiva_vehicle_category`, `addon_context` → `mhmrentiva_addon_context`, `addon_category` → `mhmrentiva_addon_category`.

* The same rename affects any query you have written by hand. A `WP_Query` with `'post_type' => 'vehicle'`, a `pre_get_posts` handler that checks for it, a `get_posts` call, a shortcode of your own — all of these now match nothing, silently, because they are asking for a post type that no longer exists under that name. The old names were generic enough (`vehicle`, `vehicle_booking`, `vehicle_category`) that this is worth searching for even if you do not think you wrote any.

* What to search your custom code for, before you update: `mhm_rentiva` (catches both the underscore and slash families), `mhm_message_`, `save_post_vehicle`, and the quoted literals `'vehicle'`, `'vehicle_booking'`, `'vehicle_addon'` and `'vehicle_category'`.
* Breaking: the three Elementor widgets named "Featured Vehicles", "Vehicles Grid" and "Vehicles List" were renamed internally. Pages you have already built with them are migrated automatically on update and need no attention; this is listed only because a widget name, like a hook name, is something custom code can refer to.
* Changed: every identifier the plugin registers — settings, custom post types, taxonomies, database tables, scheduled jobs, and the keys it stores against your posts and users — now uses a single consistent prefix. Your existing data is moved to the new names by a migration that runs once, automatically, the first time you load the admin after updating. Nothing is deleted and nothing needs to be re-entered. If you also run the paid add-on, update both at the same time: this version refuses to migrate underneath an older add-on rather than leave your data half-moved.
* Fixed: choosing "Semi-Automatic" or "CVT" for a vehicle's transmission, or "LPG", "CNG" or "Hydrogen" for its fuel type, silently saved a different value than the one selected. The list the editor offers and the list it accepts had drifted apart; they are now the same list.
* Fixed: the plugin's custom fields are now registered on every request rather than only inside the admin, so the sanitising rules that protect them apply everywhere instead of only on admin screens.
* Fixed: uninstalling the free plugin no longer removes the paid add-on's tables, which hold commission ledgers, payout records and the audit trail that signs them.
* Security: the remaining places where the plugin built SQL by hand have been reshaped to use WordPress's prepared-statement API, and the comments that explain each remaining direct query have been checked line by line against the query they describe.

= 5.2.4 =
* Changed: the plugin's top-level admin menu now sits between Tools and Settings instead of directly above Appearance. Nothing else about the menu changes — the same screens are in the same order underneath it — but it no longer pushes past WordPress's own items.
* Docs: the readme's "External services" section now lists every page outside your site that the admin screens link to (the documentation site, the issue tracker, wpalemi.com with its terms and privacy pages, this plugin's WordPress.org support forum, the wp-mail-smtp and fluent-smtp plugin pages, and a YouTube channel). The plugin still makes no request to any of them — these are links a user may click. A build check guards against reintroducing the third-party services earlier versions used (geolocation, CDN fonts and scripts, analytics, Gravatar).

= 5.2.3 =
* Fixed: the plugin's background-job table was created with "CREATE TABLE IF NOT EXISTS". WordPress reads the words after CREATE TABLE as the table's name, so its schema updater had been tracking a table called "IF" — a table it cannot describe, and therefore skipped entirely. The real table was created correctly by the database itself and no data was lost, but any column added to it in a later release would silently never have been applied. Measured on a live database: before the fix a newly added column was ignored; after it, the same column was created. Existing tables are updated in place and no data is touched.
* Docs: the Privacy section has been corrected. It now discloses that every contact-form submission is stored with the sender's name, e-mail, telephone, message and the IP address and browser user-agent it came from, and that those records have no retention setting at all. It also notes that reviews left through the rating form are stored as WordPress comments, so WordPress records the reviewer's IP with them, and describes what the activity log and the e-mail log each hold and how long they keep it, and states that bookings placed through WooCommerce checkout record no IP address. What the plugin stores has not changed; the description of it was incomplete.

= 5.2.2 =
* Fixed: the plugin now tells WooCommerce which of its storage features it works with. Without that declaration WooCommerce could not confirm compatibility, and site owners turning on High-Performance Order Storage — the default for new stores since WooCommerce 8.2 — were warned about this plugin by name.
* Fixed: the payment type chosen at checkout was written to order storage in a way that only works on the older layout. It is now written through WooCommerce's own order object, so it lands in the right place whichever storage a store uses. On existing stores the value stays exactly where it has always been; nothing is moved and nothing needs migrating.
* Declared: this plugin is NOT compatible with the block-based cart and checkout, and now says so instead of leaving it unanswered. On a block checkout the payment-type selector, the custom tax row and the return-to-cart link do not appear, and — the part that matters most — the availability check that prevents two customers booking the same vehicle for the same dates does not run. Use the classic checkout with this plugin.

= 5.2.1 =
* Fixed: the dashboard chart was labelled "Revenue (Last 7 Days)" but sums the value of bookings CREATED in those days, not rental income earned in them — which is why it could read as empty while the table beside it listed upcoming rentals. It is now "New Bookings Value (Last 7 Days)", and the daily series is "Daily Bookings Value". The figures themselves are unchanged.
* Fixed: five admin strings were translated but shipped in English, because the React translation catalogues could not be regenerated and the ones being shipped were four months old. Affects "Pending Payments", "Upcoming Operations", their empty-state messages, and the notice shown if an admin screen fails to load.
* Removed: eleven translation catalogues belonging to screens and blocks that are no longer part of this plugin. Nothing loaded them.
* Docs: the readme now lists Chart.js among the bundled third-party libraries, with its version, licence and upstream source.
* Internal: the translation-catalogue build is deterministic again, and a continuous-integration check now fails if the shipped catalogues fall out of step with the translation source instead of silently going stale.

= 5.2.0 =
* Security: the booking, deposit, vehicle-gallery and blocked-date screens now check permissions against the specific booking or vehicle a request names, instead of a general "can edit content" capability. On multi-author sites this closes paths where one contributor could act on another's records.
* Security: saving a booking from the editor now always requires a valid security token. A form field being present is no longer accepted in its place.
* Security: the database backup screen only exports, restores or deletes backup tables this plugin created. Other tables in your database are out of reach of those buttons.
* Security: markup generated by the plugin is now filtered through an explicit allowlist as it is printed, rather than trusted at the point of output.
* Removed a booking-status endpoint and its script that had no interface and performed no action.
* Fixed the translation catalogue so it compiles cleanly; strings using placeholders such as %days% were mis-flagged as printf formats.
* New: redesigned Dashboard and Vehicle Settings screens. Vehicle Settings opens in the new layout by default; append ?ui=legacy to the page URL for the previous one.
* Removed the Settings > Security tab. Its seventeen controls - "Brute Force Protection", "SQL Injection Protection", "XSS Protection", "CSRF Protection", "Enable Rate Limiting" and the IP lists - were connected to nothing, so switching them on changed nothing. A control that reports a protection as active while nothing enforces it is worse than no control, because it gets relied on. Saved values are cleaned up on update.
* Removed the "Secure API Access Tokens" section from Integration settings. It issued keys labelled READ, WRITE and ADMIN, but nothing in the plugin ever validated them, so a key created there opened nothing. Stored keys are deleted on update. The REST API itself is unchanged.
* Removed the Integration settings that were likewise not wired to anything: token duration, token refresh, API caching, debug output and "Allow Global CORS". Rate limiting, which does work, stays.
* Removed the "Scheduled Notifications" background job. It ran hourly against a queue nothing ever added to, while the Cron Monitor reported it healthy. Booking confirmations, reminders and refund notices are sent by the e-mail system and are unaffected.
* Fixed: "delete all data on uninstall" stopped partway through, leaving tables, scheduled jobs and terms behind even with the setting enabled.
* Fixed: the database backups screen could go blank - any .sql file in the backup folder without a matching database record caused a fatal error while the list was built.
* Backups are now written under the uploads folder instead of directly into wp-content. Backups taken by earlier versions stay listed, restorable and deletable.
* Fixed: vehicle quick edit accepted values the full editor rejects - a negative daily price, which multiplied into rental totals, and a seat count of zero or above the configured maximum.
* Fixed: the vehicle search request accepted any page size, so one request could ask the site to render the entire fleet. Search and testimonials now enforce the limits their own settings advertise.
* Internal: temporary cache entries, the last-login record, background job names and the JavaScript objects our admin screens read now carry the plugin's prefix so they cannot collide with another plugin's. Caches expire and are rebuilt; the last-login value is written under the new name from this version on, and the old entry is left alone because that name is shared with other plugins.
* Internal: removed about 2,000 lines of unreferenced code, including a file registering database-maintenance commands and one that would have exposed protected vehicle and booking fields over the REST API had it ever been enabled.
* Removed the "Add Vehicle" control from the vehicle comparison: the button had no handler, its endpoints were never registered, and the Elementor switch for it had no effect. Selecting a vehicle and pressing it did nothing.
* Fixed: "Auto Cleanup Logs" and "Log Retention (Days)" were ignored by the daily purge, which permanently deleted entries older than thirty days regardless of either setting.
* Fixed: saving the Vehicle settings tab silently reset two Frontend-tab fields it does not display.
* Fixed: the dashboard's recent-bookings panel could stay up to twelve hours stale after a booking changed.
* Fixed: the customers screen re-ran every query on each load - its cache type was never registered, so nothing was stored or read.
* Internal: every registered script and stylesheet now carries the full plugin prefix, so another plugin using the same short name cannot displace it.
* Internal: settings posted from an unrecognised tab are no longer written to the database unchecked.

= 5.1.1 =
* Internal: the REST and deposit-management AJAX actions now use the full mhmrentiva_ prefix, and a duplicate handler registration was removed so each action is handled exactly once. (The API-key management this refers to was removed entirely in 5.2.0.)
* Internal: removed a leftover reference to a settings class that is not part of the free plugin, unreachable deposit-calculation code, and developer debug logging from the block-editor and search scripts.
* Internal: the customer privacy controls now render only when their handlers are available (they ship with the paid add-on), so the free plugin shows no non-functional buttons; add-on-only scripts were also removed from the free plugin.
* No feature or behaviour change; your settings and data are unaffected.

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

= 6.0.0 =
Major release. Data migrates automatically, but 113 hooks and every post type name were renamed: custom code that hooks or queries them stops working silently. See the changelog before updating.

= 5.1.0 =
Security hardening and WordPress.org compliance. No action required; your settings and data are unaffected.

= 5.0.2 =
Compliance housekeeping and translation improvements. No action required; your settings and data are unaffected.

= 5.0.1 =
Maintenance and translation update. No action required.

= 5.0.0 =
First WordPress.org release.
