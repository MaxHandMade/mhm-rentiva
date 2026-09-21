<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Core;

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
 * 🔴 EVERY ENTRY PRESERVES THE GLYPH ITS CALL SITES ALREADY DREW. ui-core was
 * written after this plugin was finished, so its vocabulary is not a norm this
 * product bends to: where a seed concept already resolved to the icon Rentiva
 * drew, the seed was adopted; where it did not, the product's existing icon
 * became the concept's target. Adopting the vocabulary changed no pixel — that
 * was measured across fifteen strips, not assumed.
 *
 * This file must stay free of WordPress: the gate requires it outside WP.
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
	);
}
