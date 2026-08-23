=== MHM Rentiva ===
Contributors:     maxhandmade
Tags:             car rental, vehicle rental, booking, reservation, rent a car
Requires at least: 6.7
Tested up to:      7.0
Requires PHP:      8.1
Requires Plugins:  woocommerce
Stable tag:        6.0.7
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

Only the most recent releases are repeated here, plus 6.0.0 for its breaking-change notice, to keep this file within the length WordPress.org's readme parser renders. The complete history, in English and Turkish, ships with the plugin as changelog.json and changelog-tr.json.

= 6.1.0 =
* Changed: money amounts now convert between the store's currency and its smallest unit using the store's own decimal setting (WooCommerce's "Number of Decimals," under Currency Options) instead of a fixed multiplier of 100. A store running the default two decimals sees no change. On a store configured for any other number of decimals, refund and payment amounts recorded before this release are re-read at the new scale rather than converted -- there is no migration step for existing records.
* Fixed: a booking whose deposit was taken outside WooCommerce could still be offered a WooCommerce payment link for its remaining balance. For a manually created booking that never had a WooCommerce order, using that link always failed -- the same guard that makes taking the deposit offline possible also refuses to build a WooCommerce order for money it already knows arrived elsewhere. For a booking created through WooCommerce checkout whose deposit was then marked received offline, that link had actually worked: it created the order, and once paid it silently made the earlier offline deposit vanish from the booking's recorded paid amount. Both cases are now refused for the same reason. The deposit screen recognises them and explains it in place of that button. "Process Remaining Amount," which records the balance as settled without going through WooCommerce, is unaffected and still works.
* Fixed: refunds of a paid order now work. The refund screen used to refuse any order that had actually been paid, because its check asked whether the order was still editable in WooCommerce -- true only for a pending, on-hold or auto-draft order -- rather than whether the gateway could actually send the money back.
* Fixed: a deposit booking's remaining-payment order is now refunded together with the deposit order. It used to be invisible to refunds entirely: the lookup a refund used to find a booking's WooCommerce order recognised four older meta keys but not the one the remaining-payment order is stored under.
* Fixed: a refund is now recorded once and announces itself once. A single refund used to run through two separate record-and-notify steps: WooCommerce's own refund hook wrote the refunded total onto the booking, then control returned to the Rentiva action that had just requested the refund, which added the same amount on top and sent its own e-mail -- so one refund was counted twice and the customer received two e-mails for it as soon as it succeeded.
* Fixed: the default refund e-mail now tells the customer whether their money is returning automatically to their original payment method or has to be transferred by hand, instead of always promising an automatic return regardless of which happened. A site running a customised refund e-mail body keeps sending its own text unchanged, with no mode sentence added.
* Fixed: manually created bookings are now recorded as offline payments. They used to be stored with no payment method at all, which left them unlabelled in the booking list and its filter, and refused by the refund path as an unsupported payment method.
* Fixed: payment-method reports now group new manual bookings under "offline" instead of leaving them in a blank, unlabelled bucket.
* Fixed: the refund box on the booking screen now shows its refund form for offline bookings with a balance -- for the first time on any site. The box itself has always been there; in place of the form it printed "No refundable payment found for this booking," because the form required a paid amount read from a meta key nothing wrote.

* Fixed: cancelling a paid booking now actually refunds it. Until this release a cancellation marked the booking as awaiting a refund, fired an extension point that nothing listened to, and stopped -- no money ever moved, on either the customer's own cancellation or the operator's cancel button on the deposit screen. Both now run the refund through to the payment gateway, or record it as a manual refund where the gateway cannot return the money itself.
* Changed: a customer who cancels their own booking before the cancellation deadline now receives the whole refundable balance back automatically, with no operator step in between. The plugin defines no cancellation fee, so a full refund is the only policy it can apply.
* Fixed: the cancellation e-mail no longer tells the customer their refund will be credited to their original payment method. It cannot know: that e-mail is sent before the refund runs, and where the gateway cannot return the money, a person transfers it by hand. The e-mail now says a separate refund notice will follow, and that notice states which of the two happened.
* Fixed: the refund notice now reaches the customer. It read an e-mail field that the contact form writes and bookings do not, and when the field was empty it skipped the notice in silence. It now resolves the address the same way the rest of the plugin does -- the booking's own customer e-mail, then the WooCommerce order's billing address, then the linked account -- and records the outcome when a booking genuinely has no address on file.
* Fixed: a refund no payment gateway performed is no longer recorded as completed. It is recorded as awaiting a manual transfer, because the money has not moved until someone moves it.
* Fixed: a refund split across a card-paid deposit and a hand-paid remainder no longer instructs the operator to transfer the whole amount by hand. The two amounts are now named separately: what the gateway has already returned, and what still has to be transferred.
* Fixed: cancelling a booking whose vehicle or dates are incomplete no longer fails outright. Releasing the vehicle's blocked dates is treated as bookkeeping that can be skipped and is recorded when it is, instead of refusing the cancellation the operator asked for.
* Added: refunds on one booking are serialised, so a cancellation and a refund started from the admin screen at the same moment cannot run against the same booking at once.
* Added: mhmrentiva_refund_completed, an action that fires when a refund operation ends, with the booking id and the operation's result.
* Fixed: cancelling or failing one order on a deposit-plus-remaining booking no longer touches the booking's other, already-paid order. Before this, cancelling the unpaid remaining-payment order (or having it fail) demoted or cancelled the whole booking even though its deposit order had already been paid, with no refund anywhere -- cancelling the collection instrument was not the same as cancelling the debt. The paid order is now left alone, the dead order's link is cleared so a new payment link can be issued for what is still owed, and an operator is notified.
* Fixed: cancelling a booking and issuing a refund now share the same permission check -- can this specific actor move money on this booking -- asked once at the shared money step both paths run through, instead of each entry point asking its own version. Cancelling a booking used to check whether the CURRENT logged-in session was an administrator rather than whether the person the cancellation was attributed to had that right; every caller now inherits the corrected, actor-specific question automatically, including any added later.
* Security: removed a refund endpoint that read its amount directly from the request, with no check against what the booking actually owed, and asked no permission question of its own. It had no working way to be reached in this release -- the button that once triggered it was already removed -- but re-arming it is exactly what a future retry-refund feature would do by accident, so it is deleted rather than left dormant.
* Added: the booking screen's refund box now shows the booking's current refund status for every booking that has one. It used to be blank whenever the booking's own remaining balance happened to read as zero -- which is also true of a booking with nothing left to refund -- so a booking actually awaiting review, a manual hand transfer, or a partial failure showed nothing at all.
* Added: a refund awaiting a hand transfer can now be closed from the booking screen once the money has actually moved by hand, recording who confirmed it, when, and an optional payment reference -- added to the booking's own record and, where a WooCommerce order exists, as an order note too.
* Added: a booking parked for manual review because its refund needs a human decision now has two actions on the booking screen: cancel it and run the refund after all, or record that no refund is due, with a required reason kept on the booking's record.
* Changed: a cancellation whose refund runs into a problem is no longer reported as if the cancellation itself had failed. The booking is cancelled either way; the operator is now told the cancellation succeeded and, separately, that the refund needs attention, instead of being left unsure whether the button did anything at all.
* Fixed: a booking paid in more than one currency (for example, a deposit taken in one currency and a later payment recorded in another after a store's currency setting changed) is no longer closed automatically as "no refund needed." Summing two currencies together is not a meaningful amount, so such a booking is now parked for manual review instead, and an operator is notified. Before updating: if any of your existing bookings are in this state, they will appear in the needs-review queue after this update instead of staying silently closed.
* Fixed: several places that only logged quietly, or not at all, when a notification e-mail failed to send now record it as an error visible to an operator -- among them a paid order left alone because a sibling order was already cancelled, and a booking parked for review because it needed a human decision. Both could previously fail to notify anyone with nothing left to show for it.
= 6.0.7 =
* Security: removed an unused routine that could write WordPress user accounts. The plugin's customer performance helper carried a batch-update method that changed accounts' display names, e-mail addresses, phone numbers and postal addresses after checking only that the caller may edit users in general — never that they may edit that particular account, nor that the account was one this plugin manages. Nothing in this plugin called it, in this release or any earlier one, and there is no screen or address through which it could be reached; it was removed rather than repaired, because unreachable code that writes user accounts should not ship at all. The two places that do edit an existing customer — the customer edit screen and the delete route — already asked that per-account question beside the write, and still do. Adding a customer is a different case: it creates the account, so there is no existing account for such a question to be about, and it is gated on the permission WordPress itself requires to create a user.

= 6.0.6 =
* Fixed: vehicles you had not published could be shown to visitors. The availability calendar chose its default vehicle from a list that deliberately included drafts and private vehicles, its vehicle switcher offered that same list, and the three addresses the calendar and the vehicle details page call for their data checked only that the ID named a vehicle — never that it was published. One of those is the address the calendar calls every time you change month, and it answered a draft ID with that vehicle's day-by-day occupancy and its daily prices. A vehicle you were still preparing, or had taken off the site on purpose, could therefore be picked in the calendar and have its details, availability and prices read by anyone. All five now require a published vehicle. The booking form's own availability and price addresses still answer for an unpublished vehicle; that is recorded and will follow.
* Fixed: the public availability calendar published the booking records behind each day. Every day in the data it hands to the browser carried the bookings covering it — each one's record number, its title and whether it had been paid — and the calendar's own markup printed those titles into the day's tooltip and into a label on each mark. None of it required logging in; viewing the page was enough to read which of your bookings were unpaid and what their record numbers were. A day now publishes its state and how many bookings cover it, and nothing else; the records stay on the server, where the state is worked out.
* Fixed: the address the calendar calls for its data accepted a request for any number of months. That address answers without logging in, and the number decides how many days the server walks through twice as well as what it files the result under — so a single request could ask for a span of years and be served. It is now capped at twelve months, far beyond the one to three the calendar itself ever asks for. A malformed start month sent to the same address also failed with a fatal error on PHP 8 instead of being refused; it now falls back to the current month.
* Fixed: the testimonials slider ran three unbounded database reads on every public page it appeared on. Two asked for every matching record and the third for every matching comment, and the code then kept the first five. On a site with a few thousand reviews that is a few thousand records loaded to display five of them. Each read is now bounded, and the total shown is taken from the count the database already reports rather than by loading every matching record and counting them in PHP.
* Fixed: the address that loads more customer reviews published each reviewer's e-mail address. Every review it returned carried an e-mail — taken from the booking, or from the comment the visitor left — in a field nothing on the page has ever displayed. The addresses were therefore never visible on screen, but they were in the response, and that address answers without anyone logging in. The field is gone from both sources.
* Fixed: reviews of a vehicle you had taken off the site were still shown publicly, and still named it. The reviews slider read comments without regard to whether the vehicle they belong to is published, and the address that lists one vehicle's reviews checked what kind of content the ID named but not whether it was published. Both now require a published vehicle, and the total behind "load more" counts the same reviews the list shows rather than more. That address was also unbounded — it returned every approved review on a vehicle, however many there were — and is now capped at fifty per read.
* Fixed: four places worked out which WooCommerce order a booking belongs to, and they disagreed with each other. Four different fields have carried that link over this plugin's life and two of them are still written; the bookings list left out the one current checkouts write, and the WooCommerce bridge read two of them in the opposite order to everywhere else. The same booking could therefore show its order on one screen and appear to have none on another. All four now use a single shared lookup.
* Internal: three addresses that answer with JSON wrapped their reply in an error handler that also caught the signal WordPress raises to end a request, and then appended a second, contradictory reply after the first. The browser acted on the first reply, so the effect was not visible, but it made those addresses impossible to test properly — the first test written for the calendar's limits could not read its own response. These three are corrected; the same shape remains in ten other places and is recorded to be dealt with.

= 6.0.5 =
* Fixed: two staff members creating a manual booking at the same moment could both book the same vehicle for the same dates. The availability check and the saving of the booking were two separate steps with nothing holding the vehicle in between, so both could see "free" and both could save. This is the same defect fixed on the customer checkout path in 6.0.3; the plugin already had the mechanism, but only the customer path used it. The check and the save on the manual path are now one indivisible step, and a booking taken in that instant is refused with a clear message instead of being written.
* Fixed: a booking that failed to save told you it had been created. When WordPress cannot save a record it answers with the number zero rather than an error, and the check here only recognised an error — so a failed save carried straight on to the success message, and the screen offered a link to edit a booking that did not exist. The same mistake was in five other places: a WooCommerce order could complete with no booking attached and nothing written to the log; a contact message could report "sent successfully" to the visitor and e-mail you a copy while nothing was stored for you to answer; and the setup wizard could report a page it had not created. All of them now recognise a failed save, report it as a failure, and record it.
* Fixed: this plugin's own activity log could write its entries onto your first post. The log took care to ask WordPress for a proper error object when saving failed, then converted that object to a number before looking at it — and converting an object to a number in PHP yields 1. A failed log entry therefore looked like the record with ID 1, which on most sites is a real post, and the log's details were written onto it as hidden fields. Nothing was visibly changed on the post itself, but it was being written to. The failure is now recognised before the conversion.
* Fixed: a manual booking that was refused still created the customer's account. On the manual booking screen, choosing "new customer" created the WordPress account before the vehicle's availability was checked, so a booking refused for a full or unavailable vehicle left a real account behind. Worse, retrying with corrected dates was then refused as well, because the e-mail address was now already registered — to an account the operator had never knowingly created. The account is now created only once the vehicle is known to be free.
* Fixed: the address a visitor appears to come from could be chosen by the visitor. Three separate places worked out the client's IP by trusting headers such as X-Forwarded-For and CF-Connecting-IP, which any caller can set to any value. Two of them fed the limits that stop a form or a public endpoint being hammered — so changing the header on each request gave a fresh allowance and the limit never engaged — and the third fed this plugin's own security log, meaning an attacker could record someone else's address against their own requests. All three now use the actual connection address. A site genuinely behind Cloudflare or a load balancer can re-enable specific headers with the mhmrentiva_trusted_proxy_ip_headers filter.
* Fixed: the counters behind those limits could undercount exactly when they mattered. Each request read the counter, added one and wrote it back, so requests arriving together read the same value and wrote the same increment — many hits could advance the counter by one. Where your site has a persistent object cache (Redis or Memcached) the counter now uses that cache's own atomic increment. Sites without one keep the previous storage, which still limits correctly request by request.
* Fixed: a file attached to the contact form was stored under a name derived from the visitor's own filename, in the folder your site serves to anyone. Attachments are now stored under an unguessable random name. The form's submission limit was also left at a value raised for testing (50 submissions per five minutes) and is now 5.
* Fixed: the manual booking screen never explained the weekend surcharge. It printed "Daily Price: 2800" beside "Vehicle Total: 6720" for a two-day booking, leaving whoever takes the booking to work out where the other 1120 came from — it is the weekend rate, which this plugin charges for every Saturday and Sunday in the rental (Settings → Vehicles). The breakdown now shows the surcharge and how many weekend days it covers, the same way the customer-facing booking form already did.
* Fixed: on the manual booking screen, a price worked out for one set of dates could stay on screen beneath another. Change the dates to a range the vehicle is already booked for, and the calculation is refused — but the previous total remained in place, looking like the answer for the dates now shown, and the "Create Booking" button stayed available. The stored booking was never wrong, because the price is always recalculated on the server from the submitted dates and nothing the browser sends is trusted; the risk was the figure being quoted to the customer. A refused calculation now clears the breakdown and hides the button until a valid price is calculated.

= 6.0.4 =
* Fixed: the "process refund" button on the deposit management screen never refunded any money. It worked out how much was owed back under your cancellation policy, marked the booking refunded and reported "Refund completed successfully" — but it never asked WooCommerce or the payment gateway to send anything. A refund could therefore look done, in the booking and on screen, while the customer was never paid. The button now goes through the plugin's refund service, which contacts the gateway and checks the result: nothing is marked refunded unless money actually moved, and a failure is reported as a failure. If you have marked bookings refunded on this screen, check them against your gateway's own records.
* Fixed: customers could not cancel their own bookings. The permission check read the booking's owner from a field nothing has ever written, so it always came back empty and every customer was refused — the cancel action and the "can this be cancelled" test the interface uses both failed the same way. Ownership is now read from the field the booking actually stores. It failed closed, so nobody could cancel anybody else's booking either.
* Fixed: "reset shortcode pages" could permanently delete pages you wrote yourself. It looked up each shortcode's page by searching every published page for that shortcode, then force-deleted what it found — bypassing the trash. A page of your own that happened to contain a Rentiva shortcode was an equally valid match. Both the reset and the single-page delete now act only on pages this plugin created.
* Fixed: settling the remaining payment could mark a booking completed while the customer still had the car. Only the drop-off date was considered, not the time, so a rental due back at 18:00 counted as finished from midnight that morning. The drop-off time is now taken into account, and a booking with no time recorded is treated as running until the end of its last day rather than from its start. The same correction is applied where a WooCommerce order completes a booking.
* Fixed: the Customers screen listed almost every account on your site — administrators, editors, subscribers and accounts belonging to other plugins — as though they were customers, and counted them in the totals and pagination. It now lists only genuine customers, using the same definition the customer detail, delete and export actions already used: an account a booking points at, one this plugin created, or one carrying the customer role. This is the narrowing announced as pending in 6.0.2.
* Added: the plugin now checks at startup that PHP's mbstring extension is available and shows a clear notice if it is not, instead of failing later with a fatal error — including on the customer-facing review form.
* Fixed: sending a contact form with no attachment raised a PHP warning on every submission, because the attachment list was only created when a file was present. The mail was still delivered; only sites running WordPress in debug mode would have seen it recorded.

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
