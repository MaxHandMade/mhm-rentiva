<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Frontend\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * WP.org T4 #8 (Task B-G1g): `WooCommerceIntegration::endpoint_title()` is a
 * `the_title` filter callback that returns a string WP core echoes
 * unescaped in many themes. This callback has two return shapes, and they
 * need two different fixes:
 *
 *  - PASSTHROUGH (`return $title;`): fires when this filter has nothing to
 *    do -- not an active Rentiva endpoint, or filtering a title that is not
 *    the queried page's own title. `$title` here is WP core's own value at
 *    this point in the global `the_title` filter chain, which runs for
 *    EVERY post/page title on the entire site, not only Rentiva account
 *    pages. Escaping it here would double-encode entities core's own
 *    `wptexturize()`/`convert_chars()` filters already produced (e.g.
 *    `&#8217;` -> `&amp;#8217;`), visibly corrupting titles site-wide, and
 *    would strip any intended markup a later/earlier filter (ours runs at
 *    default priority 10) legitimately added. So passthrough is
 *    deliberately left un-escaped -- proven unmodified below.
 *
 *  - REPLACEMENT (`return esc_html($rentiva_map[$active_key]['label']);`):
 *    fires only when this IS the active Rentiva endpoint's own queried
 *    title -- the one value this callback actually introduces. Escaped at
 *    the return per WP.org's "escape as late as possible" guidance.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration::endpoint_title
 */
final class WooCommerceIntegrationEndpointTitleTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WooCommerceIntegration::clear_slug_cache();
    }

    protected function tearDown(): void
    {
        global $wp_query;
        $wp_query->query_vars        = array();
        $wp_query->in_the_loop       = false;
        $wp_query->queried_object    = null;
        $wp_query->queried_object_id = null;

        parent::tearDown();
    }

    /**
     * The RED this closes: the label map is built fresh on every call via
     * `__('Vehicle Bookings', 'mhm-rentiva')` (see
     * EndpointHelperTrait::get_rentiva_endpoints_map()), so the `gettext`
     * filter is the one realistic way to make a payload reach this
     * callback's own return value -- nothing here is direct end-user input,
     * but a compromised/malicious translation string is a real WP i18n
     * attack surface, and WP.org's ask is "escape at the return" regardless
     * of how likely the input is to be hostile today.
     */
    public function test_replaced_label_escapes_an_injected_translation_payload(): void
    {
        $payload = '<script>alert(1)</script>';

        add_filter(
            'gettext',
            static function ($translated, $text, $domain) use ($payload) {
                if ('mhm-rentiva' === $domain && 'Vehicle Bookings' === $text) {
                    return $payload;
                }
                return $translated;
            },
            10,
            3
        );

        $result = $this->render_matched_endpoint_title('Original Title');

        $this->assertStringNotContainsString(
            '<script>',
            $result,
            'An injected payload in the replaced label must not survive unescaped.'
        );
        $this->assertStringContainsString(
            '&lt;script&gt;',
            $result,
            'The payload must come back HTML-entity-escaped.'
        );
    }

    /**
     * Normal-render-intact proof for the REPLACEMENT branch: a legitimate,
     * un-tampered label (no markup, the real shipped translation) must
     * render byte-for-byte the same as before this fix -- esc_html() must
     * be a no-op on plain translated text.
     */
    public function test_replaced_label_is_unchanged_for_a_normal_render(): void
    {
        $result = $this->render_matched_endpoint_title('Original Title');

        $this->assertSame('Vehicle Bookings', $result);
    }

    /**
     * Normal-render-intact proof for the PASSTHROUGH branch: on a page that
     * is NOT an active Rentiva endpoint, this filter must return whatever
     * title WordPress (or an earlier filter) handed it, byte-for-byte --
     * including any markup already legitimately present -- because this
     * filter has no business touching titles it did not generate. A title
     * containing `<script>` here stands in for "content this filter cannot
     * know is safe or not": proving it survives unmodified confirms we do
     * NOT double-escape (or strip) arbitrary site-wide titles, which is the
     * corruption risk this callback must avoid.
     */
    public function test_passthrough_title_is_returned_unmodified_when_not_the_active_endpoint(): void
    {
        global $wp_query;
        $wp_query->query_vars        = array();
        $wp_query->in_the_loop       = true;
        $wp_query->queried_object_id = 999;

        $title  = 'A <script>alert(1)</script> Title';
        $result = WooCommerceIntegration::endpoint_title($title, 999);

        $this->assertSame(
            $title,
            $result,
            'Passthrough titles must not be modified/escaped by this filter.'
        );
    }

    /**
     * Same passthrough guarantee for the "queried id mismatch" branch: an
     * active Rentiva endpoint slug IS set, but the title being filtered
     * belongs to nested content (e.g. a vehicle title shown inside the My
     * Bookings page), not the queried My Account page itself.
     */
    public function test_passthrough_title_is_returned_unmodified_for_non_queried_id(): void
    {
        global $wp_query;
        $slug = WooCommerceIntegration::get_endpoint_slug('bookings');

        $wp_query->query_vars[ $slug ] = '';
        $wp_query->in_the_loop         = true;
        // WP_Query::get_queried_object_id() re-derives queried_object_id via
        // get_queried_object() unless queried_object is already set -- set
        // both so the mismatch below is genuinely 999 vs. 123, not silently
        // collapsed to WP_Query's own "not yet resolved" 0 fallback.
        $wp_query->queried_object    = (object) array( 'ID' => 999 );
        $wp_query->queried_object_id = 999;

        $title = 'A <b>Nested</b> Vehicle Title';
        // $id (123) does not match queried_object_id (999) -> passthrough.
        $result = WooCommerceIntegration::endpoint_title($title, 123);

        $this->assertSame($title, $result);
    }

    private function render_matched_endpoint_title(string $original_title): string
    {
        global $wp_query;

        $slug = WooCommerceIntegration::get_endpoint_slug('bookings');

        $wp_query->query_vars[ $slug ] = '';
        $wp_query->in_the_loop         = true;
        // See comment in test_passthrough_title_is_returned_unmodified_for_non_queried_id()
        // -- queried_object must be set too, or WP_Query re-derives (and
        // resets) queried_object_id out from under us.
        $wp_query->queried_object    = (object) array( 'ID' => 4242 );
        $wp_query->queried_object_id = 4242;

        return WooCommerceIntegration::endpoint_title($original_title, 4242);
    }
}
