<?php
declare(strict_types=1);

namespace MHMRentiva\Core\Color\Sources;

use MHMRentiva\Core\Color\ThemeAdapters;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Source 1: the effective colour of a known theme (ThemeAdapters registry).
 */
final class AdapterSource implements AccentSource {

	private string $label = '';

	private bool $is_default = false;

	private string $adapter = '';

	public function id(): string
	{
		return 'adapter';
	}

	/** The theme's name (raw, untranslated; plan R8). */
	public function label(): string
	{
		return $this->label;
	}

	/** Whether the colour is the theme's own default; valid after resolve(). */
	public function is_default(): bool
	{
		return $this->is_default;
	}

	/** Id of the adapter that answered; valid after resolve(). */
	public function adapter(): string
	{
		return $this->adapter;
	}

	public function resolve(): ?string
	{
		$read = ThemeAdapters::read_active();
		if (null === $read) {
			return null;
		}

		$this->adapter    = $read['id'];
		$this->is_default = $read['is_default'];
		$template         = (string) get_template();
		$name             = '';
		$theme            = wp_get_theme($template);
		if ($theme->exists()) {
			$name = (string) $theme->get('Name');
		}
		$this->label = '' !== $name ? $name : $template;

		return $read['hex'];
	}
}
