=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.1
Requires PHP:      8.1
Requires Plugins:  woocommerce
Stable tag:        6.1.3
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

Four other things it keeps are worth naming; three of them record an IP address, which in most jurisdictions counts as personal data in its own right.

If you publish the plugin's **contact form**, each submission is saved as a private record holding the sender's name, e-mail address, telephone number, company, the message itself, a link to any file they attached, and the **IP address** and **browser user-agent** it was sent from. The record also keeps the rest of what the form submitted: which vehicle the enquiry was about, a preferred date, a priority, a rating, and which variant of the form was used. These records have no retention setting and are never removed automatically; you read and delete them yourself under MHM Rentiva → Contact Messages. The attached file itself is placed in your site's ordinary uploads folder, where it is reachable by anyone who has the URL, and it stays there even if you delete the message.

If you publish the plugin's **rating form**, each review is stored as an ordinary WordPress comment, so WordPress itself records the reviewer's IP address and browser user-agent alongside it, exactly as it does for any comment — and for a review left by someone who is not logged in, the name and e-mail address they typed. Those guest reviews are reachable through Tools → Export/Erase Personal Data, which matches comments by e-mail address. From version 6.0.1, a review left by a logged-in customer is saved the same way WordPress saves any comment from a signed-in visitor: against their user ID, and with the display name, e-mail address and website address from their profile written onto the comment. Those reviews are matched by the same Export/Erase tools, on the account's e-mail address. Reviews that logged-in customers left in earlier versions carry no name and no e-mail address on the comment, and updating does not fill them in, so the Export/Erase tools — which look comments up by e-mail address — do not return those older ones; remove them from the Comments screen instead.

The **activity log** records, for each entry, the IP address and browser user-agent of the request that produced it, along with the WordPress user ID where there is one. Unlike contact messages, log entries are deleted automatically after a retention period you set in the plugin's settings (30 days by default).

The **e-mail log** records, for each message sent through the plugin's notification system, the recipient address, the subject, whether delivery succeeded, and the booking details the message was built from — which for a booking e-mail means the customer's name, contact details and rental dates. The assembled message body itself is not stored. This log has its own retention setting on the same terms (also 30 days by default).

Booking records themselves do not store an IP address: no code path in this version writes one, whether the booking comes through WooCommerce checkout or an administrator enters it by hand. Records created by older versions may still carry an IP address and user-agent; where they exist they have no expiry and go only when the booking is permanently deleted.

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

Three bundled JavaScript libraries and one webfont ship with the plugin -- two of the libraries on the front end, one in the admin dashboard only -- all permissively licensed and with public upstream source.

Two are pre-built (minified) under `assets/vendor/`:

*   **flatpickr** v4.6.13 — date picker, MIT License. Source: https://github.com/flatpickr/flatpickr
*   **Swiper** 14.1.0 — touch slider, MIT License. Source: https://github.com/nolimits4web/swiper

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

== Changelog ==

WordPress.org renders at most 5,000 characters of this section, so only the releases published since the version currently in the directory are repeated here. The complete history, in English and Turkish, ships with the plugin as changelog.json and changelog-tr.json, 6.0.0's breaking-change notice among them.

= 6.1.3 =
* Fixed: on a site whose vehicle settings were saved before 6.1.2, adding gallery images and pressing Update still wiped the gallery. 6.1.2 stopped the two non-detail keys, image and gallery_images, from being written into the selected-details option, but did nothing for the sites that already had them stored. A stored key that matched none of the known field sources was handed a label made up from the key name, which carried it past every later check, and the detail grid then rendered a second field named mhmrentiva_gallery_images -- the same name as the gallery meta box's hidden input. PHP keeps the last field of a repeated name, so an empty box overwrote the gallery. Such a key is now dropped rather than labelled, which makes an already-affected site safe without touching its database. One deliberate consequence: a custom detail whose name you clear under Edit Names now disappears from the grid instead of showing under a key-derived label. Nothing stored is deleted, naming it again brings it back, and the front end was already hiding it.
* Security: the handlers that write vehicle and booking meta now verify the post type of the post they are writing to. edit_post answers whether a user may edit a given post, never whether that post is one of this plugin's, so a handler acting on whatever id arrived was writing to an object it had not identified. The booking meta handler was the widest case: hooked to the untyped save_post, saving any page or post on the site wrote a booking status onto it. Twelve handlers were corrected; an independent audit then found three more outside the recorded list, including a live AJAX handler that wrote vehicle ordering meta onto any post id it was given.
* Fixed: an admin screen whose data fails to load now says so instead of going blank. The failure that prompted this happened in the companion add-on, where one bad endpoint map took out five screens at once -- the three wrapped in an error boundary showed a message and could be refreshed, while the two that were not left an empty page under the WordPress chrome with nothing explaining it. Sweeping the same class here rather than fixing only the screens that had already failed found one unwrapped screen in this plugin. Every React screen here is wrapped now, and a gate keeps new ones from shipping unwrapped.
* Changed: shared admin modules and the React page loader now come from the mhm/ui-core package rather than copies kept here, and duplicate CSS custom-property declarations were consolidated. Checked in the browser, light and dark: nothing rendered differently.

= 6.1.2 =
* Fixed: a booking created from the admin's manual booking screen was stored with no status at all. The screen's script read the status field by its name rather than its id, matched nothing, and sent an empty value that the handler stored as-is. The availability check counts only live statuses, so such a booking was invisible to it and the same vehicle could be booked again over the same dates.
* Security: the manual booking screen's boundary now refuses any status the screen itself does not offer, instead of writing whatever arrives.
* Fixed: the confirmation prompt before changing a booking's status on the edit screen never appeared -- it was bound to the status field by name rather than by id, so it was bound to nothing. The field's label was associated with the same missing id and is now linked to the select.
* Security: the role given to customer accounts this plugin creates is rejected if it carries administrative capabilities, rather than accepted because the role exists. The same setting decides which accounts count as customers, so a privileged value affected the Customers list too.

= 6.1.1 =
* Fixed: a My Account tab contributed by an extension registered no endpoint at all when WooCommerce was active, so every such tab returned a 404. The extension point that carries those tabs was only consulted on the standalone-endpoint path, which WooCommerce sites never take.
* Fixed: the rewrite-flush check ignored extension endpoints and ran only in the admin, so a newly contributed tab could stay unreachable until someone opened wp-admin and saved permalinks by hand.
* Security: an extension's endpoint contribution is now rejected when it collides with a reserved WordPress or WooCommerce query variable. Registering one as an endpoint would have broken permalinks site-wide.
* Fixed: the four core module scripts were requested a second time, without their version query, because the plugin URL already ended in a slash and the loader appended another.

== Upgrade Notice ==

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
