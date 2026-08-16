=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.0
Requires PHP:      8.1
Requires Plugins:  woocommerce
Stable tag:        6.0.4
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

Older releases are not repeated here, to keep this file within the length WordPress.org's readme parser renders. The complete history, in English and Turkish, ships with the plugin as changelog.json and changelog-tr.json.

= 6.0.4 =
* Fixed: the "process refund" button on the deposit management screen never refunded any money. It worked out how much was owed back under your cancellation policy, marked the booking refunded and reported "Refund completed successfully" — but it never asked WooCommerce or the payment gateway to send anything. A refund could therefore look done, in the booking and on screen, while the customer was never paid. The button now goes through the plugin's refund service, which contacts the gateway and checks the result: nothing is marked refunded unless money actually moved, and a failure is reported as a failure. If you have marked bookings refunded on this screen, check them against your gateway's own records.
* Fixed: customers could not cancel their own bookings. The permission check read the booking's owner from a field nothing has ever written, so it always came back empty and every customer was refused — the cancel action and the "can this be cancelled" test the interface uses both failed the same way. Ownership is now read from the field the booking actually stores. It failed closed, so nobody could cancel anybody else's booking either.
* Fixed: "reset shortcode pages" could permanently delete pages you wrote yourself. It looked up each shortcode's page by searching every published page for that shortcode, then force-deleted what it found — bypassing the trash. A page of your own that happened to contain a Rentiva shortcode was an equally valid match. Both the reset and the single-page delete now act only on pages this plugin created.
* Fixed: settling the remaining payment could mark a booking completed while the customer still had the car. Only the drop-off date was considered, not the time, so a rental due back at 18:00 counted as finished from midnight that morning. The drop-off time is now taken into account, and a booking with no time recorded is treated as running until the end of its last day rather than from its start. The same correction is applied where a WooCommerce order completes a booking.
* Fixed: the Customers screen listed almost every account on your site — administrators, editors, subscribers and accounts belonging to other plugins — as though they were customers, and counted them in the totals and pagination. It now lists only genuine customers, using the same definition the customer detail, delete and export actions already used: an account a booking points at, one this plugin created, or one carrying the customer role. This is the narrowing announced as pending in 6.0.2.
* Added: the plugin now checks at startup that PHP's mbstring extension is available and shows a clear notice if it is not, instead of failing later with a fatal error — including on the customer-facing review form.

= 6.0.3 =
* Fixed: two customers checking out at the same moment could both book the same vehicle for the same dates. The check meant to prevent this asked the database to hold the vehicle's records while the booking was written, but never opened the transaction that makes such a hold last — so the database let go of it immediately, before the booking was created. Both checkouts could read "available" and both could write a booking. The check and the booking are now one indivisible step; when a conflict is found the WooCommerce order is cancelled exactly as before. A site where two people rarely start checkout in the same second was unlikely to have hit this, but nothing prevented it.
* Fixed: an uploaded payment receipt was stored under a name taken from the customer's own file, in the folder your site serves directly to visitors. Marking the upload "private" keeps it out of WordPress's media listings but puts nothing in front of the file itself, so anyone who guessed the address could open it. Receipts are now stored under an unguessable random name and handed out through an address that checks who is asking — the customer the booking belongs to, or your staff. Receipts uploaded by earlier versions keep their existing names; re-upload one if you want it renamed.
* Fixed: asking for a remaining-payment link twice in quick succession — a double click, two open tabs — could create two separate WooCommerce orders for the same outstanding amount, leaving one of them unpaid and unaccounted for. The lookup and the creation are now a single step, so a second request hands back the order the first one made.
* Fixed: the review form accepted whatever content ID the browser sent it without checking that it was a vehicle, so a review could be attached to a blog post or a page — which would then also carry this plugin's rating figures. A review is now refused unless its target really is a vehicle.
* Fixed: switching between deposit and full payment answered the browser with an amount of zero on every successful switch, because the figure was worked out into a value nothing ever read. The cart itself was always correct and customers were charged the right amount; only the reported number was wrong. It now reports the cart total.

