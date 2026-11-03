<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color\Sources;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * One place the site's accent colour can come from (Automatic mode).
 *
 * AccentResolver asks the sources in order (filter `mhmrentiva_accent_sources`)
 * and stops at the first concrete colour, so resolve() runs lazily.
 */
interface AccentSource {

	/** Stable id, e.g. 'adapter', 'elementor', 'theme_json'. */
	public function id(): string;

	/** Raw, untranslated label of what was read; valid after resolve(). */
	public function label(): string;

	/** Normalised '#rrggbb', or null to fall through to the next source. */
	public function resolve(): ?string;
}
