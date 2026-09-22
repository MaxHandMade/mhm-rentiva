<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Rentiva's own icon concepts — the single source the runtime and the gate share.
 *
 * The kit takes a CONCEPT for its `icon` prop ("revenue") and resolves it to a
 * Dashicon suffix ("money-alt"), so a KPI card says what it means rather than
 * what it draws. ui-core ships twelve domain-independent seed concepts and a
 * `register()` seam for a product's own nouns. This is ours.
 *
 * 🔴 WHY A CONSTANT AND NOT A LITERAL AT EACH CALLER. Three places need this
 * map: the runtime registration in mhm-rentiva.php, the CI gate in
 * bin/check-icon-concepts.php (which must know the product's vocabulary or it
 * reports every product concept as an unknown suffix), and — separately — the
 * JSX bundles, because the package's JSX registry lives inside whichever
 * bundle registered it and never reaches another. Two of those three can read
 * this constant; the third cannot, and IconConceptsTwinTest pins it instead.
 * A vocabulary written out three times drifts, and a gate measuring a
 * different vocabulary than the product uses is worse than no gate.
 *
 * 🔴 A CARD NAMES WHAT ITS FIGURE MEANS, AND THE GLYPH FOLLOWS. Adopting the
 * vocabulary first preserved every glyph (measured across fifteen strips), and
 * that left cards whose concept contradicted their figure -- a booking count
 * called `pending`, an occupancy rate called `total`. A central glyph change
 * would then have moved them with the wrong group. The rule since 2026-09-22
 * (approved pixel change): "Total X" cards use X's noun, "Active X" cards use
 * `active`, money, rate and state cards use the concept for that. A new card
 * picks its concept by that rule, not by the glyph it wants.
 *
 * This file must stay free of WordPress: the gate requires it outside WP,
 * defining ABSPATH first so the guard above lets it through.
 */
final class IconConcepts {

	/**
	 * Concept => Dashicon suffix. Registered on top of ui-core's seed map.
	 *
	 * @var array<string, string>
	 */
	public const MAP = array(
		// The product's central noun. The seed has no vehicle concept, and
		// `items` (products) would read as a shop's catalogue rather than a
		// rental fleet.
		'vehicles' => 'car',

		// A count of reservations, drawn on a calendar. The seed's `time` carries
		// this exact glyph and was used here first, but a COUNT card calling
		// itself "time" inverts the point of the vocabulary: the prop is supposed
		// to say what the card means, and this one means bookings. `time` stays in
		// the seed for a card that is genuinely temporal.
		'bookings' => 'calendar-alt',

		// A price, not revenue: the add-on screens show what services cost
		// (average, sum of all prices), which nobody has been paid yet.
		'price'    => 'money-alt',

		// Bookings that finished. The seed's `active` means "in effect now";
		// a completed booking is over, so it keeps the plain tick. Shared with
		// Pro's vendor screen -- see tests/fixtures/icon-concepts-shared-with-pro.php.
		'completed' => 'yes',

		// Fleet occupancy, a rate. Shared with Pro, which drew it as a pie first.
		'occupancy' => 'chart-pie',

		// The shortcode-pages screen counts WordPress pages it created and the
		// ones that are missing -- neither is a seed noun.
		'pages'    => 'admin-page',
		'missing'  => 'warning',
	);

	/**
	 * Concept => the glyph to draw when the WINNING ui-core cannot resolve it.
	 *
	 * 🔴 WHY A SECOND MAP EXISTS, AND WHY IT IS NOT REDUNDANT. ui-core
	 * arbitrates by version: the highest registered copy boots and serves every
	 * plugin on the site. A sibling MHM plugin bundling 0.11-0.13 can therefore
	 * win over Rentiva's 0.14 -- that is a supported install, not a broken one.
	 * Those copies have no `Kit\Icons`; their StatCard concatenates
	 * `'dashicons dashicons-' . $icon` with no resolution step. Handing such a
	 * copy `revenue` prints `dashicons-revenue`, a class no stylesheet defines,
	 * and every PHP KPI card on the site loses its icon -- silently, with no
	 * error and no failing gate. So Rentiva resolves the concept itself before
	 * delegating, and this is the table it resolves from.
	 *
	 * 🔴 IT MUST NOT DRIFT FROM THE PACKAGE. Every entry is asserted equal to
	 * `Icons::resolve()` by IconConceptsLegacyKitTest, and that same test scans
	 * the PHP call sites and fails when one writes a concept missing here. A
	 * missing entry is the whole defect; a stale value is a silent pixel
	 * change.
	 *
	 * Exactly the concepts Lite's PHP call sites write -- no more (a row nothing
	 * writes was pruned 2026-09-22, so this table stays a list of real needs),
	 * no less (IconConceptsLegacyKitTest fails on a missing one).
	 *
	 * Only the PHP path needs this. The JSX registry is bundled at build time
	 * out of Rentiva's OWN vendor copy, so a React card resolves its concepts
	 * no matter which PHP copy won.
	 *
	 * @var array<string, string>
	 */
	public const LEGACY_SUFFIX = array(
		'revenue'   => 'money-alt',
		'pending'   => 'clock',
		'active'    => 'yes-alt',
		'bookings'  => 'calendar-alt',
		'vehicles'  => 'car',
		'items'     => 'products',
		'price'     => 'money-alt',
		'completed' => 'yes',
		'occupancy' => 'chart-pie',
	);
}
