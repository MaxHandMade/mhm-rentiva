<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color\Sources;

use MHMRentiva\Core\Color\ElementorGlobals;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Source 2: Elementor Site Settings global colour, Accent first, then Primary;
 * Elementor's untouched factory values are skipped (spec §2 item 2, plan R3).
 */
final class ElementorKitSource implements AccentSource {

	/** Stored reference => Elementor factory value. */
	private const REFS = array(
		'accent'  => array( 'globals/colors?id=accent', '#61ce70' ),
		'primary' => array( 'globals/colors?id=primary', '#6ec1e4' ),
	);

	private string $label = '';

	public function id(): string
	{
		return 'elementor';
	}

	/** 'accent' or 'primary' (raw; plan R8). */
	public function label(): string
	{
		return $this->label;
	}

	public function resolve(): ?string
	{
		foreach (self::REFS as $label => list( $stored, $factory )) {
			$item = ElementorGlobals::resolve($stored);
			if (null !== $item && $factory !== $item['hex']) {
				$this->label = $label;
				return $item['hex'];
			}
		}
		return null;
	}
}