= 6.0.2 =
* Fixed: the customer screens and the endpoints behind them acted on whichever account they were given, checking only that you were allowed to manage users in general — never that the particular account was one of your customers. The customer detail view would open any account's profile, spend history and contact details by address alone, and the CSV export would write any account handed to it — an editor, a second administrator — into a downloaded file of personal data. They now check two separate things before acting: that WordPress permits you to act on that specific account, and that the account is actually a customer of this plugin — one the site has a booking for, one this plugin created, or one carrying the customer role. Accounts that are none of those are left alone. Note that the Customers list itself still shows every account on the site; narrowing it is a separate change and is not in this release, so a listed account that is not a customer will now decline to open, and will not appear in an export.
* Fixed: "delete selected" on the Customers screen never worked. It has raised a fatal error on every attempt since the action was added, because it called a WordPress function that only exists inside the admin pages and not on the address this action is served from — so no account was ever deleted by it, and sites running WordPress in debug mode recorded the error. The action now loads what it needs and completes, with the per-account checks described above applied to every target. If you have been deleting customers one at a time because the bulk action appeared to do nothing, that is why.
* Fixed: the bulk actions on the Additional Services screen ran on any post ID they were sent, without checking it was an additional service. The delete action there is a permanent delete, so a request naming a page, a vehicle or another plugin's content would have destroyed it, and the enable/disable actions would have written this plugin's settings onto it. Every target is now verified before anything touches it.
* Changed: the default footer of the e-mails this plugin sends to your customers no longer reads "Powered by MHM Rentiva". It is now just your site's name. The field is still yours to edit on Settings → E-mail, so if you want the credit you can type it; the plugin no longer adds it on your behalf. Sites that already saved a footer keep whatever they saved.
* Internal: the booking list's Title column escapes its output at the point it hands the value back to WordPress rather than inside the helper that builds it. The escaping was already correct and the rendered output is byte-for-byte unchanged; the rewrite makes it verifiable where an automated review reads it.

