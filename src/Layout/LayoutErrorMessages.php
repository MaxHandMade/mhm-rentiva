<?php
declare(strict_types=1);

namespace MHMRentiva\Layout;

if (! defined('ABSPATH')) {
    exit;
}

use WP_Error;

/**
 * Layout Error Messages
 *
 * The mhm/ui-core package owns no plugin slug, so it owns no text domain --
 * and WordPress's string extractor reads code without executing it, so a domain
 * passed in as a variable would silently drop the string from every .pot.
 * The package's Layout engine therefore returns WP_Error( code, '', $data )
 * with NO human text: $data is the only information an error carries, and
 * this class rebuilds the sentence here, in this plugin's own domain.
 *
 * Each branch below is written against the real payload the corresponding
 * `new WP_Error(...)` site in mhm-ui-core actually constructs (verified by
 * reading src/Layout/BlueprintValidator.php and CompositionBuilder.php at
 * 86e48c3, not assumed): it must never read a field that shape does not
 * carry, and it must never paper over an omitted field with an empty
 * placeholder -- printing a sentence the data never said is a defect, not a
 * cosmetic detail.
 *
 * @package MHMRentiva\Layout
 */
final class LayoutErrorMessages {

    /**
     * Every WP_Error code (with this plugin's "mhmrentiva_" prefix) this
     * class renders a sentence for. A code the package raises but this list
     * omits falls through to the fallback in render() instead of a made-up
     * sentence.
     *
     * A later task ties this to the package's ErrorCodes::ALL so a rename on
     * the package side cannot silently fall through unnoticed; for now it is
     * a literal list.
     *
     * @var list<string>
     */
    public const HANDLED = [
        'mhmrentiva_invalid_blueprint',
        'mhmrentiva_unsupported_version',
        'mhmrentiva_forbidden_pattern',
        'mhmrentiva_no_pages',
        'mhmrentiva_invalid_components',
        'mhmrentiva_invalid_page',
        'mhmrentiva_invalid_instance',
        'mhmrentiva_unknown_component',
        'mhmrentiva_missing_adapter',
        'mhmrentiva_tailwind_leakage',
        'mhmrentiva_utility_leakage',
    ];

