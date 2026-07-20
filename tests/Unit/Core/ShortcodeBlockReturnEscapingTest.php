<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use WP_UnitTestCase;

/**
 * WP.org T4 #8 (Task B-G1g), sites 2 & 3: two RETURN sites echo a string
 * downstream without escaping AT THAT RETURN --
 *
 *  - ShortcodeServiceProvider::handle_shortcode_execution() (the generic
 *    `add_shortcode()` wrapper shared by all 27 shortcodes; `return $output
 *    ?? $buffered;`)
 *  - BlockRegistry::render_callback() (the shared Gutenberg render_callback
 *    for every dynamic block; `return sprintf('<div %s>%s</div>', ...)`)
 *
 * Neither site can safely wrap its WHOLE return in wp_kses_post(): both are
 * generic dispatchers with no knowledge of what any given
 * shortcode/renderer's markup needs to contain (SVG icons, style attrs,
 * data-* used by frontend JS -- see the documented phpcs:ignore added at
 * each return). The actual escaping happens per-field, inside each
 * shortcode's own template. This suite proves that guarantee end-to-end
 * through BOTH flagged dispatch paths using the testimonials shortcode/
 * block as the vehicle, because its template
 * (templates/shortcodes/testimonials.php) renders two genuinely
 * user-supplied text fields -- the reviewer's name and review text, both
 * sourced from booking post meta that a customer submits through the
 * public review form -- each wrapped in esc_html() at the point of output
 * (verified by reading the template: lines ~103/111/115).
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeServiceProvider::handle_shortcode_execution
 * @covers \MHMRentiva\Blocks\BlockRegistry::render_callback
 */
final class ShortcodeBlockReturnEscapingTest extends WP_UnitTestCase
{
    private const NAME_PAYLOAD   = '<script>alert(1)</script>Evil';
    private const REVIEW_PAYLOAD = 'Nice car"><img src=x onerror=alert(2)>';

    private int $vehicle_id;
    private int $booking_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Test Vehicle',
        ));

        $this->booking_id = $this->factory->post->create(array(
            'post_type'   => 'vehicle_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($this->booking_id, '_mhm_rentiva_customer_review', self::REVIEW_PAYLOAD);
        update_post_meta($this->booking_id, '_mhm_rentiva_review_approved', '1');
        update_post_meta($this->booking_id, '_mhm_rentiva_customer_rating', 5);
        update_post_meta($this->booking_id, '_mhm_rentiva_customer_name', self::NAME_PAYLOAD);
        update_post_meta($this->booking_id, '_mhm_rentiva_customer_email', 'reviewer@example.com');
        update_post_meta($this->booking_id, '_mhm_rentiva_vehicle_id', $this->vehicle_id);
    }

    /**
     * Site 2 (ShortcodeServiceProvider): dispatched via do_shortcode(), the
     * SAME public entry point every `[rentiva_testimonials]` usage in real
     * post content goes through -- not a direct Testimonials::render() call
     * that would bypass the flagged dispatcher entirely.
     */
    public function test_shortcode_dispatch_does_not_leak_injected_payload(): void
    {
        $output = do_shortcode('[rentiva_testimonials]');

        $this->assert_payload_neutralized($output);
        $this->assert_normal_markup_intact($output);
    }

    /**
     * Site 3 (BlockRegistry::render_callback): dispatched via render_block()
     * against a real parsed block array, the same path the block editor's
     * saved content goes through -- exercises get_block_wrapper_attributes()
     * (WP core, esc_attr()'s its own output) wrapping the do_shortcode()
     * output from site 2's own dispatcher.
     */
    public function test_block_render_does_not_leak_injected_payload(): void
    {
        $output = (string) render_block(array(
            'blockName'    => 'mhm-rentiva/testimonials',
            'attrs'        => array(),
            'innerBlocks'  => array(),
            'innerHTML'    => '',
            'innerContent' => array(),
        ));

        $this->assert_payload_neutralized($output);
        $this->assert_normal_markup_intact($output);

        // The block wrapper itself: get_block_wrapper_attributes() must have
        // produced a real, escaped attribute string on the outer <div>.
        $this->assertMatchesRegularExpression(
            '/^<div class="[^"]*wp-block-mhm-rentiva-testimonials[^"]*"/',
            trim($output),
            'Block wrapper must carry get_block_wrapper_attributes() output on the outer <div>.'
        );
    }

    private function assert_payload_neutralized(string $output): void
    {
        // Raw, unescaped tag constructs must never appear -- this is the
        // actual exploit shape (a live <script> element, or an attribute
        // breakout via `">` that opens a new <img onerror=...> element).
        $this->assertStringNotContainsString(
            '<script>',
            $output,
            'Injected <script> from customer_name must not survive as a live tag.'
        );
        $this->assertStringNotContainsString(
            '<img',
            $output,
            'Injected <img onerror=...> attribute-breakout from the review text must not survive as a live tag.'
        );

        // Proof the neutralization is real escaping, not silent stripping:
        // the entity-encoded forms must be present, and the payload's plain
        // text (letters, which esc_html() never touches) must still be
        // there verbatim.
        $this->assertStringContainsString(
            '&lt;script&gt;',
            $output,
            'The script payload must appear HTML-entity-escaped in the output.'
        );
        $this->assertStringContainsString(
            '&lt;img',
            $output,
            'The img breakout payload must appear HTML-entity-escaped in the output.'
        );
        $this->assertStringContainsString(
            'Evil',
            $output,
            'The payload text itself must survive (escaped, not dropped) -- proves esc_html() encoded it rather than the field being stripped.'
        );
        $this->assertStringContainsString(
            'Nice car',
            $output,
            'The review text itself must survive (escaped, not dropped).'
        );
    }

    private function assert_normal_markup_intact(string $output): void
    {
        $this->assertStringContainsString(
            'rv-testimonials',
            $output,
            'Wrapper markup from the template must render normally.'
        );
        $this->assertStringContainsString(
            'rv-author-name',
            $output,
            'Author name markup structure must be present and intact.'
        );
        $this->assertStringContainsString(
            'rv-avatar-placeholder',
            $output,
            'Initials avatar markup must be present and intact.'
        );
    }
}
