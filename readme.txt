=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 7.0
Tested up to:      7.1
Requires PHP:      8.1
Requires Plugins:  woocommerce
Stable tag:        6.4.1
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
*   **16 Shortcodes:** (one of them, the customer dashboard, retired in 6.2.0 and now only points to My Account) Search, results, vehicle grids and lists, vehicle details, booking form, availability calendar, comparison, testimonials, ratings, contact form, and the customer account views.
*   **16 Gutenberg Blocks:** One for each frontend shortcode, built on a Render Parity architecture — a block, its Elementor widget and its shortcode all delegate to the same renderer, so they produce identical output.
*   **17 Elementor Widgets:** The same components as Elementor widgets, with Elementor's own controls and live preview. (Two of the seventeen — Vehicle Card and Vehicles List — render the same underlying component with different presets, so the seventeen widgets cover sixteen distinct components.)
*   **REST API:** Endpoints under `mhm-rentiva/v1` for availability checks, customer records and admin dashboard data.
*   **Translation Ready:** Ships with a full Turkish translation.

**Documentation and support**

*   [mhmrentiva.com](https://mhmrentiva.com/en/) — the plugin's own site: what it does, pricing and tutorials
*   [Documentation](https://maxhandmade.github.io/mhm-rentiva-docs/) — setup, shortcodes, blocks and Elementor widgets, in English and Turkish
*   [Support forum](https://wordpress.org/support/plugin/mhm-rentiva/)
*   [Source code and issue tracker on GitHub](https://github.com/MaxHandMade/mhm-rentiva)
*   [wpalemi.com](https://wpalemi.com/) — the author's site
*   [MaxHandMade on YouTube](https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA) — WordPress and WooCommerce videos from the author

**Requirements:** WooCommerce. It is declared in the plugin header and checked on activation, so the plugin will not activate without it. WooCommerce provides the cart, checkout and payment gateways used for frontend bookings.

= External services =

This plugin makes no requests of its own to any third-party service: no analytics, no geolocation lookups, no remotely-hosted fonts or scripts. Every asset it loads, including its webfonts, is served from your own site. Nothing about your site, your bookings or your customers leaves your server through this plugin, unless you run Akismet as described next.

One thing you can choose: if you run the Akismet plugin on your site with your own API key, the contact form asks it about each message that passed the plugin's own spam rules, through Akismet's own code and under your Akismet account. That sends the sender's name, e-mail address, IP address, browser user-agent and the message, with your site address and language, to Akismet (terms: [https://akismet.com/tos/](https://akismet.com/tos/), privacy: [https://automattic.com/privacy/](https://automattic.com/privacy/)). This plugin opens no connection of its own for it, holds no key and does not ship Akismet. Without that plugin and key nothing is sent.

For completeness, the plugin's admin screens do contain ordinary hyperlinks to pages outside your site, which open only if you click them and send nothing when you do:

*   Its documentation at [https://maxhandmade.github.io/mhm-rentiva-docs/](https://maxhandmade.github.io/mhm-rentiva-docs/) (source: [https://github.com/MaxHandMade/mhm-rentiva-docs](https://github.com/MaxHandMade/mhm-rentiva-docs))
*   Its issue tracker at [https://github.com/MaxHandMade/mhm-rentiva/issues](https://github.com/MaxHandMade/mhm-rentiva/issues), linked from the About screen
*   The author's site and support page at [https://wpalemi.com/](https://wpalemi.com/) — terms: [https://wpalemi.com/terms/](https://wpalemi.com/terms/) , privacy policy: [https://wpalemi.com/privacy/](https://wpalemi.com/privacy/)
*   This plugin's own support forum at [https://wordpress.org/support/plugin/mhm-rentiva/](https://wordpress.org/support/plugin/mhm-rentiva/) and, in the setup wizard's e-mail step, links to the wp-mail-smtp and fluent-smtp plugin pages on WordPress.org
*   The author's YouTube channel at [https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA](https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA), linked from the About screen

These are links, not integrations: the plugin performs no HTTP request to any of them. A check in our build guards against reintroducing the specific third-party services earlier versions used — geolocation lookups, CDN-hosted fonts and scripts, analytics and Gravatar — by failing if any of those hosts reappears in the PHP we ship.

= Privacy =

MHM Rentiva stores its booking and customer records (such as names, e-mail addresses and phone numbers) locally in your WordPress database and does not transmit them anywhere.

Four other things it keeps are worth naming; three of them record an IP address, which in most jurisdictions counts as personal data in its own right.

If you publish the plugin's **contact form**, each submission is saved as a private record holding the sender's name, e-mail address, telephone number, company, the message itself, any file they attached, and the **IP address** and **browser user-agent** it was sent from. The record also keeps the rest of what the form submitted: which vehicle the enquiry was about, a preferred date, a priority, a rating, and which variant of the form was used. These records have no retention setting and are never removed automatically; you read and delete them yourself under MHM Rentiva → Contact Messages. A message the plugin's spam rules catch is saved the same way, without its file, and is listed under the Spam filter on that screen. Files attached to a contact message are stored in a plugin-owned folder inside your uploads directory (`wp-content/uploads/mhm-rentiva-private/contact/`) under a random name with no file extension. They are **not encrypted**. On Apache the folder ships with an `.htaccess` that denies direct access; on nginx you must add a `location ^~ /wp-content/uploads/mhm-rentiva-private/ { deny all; }` rule yourself, otherwise the only protection is the unguessable name and the folder's empty index page. If you use a plugin that copies every upload to external storage (a CDN or cloud offload plugin), exclude the `mhm-rentiva-private` folder in its settings, or it may copy these files as well — or, if it moves files away from your server, contact attachments may fail to upload. The file is deleted when the message is permanently deleted, and when the plugin is deleted with its "Wipe all data on uninstall" setting turned on. On a multisite network that wipe covers the main site only: delete the other sites' `mhm-rentiva-private/contact/` folders by hand. A copy is also sent as an attachment of the notification e-mail, which this plugin does not control once sent. Attachments saved by earlier versions were public files in the normal uploads folder; updating moves those it can positively identify into the private folder and lists the rest in an admin notice.

If you publish the plugin's **rating form**, each review is stored as an ordinary WordPress comment, so WordPress itself records the reviewer's IP address and browser user-agent alongside it, exactly as it does for any comment — and for a review left by someone who is not logged in, the name and e-mail address they typed. Those guest reviews are reachable through Tools → Export/Erase Personal Data, which matches comments by e-mail address. From version 6.0.1, a review left by a logged-in customer is saved the same way WordPress saves any comment from a signed-in visitor: against their user ID, and with the display name, e-mail address and website address from their profile written onto the comment. Those reviews are matched by the same Export/Erase tools, on the account's e-mail address. Reviews that logged-in customers left in earlier versions carry no name and no e-mail address on the comment, and updating does not fill them in, so the Export/Erase tools — which look comments up by e-mail address — do not return those older ones; remove them from the Comments screen instead.

The **activity log** records, for each entry, the IP address and browser user-agent of the request that produced it, along with the WordPress user ID where there is one. Unlike contact messages, log entries are deleted automatically after a retention period you set in the plugin's settings (30 days by default).

The **e-mail log** records, for each message sent through the plugin's notification system, the recipient address, the subject, whether delivery succeeded, and the booking details the message was built from — which for a booking e-mail means the customer's name, contact details and rental dates. The assembled message body itself is not stored. This log has its own retention setting on the same terms (also 30 days by default).

Booking records themselves do not store an IP address: no code path in this version writes one, whether the booking comes through WooCommerce checkout or an administrator enters it by hand. Records created by older versions may still carry an IP address and user-agent; where they exist they have no expiry and go only when the booking is permanently deleted.

If you keep a privacy policy, these are the parts of the plugin it should describe.

The plugin registers no personal-data exporter or eraser. Contact-form submissions are not covered by Tools → Export/Erase Personal Data: search for them by e-mail address under MHM Rentiva → Contact Messages and delete them there.

= A paid version exists =

A separate paid Rentiva plugin adds a multi-vendor marketplace, VIP transfers with location-based routes, customer messaging, advanced reports and vendor payouts. It is a separate add-on plugin. Nothing described on this page is withheld or limited to promote it, and this plugin never advertises it to you in the admin.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mhm-rentiva` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Install and activate WooCommerce first. It is a required dependency: activation is refused without it.
4. Use the Settings menu to configure your vehicle features, equipment, and module preferences.

== Source code ==

Most of this plugin is plain, human-readable PHP with no build step.

Five admin screens (Dashboard, Customers, Contact Messages, About and Shortcode Pages) are built in React. Their compiled bundles ship in `build/admin/`. They are generated from un-minified React sources that are NOT part of this ZIP: those live in the public GitHub repository linked below, under `src-react/`, next to the build tooling. What does ship under `src-react/` is plain CSS: the five screens' stylesheets and the shared admin stylesheet (`src-react/shared/admin.css`) that the vehicle, booking and add-on list screens load directly. No obfuscated code is bundled.

The build tooling itself (`package.json`, `webpack.config.js`) is not included in this plugin's ZIP -- it lives only in the public GitHub repository, alongside the same `src-react/` sources:

[https://github.com/MaxHandMade/mhm-rentiva](https://github.com/MaxHandMade/mhm-rentiva)

To rebuild the admin bundles from source, clone or download that repository and run the following from the repository root:

`npm install`
`npm run build`

The build uses [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts) (webpack). Run `npm start` instead of `npm run build` for a watched development build.

= Bundled third-party libraries =

Three bundled JavaScript libraries and one webfont ship with the plugin -- two of the libraries on the front end, one in the admin dashboard only -- all permissively licensed and with public upstream source.

Two are pre-built (minified) under `assets/vendor/`:

*   **flatpickr** v4.6.13 — date picker, MIT License. Source: [https://github.com/flatpickr/flatpickr](https://github.com/flatpickr/flatpickr)
*   **Swiper** 14.1.0 — touch slider, MIT License. Source: [https://github.com/nolimits4web/swiper](https://github.com/nolimits4web/swiper)

One is compiled into the admin dashboard bundle (`build/admin/dashboard.js`) by the build described above, and is declared as a dependency in `package.json`:

*   **Chart.js** v4.5.1 — dashboard charts, MIT License. Source: [https://github.com/chartjs/Chart.js](https://github.com/chartjs/Chart.js)

One webfont is bundled under `assets/vendor/fonts/`, served from your own site rather than from a font CDN:

*   **Plus Jakarta Sans** — SIL Open Font License 1.1, with the license text shipped alongside it at `assets/vendor/fonts/LICENSE-Plus-Jakarta-Sans.txt`. Source: [https://github.com/tokotype/PlusJakartaSans](https://github.com/tokotype/PlusJakartaSans)

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
No. It makes no third-party requests of its own — see the "External services" section above, which also covers the Akismet plugin if you run it yourself.

= Is it mobile-ready? =
Yes, all frontend components and admin settings are fully responsive.

= Does it work on a WordPress multisite network? =
Yes, and it can be network-activated. One limit is worth knowing before you plan around it: the
parts that manage WordPress user accounts -- the Customers screens and creating a customer account
from a booking -- require a Super Admin. WordPress itself reserves user management for the network
level on multisite, so a site administrator will not see those screens even though they can use
everything else. Vehicles, bookings, add-ons, payments and all frontend features work normally for
a site administrator.

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

= My site is behind Cloudflare (or another reverse proxy / CDN) — will rate limiting still work correctly? =

By default, no — and this is worth fixing. The plugin's anonymous rate limits (contact form submissions,
price and availability lookups on the booking form, and others) are counted per visitor IP address, read
from the server's `REMOTE_ADDR`. Behind Cloudflare or any reverse proxy, `REMOTE_ADDR` is the proxy's own
edge IP, the same for every visitor — so instead of limiting each visitor separately, the plugin
effectively limits your whole site to one shared bucket (for example, the contact form's default of 5
submissions per 5 minutes becomes 5 submissions per 5 minutes for every visitor combined).

This default is intentional, not an oversight: trusting a header like `X-Forwarded-For` automatically
would let anyone bypass rate limiting outright, since that header can be set by any visitor unless your
proxy is configured to overwrite it. If you know your proxy overwrites it — which Cloudflare does — you
can opt the specific header back in:

`add_filter( 'mhmrentiva_trusted_proxy_ip_headers', function () {
    return array( 'HTTP_CF_CONNECTING_IP' ); // Cloudflare
} );`

Add this to your theme's functions.php or a small plugin of your own. List headers in priority order —
the plugin uses the first one present that holds a valid public IP address, falling back to `REMOTE_ADDR`
if none match. Only add a header your proxy is actually configured to set and protect; adding one that
ordinary visitors can also send re-opens the bypass this default closes.

== Screenshots ==

1. Search and results: the search strip, filters, and vehicle cards with the total for the chosen dates.
2. Vehicle page: photo gallery, highlights, rental conditions, the availability calendar and the booking card.
3. Booking form: dates, additional services, the rental agreement the customer accepts, and the price summary with the deposit.
4. My Account: the customer's upcoming, past and cancelled bookings.
5. Admin dashboard: bookings, booking value, upcoming operations, booking statuses and quick actions.
6. Bookings list: status tabs, payment and payment-method filters, deposit and remaining amounts.
7. Vehicle editor: gallery, daily price, specifications, deposit and features.
8. Settings: booking rules such as the default rental days, cancellation deadline and payment deadline.

== Changelog ==

WordPress.org shows at most 5,000 characters of this section, so it lists what a site owner needs for the release since the version in the directory. The complete history, in English and Turkish, ships with the plugin as changelog.json and changelog-tr.json.

= 6.4.1 =
* Fixed: a customer whose account WooCommerce creates, at checkout or in My Account, gets one welcome e-mail, WooCommerce's own new-account e-mail, instead of that and Rentiva's welcome. If that WooCommerce e-mail is switched off (WooCommerce > Settings > Emails), Rentiva's welcome is still sent.
* Fixed: the bookings calendar summary shows the pickup and return times, the total with your shop's currency, formatted as in the bookings list, and dates in your site's date and time format.
* Fixed: Upcoming Operations on the dashboard shows dates in your admin language instead of your browser's, and no longer a day early when your browser is west of UTC.
* Fixed: the blocked-days list on the vehicle editor no longer stops working for an admin whose language is Portuguese (AO90).
* Fixed: a fresh install no longer writes a "Table ... doesn't exist" database error to the log.

= 6.4.0 =
* Before you update: Rentiva requires WordPress 7.0 or newer (was 6.7); current WooCommerce requires 7.0 as well.
* After you update: clear your page cache (your host's cache, LiteSpeed Cache, WP Rocket and the like). A page cached by 6.3.x keeps the old booking form and calendar markup, which the new scripts do not drive, until it is cleared.
* Note for themes: a theme that overrides templates/shortcodes/*, templates/partials/* or templates/account/*.php keeps its old markup, which the new scripts and styles no longer drive. Remove or update those overrides.
* Rentiva Pro users: update Pro to 6.5.0 after this release; Pro 6.4.x keeps working until then.
* New: a redesigned search strip, results page and vehicle card. Results have a Category filter, removable filter chips, a comparison bar (up to 3 vehicles) and a filter drawer on phones. With search dates every card shows the total for the rental, required services included.
* New: My Account follows the new design. The dashboard shows the next rental; the booking page shows progress, payment and when free cancellation ends; My bookings is split into Upcoming, Past and Cancelled; a printable Booking summary (not an invoice).
* New: an optional rental agreement on the booking form (Settings > Booking > Rental agreement, off by default). The time and the version of the text the customer accepted are stored with the booking.
* Changed: taxes come only from WooCommerce > Settings > Tax; Rentiva's own tax settings are removed. The tax charged does not change.
* Fixed: the booking form quotes exactly what is charged. Seasonal pricing reaches the payment and the stored booking total.
* Fixed: WooCommerce checkout bookings stored their times off by the site's UTC offset (three hours in Istanbul), so a late-evening booking could overlap another. A one-time step on update corrects existing bookings.
* Fixed: a booking entered by hand and paid outside the site is no longer cancelled after the payment deadline. A failed remaining-balance payment no longer cancels the booking.
* Fixed: a booking refunded in full before the rental no longer holds the vehicle. Payment Management refunds are no longer refused a day after booking.
* Changed: the classic cart replaces the Cart block only when the cart holds a rental. The availability calendar shows 2 months by default and partly free days; a calendar whose vehicle no longer exists shows "Vehicle not found." instead of another vehicle.
* Changed: the customer's upload is called "Upload bank receipt" and is accepted only while a booking awaits payment or confirmation and no money has been received. Payment history shows the amount actually paid.
* Fixed: with page caching for visitors, one visitor's results are no longer shown to another. Personal data export and erasure include bookings in the trash.
* Security: the REST nonce is printed only on the pages that need it.

== Upgrade Notice ==

= 6.4.1 =
Fixes the bookings calendar summary (times, prices, dates) and dashboard dates, and sends one welcome e-mail when WooCommerce creates the customer's account.

= 6.4.0 =
Requires WordPress 7.0. New search, booking form, calendar and vehicle card markup: clear your page cache after updating. Taxes now come only from WooCommerce > Settings > Tax, and seasonal prices are charged as shown. Rentiva Pro: update Pro to 6.5.0 after this.

= 6.3.1 =
Elementor-built pages are found: "Book now" buttons open your booking page again. On Elementor sites the vehicle pages may move under your vehicle-details page (old addresses redirect).

= 6.3.0 =
Unticked e-mail templates and the "Send Booking Notifications" switch now really stop sending, and cancelled bookings send the customer one e-mail. Check your e-mail settings and Settings > Payment after updating. Rentiva Pro users: update Pro to 6.4.0 after this.

= 6.2.1 =
Privacy fix: Elementor pages with Rentiva widgets could expose the saving user's account data to site search. A background job cleans affected pages. Purge page caches and search indexes after updating.

= 6.2.0 =
Contact attachments move to a private folder and the customer dashboard is retired. Ten hooks were removed: custom code using them stops working. Rentiva Pro 6.1.0 or older breaks on the vendor panel: update Rentiva Pro to 6.3.0.

= 6.0.1 =
Cleanup and bug-fix release. No action required; an automatic one-time migration removes some internal database indexes. If you build pages with this plugin's blocks, this release repairs the favourite and compare buttons on them.

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
