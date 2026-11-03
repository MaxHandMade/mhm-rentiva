<?php
/**
 * Stand-ins for the Thembay theme functions ThemeAdapters reads (spec §2.1).
 * Values are the measured theme defaults (renk-ayari-tema-taramasi.md):
 * lasa custom-styles.php:84, ruza :89, zota electronics skin.
 * `tbayfake` has no default function; `tbayargs`' default needs an argument.
 *
 * Only loaded by tests/Core/Color/ThemeAdaptersTest.php; tests/ does not ship.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, Universal.Files.SeparateFunctionsFromOO

if (! function_exists('lasa_tbay_get_config')) {
	function lasa_tbay_get_config($name = '', $default = '')
	{
		return $default;
	}
}
if (! function_exists('lasa_tbay_default_theme_primary_color')) {
	function lasa_tbay_default_theme_primary_color()
	{
		return array( 'main_color' => '#CC0000' );
	}
}
if (! function_exists('zota_tbay_get_config')) {
	function zota_tbay_get_config($name = '', $default = '')
	{
		return $default;
	}
}
if (! function_exists('zota_tbay_default_theme_primary_color')) {
	function zota_tbay_default_theme_primary_color()
	{
		return array( 'main_color' => '#0D6DD7' );
	}
}
if (! function_exists('ruza_tbay_get_config')) {
	function ruza_tbay_get_config($name = '', $default = '')
	{
		return $default;
	}
}
if (! function_exists('ruza_tbay_default_theme_primary_color')) {
	function ruza_tbay_default_theme_primary_color()
	{
		return array( 'main_color' => '#feb000' );
	}
}
if (! function_exists('tbayfake_tbay_get_config')) {
	function tbayfake_tbay_get_config($name = '', $default = '')
	{
		return $default;
	}
}
if (! function_exists('tbayargs_tbay_get_config')) {
	function tbayargs_tbay_get_config($name = '', $default = '')
	{
		return $default;
	}
}
if (! function_exists('tbayargs_tbay_default_theme_primary_color')) {
	function tbayargs_tbay_default_theme_primary_color($skin)
	{
		return array( 'main_color' => '#123456' );
	}
}
