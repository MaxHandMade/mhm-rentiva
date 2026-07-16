<?php

namespace MHMRentiva\Tests\Integration;

use WP_UnitTestCase;

/**
 * The bundled webfont must actually reach the browser.
 *
 * readme.txt promises "Every asset it loads, including its webfont, is served
 * from your own site". That claim is only true if `mhm-rentiva-fonts` is really
 * printed -- and it is never enqueued directly. It rides in as a dependency of
 * `mhm-css-variables`, which makes it uniquely easy to lose SILENTLY:
 *
 *   wp_enqueue_style('mhm-css-variables', $src, array('mhm-rentiva-fonts'), $v);
 *
 * ...does NOT attach that dependency if the handle is already registered.
 * WP_Dependencies::add() returns false for a known handle and drops $src, $deps
 * and $ver on the floor without a notice. BlockRegistry::register_blocks()
 * used to register `mhm-css-variables` with EMPTY deps on `init` -- which
 * always beats wp_enqueue_scripts -- so the font dependency evaporated and the
 * front end silently fell back to a system font.
 *
 * That regression was invisible on a dev site running Elementor, because
 * Elementor loads Plus Jakarta Sans from Google Fonts itself and masks the
 * missing local face. Hence these assertions look at the dependency graph and
 * the print queue, never at rendered typography.
 *
 * @package MHMRentiva\Tests\Integration
 */
class BundledFontEnqueueTest extends WP_UnitTestCase
{

    /**
     * Guards the premise of every assertion below: if the woff2/CSS payload were
     * missing, the plugin would be registering a 404 and the queue assertions
     * would still pass.
     */
    public function test_the_bundled_font_payload_actually_ships(): void
    {
        $dir = dirname(__DIR__, 2) . '/assets/vendor/fonts/';

        $this->assertFileExists($dir . 'plus-jakarta-sans.css');
        $this->assertFileExists($dir . 'plus-jakarta-sans-latin.woff2');
        $this->assertFileExists($dir . 'plus-jakarta-sans-latin-ext.woff2');
    }

    /**
     * The font face must be defined locally, not fetched from a CDN. WordPress.org
     * forbids remote assets, and a Google Fonts URL would leak every visitor's IP.
     *
     * Scans the url() references rather than the raw file text on purpose: the
     * file's header comment legitimately *names* fonts.googleapis.com to explain
     * what it replaced, and a naive substring scan would fail on that prose while
     * telling us nothing about what the browser actually fetches.
     */
    public function test_the_font_stylesheet_fetches_nothing_remote(): void
    {
        $css = (string) file_get_contents(
            dirname(__DIR__, 2) . '/assets/vendor/fonts/plus-jakarta-sans.css'
        );

        $this->assertStringContainsString('@font-face', $css);

        preg_match_all('/url\(\s*[\'"]?([^\'")]+)/i', $css, $matches);

        $this->assertNotSame(array(), $matches[1], 'Premise failed: the stylesheet references no font files.');

        foreach ($matches[1] as $ref) {
            $this->assertDoesNotMatchRegularExpression(
                '#^(https?:)?//#i',
                trim($ref),
                sprintf('Font reference "%s" is remote; every asset must be served from the user\'s own site.', $ref)
            );
        }
    }

    /**
     * The core of the regression: whoever registered `mhm-css-variables` first,
     * the surviving registration must still carry the font dependency.
     *
     * Asserts against the registry as the plugin's own `init` hooks left it --
     * including BlockRegistry::register_blocks(), the callback that used to clobber
     * this. Re-registering here would test a fresh call rather than the real boot.
     */
    public function test_css_variables_keeps_the_font_dependency_after_init(): void
    {
        $styles = wp_styles();

        $this->assertTrue(
            wp_style_is('mhm-css-variables', 'registered'),
            'Premise failed: mhm-css-variables is not registered at all.'
        );

        $this->assertContains(
            'mhm-rentiva-fonts',
            $styles->registered['mhm-css-variables']->deps,
            'mhm-css-variables lost its bundled-font dependency -- something re-registered '
            . 'the handle with different deps and WP discarded them silently.'
        );
    }

    /**
     * The font handle has to exist, or the dependency above would be dangling and
     * WP_Styles would drop mhm-css-variables entirely at print time.
     */
    public function test_the_font_handle_is_registered_and_points_at_the_bundled_css(): void
    {
        $this->assertTrue(wp_style_is('mhm-rentiva-fonts', 'registered'));

        $src = (string) wp_styles()->registered['mhm-rentiva-fonts']->src;

        $this->assertStringContainsString('assets/vendor/fonts/plus-jakarta-sans.css', $src);
        $this->assertStringNotContainsString('fonts.googleapis.com', $src);
    }

    /**
     * The end-user-visible outcome, through the real hook: after wp_enqueue_scripts
     * fires on a page that loads plugin assets, the font resolves into the list of
     * stylesheets WordPress is about to print.
     *
     * `wp_style_is('mhm-rentiva-fonts', 'enqueued')` would be the wrong probe and
     * would fail even when the font loads correctly: a dependency is never added to
     * the queue, it is pulled into `to_do` when all_deps() expands the queue. That
     * expanded list is what actually gets printed, so that is what is asserted.
     */
    public function test_the_font_lands_in_the_print_queue_on_wp_enqueue_scripts(): void
    {
        add_filter('mhm_rentiva_force_load_assets', '__return_true');

        do_action('wp_enqueue_scripts');

        $styles = wp_styles();

        $this->assertContains(
            'mhm-css-variables',
            $styles->queue,
            'Premise failed: core CSS did not enqueue, so the font check below would be vacuous.'
        );

        $styles->all_deps($styles->queue);

        $this->assertContains(
            'mhm-rentiva-fonts',
            $styles->to_do,
            'The bundled webfont never reaches the page: it is not in the resolved print queue. '
            . 'readme.txt claims the webfont is served from the user\'s own site.'
        );

        remove_filter('mhm_rentiva_force_load_assets', '__return_true');
    }

    /**
     * The font must precede the stylesheet that consumes it, so the face is defined
     * before --mhm-font-display is used.
     */
    public function test_the_font_is_printed_before_css_variables(): void
    {
        add_filter('mhm_rentiva_force_load_assets', '__return_true');

        do_action('wp_enqueue_scripts');

        $styles = wp_styles();
        $styles->all_deps($styles->queue);

        $order = array_values($styles->to_do);

        $this->assertLessThan(
            array_search('mhm-css-variables', $order, true),
            array_search('mhm-rentiva-fonts', $order, true),
            'The webfont must be printed before the stylesheet that references it.'
        );

        remove_filter('mhm_rentiva_force_load_assets', '__return_true');
    }
}