    /**
     * Renders the human sentence for a Layout WP_Error, in this plugin's own
     * text domain.
     *
     * Two of the eleven codes carry two distinct $data shapes each (see the
     * class docblock): invalid_page's per-page key check adds a 'key' field
     * that its root-structure check does not carry, and invalid_instance's
     * two raise sites carry either instance_index+page_index or instance_id,
     * never both. Each branch below checks which fields are actually present
     * before reading them, rather than assuming one shape.
     *
     * @param WP_Error $error A Layout engine error (code "mhmrentiva_<suffix>").
     */
    public static function render( WP_Error $error ): string {
        $code = (string) $error->get_error_code();
        $data = $error->get_error_data();
        $data = is_array( $data ) ? $data : [];

        switch ( $code ) {
            case 'mhmrentiva_invalid_blueprint':
                return sprintf(
                    /* translators: %s: the manifest's missing root key. */
                    __( 'Manifest root key missing: %s', 'mhm-rentiva' ),
                    self::stringify( $data['key'] )
                );

            case 'mhmrentiva_unsupported_version':
                return sprintf(
                    /* translators: %s: the manifest's declared version string. */
                    __( 'Unsupported blueprint version: %s', 'mhm-rentiva' ),
                    self::stringify( $data['version'] )
                );

            case 'mhmrentiva_forbidden_pattern':
                return sprintf(
                    /* translators: %s: the forbidden pattern found in the manifest. */
                    __( 'Forbidden pattern detected in manifest: %s', 'mhm-rentiva' ),
                    self::stringify( $data['pattern'] )
                );

            case 'mhmrentiva_no_pages':
                return __( 'Manifest contains no pages.', 'mhm-rentiva' );

            case 'mhmrentiva_invalid_components':
                return __( 'Manifest components section must be an object/array.', 'mhm-rentiva' );

            case 'mhmrentiva_invalid_page':
                // BlueprintValidator::validate()'s root-structure page check
                // (a non-array page entry) carries only page_index; its
                // validate_page() per-page key check adds 'key'. Branch on
                // which shape actually arrived -- do not default 'key' to ''
                // for the shape that never carries it, or the sentence below
                // silently prints "is missing key: " for a page that was
                // never checked against a required key at all.
                if ( array_key_exists( 'key', $data ) ) {
                    return sprintf(
                        /* translators: 1: page index in the manifest. 2: the page's missing key. */
                        __( 'Page #%1$d is missing key: %2$s', 'mhm-rentiva' ),
                        (int) $data['page_index'],
                        self::stringify( $data['key'] )
                    );
                }

                return sprintf(
                    /* translators: %d: page index in the manifest. */
                    __( 'Page #%d is not a valid page object', 'mhm-rentiva' ),
                    (int) $data['page_index']
                );

            case 'mhmrentiva_invalid_instance':
                // BlueprintValidator::validate_page()'s composition check
                // carries instance_index+page_index (instance_id is absent
                // entirely -- that is what triggered this code). CompositionBuilder
                // ::build() instead carries instance_id itself: present, but not a
                // string, which is a different fact and needs a different sentence.
                if ( array_key_exists( 'instance_id', $data ) ) {
                    return sprintf(
                        /* translators: %s: the instance_id value, which is not a string. */
                        __( 'Component instance has a non-string instance_id: %s', 'mhm-rentiva' ),
                        self::stringify( $data['instance_id'] )
                    );
                }

                return sprintf(
                    /* translators: 1: the component instance's index within its page. 2: the page index. */
                    __( 'Component instance #%1$d in page #%2$d missing instance_id', 'mhm-rentiva' ),
                    (int) $data['instance_index'],
                    (int) $data['page_index']
                );

            case 'mhmrentiva_unknown_component':
                return sprintf(
                    /* translators: %s: the unrecognised component reference. */
                    __( 'Unknown component reference: %s', 'mhm-rentiva' ),
                    self::stringify( $data['component_id'] )
                );

            case 'mhmrentiva_missing_adapter':
                return sprintf(
                    /* translators: %s: the component type with no registered adapter. */
                    __( 'No adapter found for component type: %s', 'mhm-rentiva' ),
                    self::stringify( $data['type'] )
                );

            case 'mhmrentiva_tailwind_leakage':
                return sprintf(
                    /* translators: %s: the forbidden framework pattern found in rendered markup. */
                    __( 'Tailwind leakage detected in rendered markup: %s', 'mhm-rentiva' ),
                    self::stringify( $data['pattern'] )
                );

            case 'mhmrentiva_utility_leakage':
                return sprintf(
                    /* translators: %s: the unprefixed utility class fragment. */
                    __( 'Unprefixed utility class detected: %s', 'mhm-rentiva' ),
                    self::stringify( $data['fragment'] )
                );

            default:
                // A code the package raises but this class does not (yet) know --
                // e.g. a future ErrorCodes addition, or a rename that Task B7's
                // HANDLED/ErrorCodes::ALL parity test exists to catch. Name the
                // code rather than inventing a sentence it never earned.
                return sprintf(
                    /* translators: %s: the unrecognised WP_Error code. */
                    __( 'Unhandled layout error: %s', 'mhm-rentiva' ),
                    $code
                );
        }
    }

    /**
     * Renders any $data value as display text.
     *
     * Most fields above are guaranteed strings by their shape, but at least
     * one is deliberately not: invalid_instance's instance_id-present shape
     * exists BECAUSE that value is not a string (int, float, bool, array,
     * ...). A bare (string) cast would emit a PHP "Array to string
     * conversion" notice for an array value; this renders something a human
     * can read instead, for any type $data might carry.
     *
     * @param mixed $value The raw $data value.
     */
    private static function stringify( mixed $value ): string {
        if ( is_string( $value ) ) {
            return $value;
        }

        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        if ( null === $value ) {
            return 'null';
        }

        $encoded = wp_json_encode( $value );

        return is_string( $encoded ) ? $encoded : '';
    }
}
