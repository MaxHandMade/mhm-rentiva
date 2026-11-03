<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Admin\Settings\Core\SettingsCore;
use MHMRentiva\Admin\Settings\Settings;
use MHMRentiva\Core\Color\AccentResolver;
use MHMRentiva\Core\Color\ColorMath;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings > Frontend > Colors: the accent mode, the color picker, the source
 * line, the two readability notices, the page cache note and a small inert
 * .rvs-shell preview (spec §8).
 *
 * PHP decides, JavaScript only previews: every sentence is translated here and
 * printed into the markup (variable ones as a `data-template` holding `%s`), so
 * the section is complete without JavaScript.
 *
 * @since 6.4.0
 */
final class FrontendColorsSection {

	/** Preview stylesheets; they pull in mhm-rentiva-rvs-tokens and its inline palette rule. */
	public const PREVIEW_STYLES = array( 'mhm-rentiva-rvs-primitives', 'mhm-rentiva-rvs-calendar', 'mhm-rentiva-rvs-data' );

	/**
	 * The field of the Colors section (add_settings_field callback).
	 */
	public static function render_fields(): void {
		// A hand-written option row may hold anything: only the exact 'custom' counts.
		$mode      = 'custom' === SettingsCore::get( AccentResolver::MODE_KEY, 'auto' ) ? 'custom' : 'auto';
		$color     = ColorMath::normalize( SettingsCore::get( AccentResolver::COLOR_KEY, '' ) ) ?? '';
		$automatic = AccentResolver::automatic();
		$resolved  = AccentResolver::resolve();
		$custom    = array(
			'source'     => 'custom',
			'label'      => '',
			'is_default' => false,
		);
		$modes     = array(
			'auto'   => __( "Automatic — use the site's color", 'mhm-rentiva' ),
			'custom' => __( 'Custom color', 'mhm-rentiva' ),
		);

		// The developer filter also runs in Custom mode and may replace the picked
		// colour. JavaScript cannot know that, so PHP measures it once for the saved
		// custom colour (else the automatic one) and hands the result over. Limit: a
		// filter that answers differently per colour is previewed only for the saved
		// colour; what it does to a newly typed one shows after saving.
		$candidate = '' !== $color ? $color : $automatic['hex'];
		$probe     = ColorMath::normalize( apply_filters( 'mhmrentiva_accent_color', $candidate, 'custom' ) );
		$filtered  = null !== $probe && $probe !== $candidate;

		echo '<div class="mhm-rentiva-accent" data-auto-hex="' . esc_attr( $automatic['hex'] ) . '" data-auto-source="' . esc_attr( self::source_label( $automatic ) ) . '" data-custom-source="' . esc_attr( self::source_label( $custom ) ) . '"';
		if ( $filtered ) {
			$by_filter = array(
				'source'     => 'filter',
				'label'      => '',
				'is_default' => false,
			);
			echo ' data-custom-filter-hex="' . esc_attr( (string) $probe ) . '" data-custom-filter-source="' . esc_attr( self::source_label( $by_filter ) ) . '"';
		}
		echo '>';

		echo '<fieldset class="mhm-rentiva-accent-mode">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Accent color mode', 'mhm-rentiva' ) . '</legend>';
		foreach ( $modes as $value => $label ) {
			echo '<label><input type="radio" name="mhmrentiva_settings[' . esc_attr( AccentResolver::MODE_KEY ) . ']" value="' . esc_attr( $value ) . '" ' . checked( $mode, $value, false ) . ' /> ' . esc_html( $label ) . '</label><br />';
		}
		echo '</fieldset>';

		echo '<p><label for="mhm-rentiva-accent-color" class="screen-reader-text">' . esc_html__( 'Custom accent color', 'mhm-rentiva' ) . '</label>';
		echo '<input type="text" name="mhmrentiva_settings[' . esc_attr( AccentResolver::COLOR_KEY ) . ']" class="mhm-rentiva-accent-color" data-default-color="' . esc_attr( ColorMath::DEFAULT_ACCENT ) . '" value="' . esc_attr( $color ) . '" id="mhm-rentiva-accent-color" /></p>';

		/* translators: %s: where the accent color comes from, e.g. "theme setting (Astra)" */
		$source_template = __( 'Source: %s', 'mhm-rentiva' );
		echo '<p class="mhm-rentiva-accent-source" aria-live="polite" data-template="' . esc_attr( $source_template ) . '">';
		echo '<span class="mhm-rentiva-accent-swatch" aria-hidden="true" style="background-color:' . esc_attr( $resolved['hex'] ) . '"></span> ';
		echo '<span class="mhm-rentiva-accent-source-text">' . esc_html( sprintf( $source_template, self::source_label( $resolved ) ) ) . '</span> ';
		echo '<code class="mhm-rentiva-accent-hex">' . esc_html( $resolved['hex'] ) . '</code>';
		echo '</p>';

		/* translators: %s: hex color */
		$text_template = __( 'This color is too light for text on the page. Links and text use a darker shade (%s) so they stay readable.', 'mhm-rentiva' );
		echo '<div class="mhm-rentiva-accent-notices" aria-live="polite">';
		echo '<p class="notice notice-warning inline mhm-rentiva-accent-notice--text" data-template="' . esc_attr( $text_template ) . '"' . ( $resolved['flags']['text_darkened'] ? '' : ' hidden' ) . '>' . esc_html( sprintf( $text_template, $resolved['tokens']['accent-text'] ) ) . '</p>';
		echo '<p class="notice notice-warning inline mhm-rentiva-accent-notice--fill"' . ( $resolved['flags']['fill_low'] ? '' : ' hidden' ) . '>' . esc_html__( 'This light color is hard to tell from a white background.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		echo '<p class="description">' . esc_html__( 'If you use a page cache plugin, clear it after changing this.', 'mhm-rentiva' ) . '</p>';

		self::render_preview();

		echo '</div>';
	}