= 6.0.1 =
* Removed: this plugin used to create named indexes of its own on WordPress's own core tables -- 20 under the current naming, and 35 counting the pre-6.0.0 spellings the cleanup also drops (wp_posts, wp_postmeta, wp_usermeta) to speed up its own queries. Those tables are shared with every other plugin and theme, and there was no reliable way to know it stayed safe to keep adding to them, so that subsystem has been removed. A one-time cleanup migration drops the old indexes automatically the first time you open the admin after updating; the plugin's own tables are unaffected and no data changes. If your database user is not permitted to drop indexes, the cleanup now stops after three attempts and records which ones it could not remove, instead of retrying on every admin request indefinitely.
* Fixed: submitting a new review through the rating form raised seven PHP warnings and a deprecation notice every time, which any site running WordPress in debug mode wrote to its error log. The cause was that the review was handed to WordPress without the three comment author fields WordPress reads before filling in its own defaults. One consequence was visible without debug mode: a review left by a logged-in customer was stored with no name and no e-mail address on it, so it appeared unattributed in the review list and WordPress's own Tools → Export/Erase Personal Data could not match it. New reviews now carry the display name, e-mail address and website address from the reviewer's profile, exactly as WordPress's own comment form stores them. Reviews saved by earlier versions are not changed.
* Fixed: on pages built with this plugin's blocks rather than its shortcodes, the favourite and compare buttons did nothing when clicked. They appeared, they showed the correct saved state, and the browser console stayed clean — the script that handles the click was simply never loaded on those pages. Pages built with shortcodes were never affected.
* Added: seasonal pricing multipliers can now be configured, and switched on. Neither was possible before. The screen that renders the multipliers had no way in, and the setting that applies them had no control anywhere in the admin — with nothing able to turn it on, the multipliers sat in the database and never reached a price. Both now live on Settings → Vehicle Management, under "Vehicle Pricing Settings". The switch defaults to off, so pricing is unchanged unless you turn it on.
* Fixed: four logging settings — Log Level, Debug Mode, Auto Cleanup Logs and Log Retention (Days) — had no screen left to appear on, so nobody could see or change them, while the plugin went on reading them. They are back on Settings → System & Performance, under "Logs & Debugging".
* Removed: two separate daily jobs were deleting old log entries, and their built-in defaults contradicted each other — with no setting saved, one treated automatic cleanup as on and the other as off. The redundant job is gone, along with the single unbounded delete it ran, and any copy still scheduled on your site is now cancelled automatically. What remains is the job that honours your Auto Cleanup Logs and Log Retention settings and deletes in small batches.
* Fixed: filtering the vehicle list by "Featured" silently cancelled the separate "active vehicles only" filter, so an inactive vehicle could appear in a featured-only view. Both filters now combine correctly.
* Fixed: a vehicle with no featured image rendered a broken image nearly everywhere it appeared — the vehicle grid, list, search results, featured-vehicles and favourites cards, the vehicle detail page, the booking form's selected-vehicle summary and the alternative-vehicle suggestions it offers when your dates are taken, and the My Account bookings list and booking detail. There were two separate causes: the two account screens pointed at a placeholder file that has never been part of this plugin, and everywhere else the built-in fallback was an image embedded directly in the page address, which WordPress's own URL escaping strips out before it reaches the browser — leaving an empty image behind. This release ships a real placeholder image and points every one of those places at it.
* Fixed: the vehicle add-on price breakdown printed its own HTML as visible text instead of rendering it, and prefixed its total with the three-letter currency code ("USD1,234.56") instead of the currency symbol you configured.
* Fixed: adding a custom feature, equipment item or detail on the vehicle edit screen threw a JavaScript error into the browser console every time, because the last thing the script did was call a function that does not exist anywhere in the plugin. The item itself was added and saved correctly — the error fired after the work was finished, with nothing left to interrupt — so what you actually got was a console full of errors, not a failed add. Re-ordering existing items by dragging was never affected and is unchanged.
* Fixed: adding or removing a vehicle from your favourites showed no confirmation message at all — the script referred to a variable that was never defined and stopped there. The message now appears, and says which of the two actions happened.
* Fixed: every dialog on the notification-templates screen — Send Test Email, Edit Template, and their fields and buttons — appeared in English on every site regardless of language, because its translations were never passed to the script.
* Fixed: on the vehicle edit screen, one script was being registered twice under two different handles carrying two different sets of data; depending on load order, one registration could silently overwrite data the other needed. It is now registered once.
* Fixed: several screens ran the notification ("toast") script without declaring that it depends on its support library, so on those screens WordPress never loaded the library and every success and error message failed to appear. The dependency is now declared everywhere it is used.
* Fixed: the "Duplicate" action shown in the add-ons list did nothing when clicked; it has been removed.
* Removed: a dozen admin-ajax endpoints and several settings screens that had no menu entry, button or working caller anywhere in the admin. All were unreachable dead code; nothing you could previously do in the admin is affected.
* Internal: several vehicle-list, search and bookings-by-date queries now name their sort clauses explicitly instead of using a shorthand that made WordPress build a second, redundant table join. The bookings-by-date query loses that extra join; the others return exactly the same rows in the same order as before, and every rewrite was locked by a test written and run against the old code first.
* Internal: a seasonal multiplier stored in a malformed shape could crash the public booking form. The booking form now skips an unusable season instead, and the settings screen will no longer store one.
* Internal: the plugin's full automated test suite — unit tests plus WordPress integration tests — passes with zero failures on this release.

= 6.0.0 =
**This is a major release. If you have custom code that hooks into this plugin, read the next paragraph before you update.**

* Breaking: 113 of this plugin's hooks were renamed. Anything attached to one of them — a snippet in your theme's functions.php, a code-snippets plugin, a custom integration, bespoke work done for you — will simply stop running after the update. There is no error message and nothing in the log; the customisation just quietly stops happening. (Two further hooks, `mhm_rentiva_enable_governance_log` and `mhm_rentiva_governance_violation`, were removed rather than renamed, along with the feature behind them.)