	/**
	 * Small inert .rvs-shell sample (plan R9): primary button, link, three
	 * calendar days (selected, today, plain) and a "Confirmed" tag. It wears the
	 * saved palette from the rvs token sheet; JavaScript repaints it on change.
	 */
	private static function render_preview(): void {
		echo '<p class="mhm-rentiva-accent-preview-title"><strong>' . esc_html__( 'Preview', 'mhm-rentiva' ) . '</strong></p>';
		echo '<div class="rvs-shell mhm-rentiva-accent-preview" inert aria-hidden="true">';
		echo '<p><span class="rvs-btn rvs-btn--primary">' . esc_html__( 'Book Now', 'mhm-rentiva' ) . '</span> ';
		echo '<a href="#">' . esc_html__( 'View Details', 'mhm-rentiva' ) . '</a></p>';
		echo '<div class="rvs-cal"><table class="rvs-cal__grid" role="presentation"><tbody><tr>';
		echo '<td class="rvs-cal__day is-selected"><span class="rvs-cal__num">' . esc_html( number_format_i18n( 12 ) ) . '</span></td>';
		echo '<td class="rvs-cal__day is-today is-available"><span class="rvs-cal__num">' . esc_html( number_format_i18n( 13 ) ) . '</span></td>';
		echo '<td class="rvs-cal__day is-available"><span class="rvs-cal__num">' . esc_html( number_format_i18n( 14 ) ) . '</span></td>';
		echo '</tr></tbody></table></div>';
		echo '<p><span class="rvs-status rvs-status--confirmed">' . esc_html__( 'Confirmed', 'mhm-rentiva' ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * Where the colour comes from, as the label of "Source: %s" (plan R10).
	 * Translated plain text, not escaped: the caller escapes.
	 *
	 * @param array{source:string,label:string,is_default:bool} $resolved
	 */
	public static function source_label( array $resolved ): string {
		$label = $resolved['label'];

		switch ( $resolved['source'] ) {
			case 'adapter':
				return $resolved['is_default']
					/* translators: %s: theme name */
					? sprintf( __( 'theme default (%s)', 'mhm-rentiva' ), $label )
					/* translators: %s: theme name */
					: sprintf( __( 'theme setting (%s)', 'mhm-rentiva' ), $label );

			case 'elementor':
				if ( 'accent' === $label ) {
					$label = _x( 'Accent', 'Elementor global color', 'mhm-rentiva' );
				} elseif ( 'primary' === $label ) {
					$label = _x( 'Primary', 'Elementor global color', 'mhm-rentiva' );
				}
				/* translators: %s: Elementor global color name, e.g. "Accent" */
				return sprintf( __( 'Elementor global color (%s)', 'mhm-rentiva' ), $label );

			case 'theme_json':
				if ( 'button' === $label ) {
					return __( "the theme's button color", 'mhm-rentiva' );
				}
				/* translators: %s: theme.json palette slug, e.g. "primary" */
				return sprintf( __( 'theme color palette (theme.json: %s)', 'mhm-rentiva' ), $label );

			case 'default':
				return __( 'Rentiva default', 'mhm-rentiva' );

			case 'custom':
				return __( 'Custom color', 'mhm-rentiva' );

			case 'filter':
				/* translators: %s: filter hook name */
				return sprintf( __( 'developer filter (%s)', 'mhm-rentiva' ), 'mhmrentiva_accent_color' );
		}

		// A source added through mhmrentiva_accent_sources: its own label, else its id.
		return '' !== $label ? $label : $resolved['source'];
	}

	/**
	 * Color picker and preview assets, on the Frontend tab only. Called by
	 * SettingsCore::enqueue_assets(), which has already checked the screen.
	 */
	public static function enqueue_assets(): void {
		self::register_picker_scripts();

		// The screen's own tab reading (read-only routing), not a second $_GET read.
		if ( 'frontend' !== Settings::current_tab() ) {
			return;
		}

		self::enqueue_picker_assets();
	}

	/**
	 * Color picker and preview assets wherever the field is rendered: the
	 * Frontend tab and the setup wizard's step 4. The caller checks the screen.
	 */
	public static function enqueue_picker_assets(): void {
		self::register_picker_scripts();

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'mhm-rentiva-accent-palette' );
		wp_enqueue_script( 'mhm-rentiva-settings-accent-color' );
		foreach ( self::PREVIEW_STYLES as $handle ) {
			wp_enqueue_style( $handle );
		}
	}

	private static function register_picker_scripts(): void {
		wp_register_script(
			'mhm-rentiva-accent-palette',
			AssetManager::get_asset_url( 'assets/js/admin/accent-palette.js' ),
			array(),
			\MHMRENTIVA_VERSION,
			true
		);
		wp_register_script(
			'mhm-rentiva-settings-accent-color',
			AssetManager::get_asset_url( 'assets/js/admin/settings-accent-color.js' ),
			array( 'jquery', 'wp-color-picker', 'mhm-rentiva-accent-palette' ),
			\MHMRENTIVA_VERSION,
			true
		);
	}
}