* How to convert your own code. **Apply these in the order given — the order matters.**
  1. First, in the older slash-style names, replace every `/` with `_`. So `mhm_rentiva/testimonials/limit` becomes `mhm_rentiva_testimonials_limit`.
  2. Then replace the prefix `mhm_rentiva_` with `mhmrentiva_`. So that name finishes as `mhmrentiva_testimonials_limit`.
  3. Two hooks used a bare `mhm_` prefix and follow the same idea: `mhm_message_created` and `mhm_message_status_changed` become `mhmrentiva_message_created` and `mhmrentiva_message_status_changed`.
  Doing step 2 before step 1 produces names that do not exist — `mhm_rentiva_` cannot match a name that begins `mhm_rentiva/`, and you would end up with a plausible-looking hook that never fires.

* Breaking, and easy to miss because it is not one of our hook names: **the plugin's post types and taxonomies were renamed too**, which changes the WordPress core hooks built from them. `save_post_vehicle` is now `save_post_mhmrentiva_vehicle`; the same applies to `add_meta_boxes_*` and to the `manage_*_posts_columns` and `manage_*_posts_custom_column` pairs. If you have code that reacts to a vehicle or booking being saved, this is the line most likely to be affected, and neither rule above will find it. The renames are: `vehicle` → `mhmrentiva_vehicle`, `vehicle_booking` → `mhmrentiva_booking`, `vehicle_addon` → `mhmrentiva_addon`, `mhm_app_log` → `mhmrentiva_app_log`, `mhm_email_log` → `mhmrentiva_email_log`, `mhm_contact_message` → `mhmrentiva_contact`; and for taxonomies `vehicle_category` → `mhmrentiva_vehicle_category`, `addon_category` → `mhmrentiva_addon_category`. (The add-on context taxonomy was removed outright in 6.0.1 rather than renamed, so no hook is built from either spelling.)

* The same rename affects any query you have written by hand. A `WP_Query` with `'post_type' => 'vehicle'`, a `pre_get_posts` handler that checks for it, a `get_posts` call, a shortcode of your own — all of these now match nothing, silently, because they are asking for a post type that no longer exists under that name. The old names were generic enough (`vehicle`, `vehicle_booking`, `vehicle_category`) that this is worth searching for even if you do not think you wrote any.

* What to search your custom code for, before you update: `mhm_rentiva` (catches both the underscore and slash families), `mhm_message_`, `save_post_vehicle`, and the quoted literals `'vehicle'`, `'vehicle_booking'`, `'vehicle_addon'` and `'vehicle_category'`.
* Breaking: the three Elementor widgets named "Featured Vehicles", "Vehicles Grid" and "Vehicles List" were renamed internally. Pages you have already built with them are migrated automatically on update and need no attention; this is listed only because a widget name, like a hook name, is something custom code can refer to.
* Changed: every identifier the plugin registers — settings, custom post types, taxonomies, database tables, scheduled jobs, and the keys it stores against your posts and users — now uses a single consistent prefix. Your existing data is moved to the new names by a migration that runs once, automatically, the first time you load the admin after updating. Nothing is deleted and nothing needs to be re-entered. If you also run the paid add-on, update both at the same time: this version refuses to migrate underneath an older add-on rather than leave your data half-moved.
* Fixed: choosing "Semi-Automatic" or "CVT" for a vehicle's transmission, or "LPG", "CNG" or "Hydrogen" for its fuel type, silently saved a different value than the one selected. The list the editor offers and the list it accepts had drifted apart; they are now the same list.
* Fixed: the plugin's custom fields are now registered on every request rather than only inside the admin, so the sanitising rules that protect them apply everywhere instead of only on admin screens.
* Fixed: uninstalling the free plugin no longer removes the paid add-on's tables, which hold commission ledgers, payout records and the audit trail that signs them.
* Security: the remaining places where the plugin built SQL by hand have been reshaped to use WordPress's prepared-statement API, and the comments that explain each remaining direct query have been checked line by line against the query they describe.

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
