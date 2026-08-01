<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use WP_UnitTestCase;

/**
 * WP.org T4 #8 (Task B-G1g), sites 2 & 3, plus the WP.org T7 K2 follow-up
 * fix: two RETURN sites echo a string downstream --
 *
 *  - ShortcodeServiceProvider::handle_shortcode_execution() (the generic
 *    `add_shortcode()` wrapper shared by every shortcode, Lite's own and any
 *    `mhmrentiva_shortcodes` contributor's)
 *  - BlockRegistry::render_callback() (the shared Gutenberg render_callback
 *    for every dynamic block, Lite's own and any `mhmrentiva_blocks`
 *    contributor's)
 *
 * As of the T7 K2 fix both sites now wrap their WHOLE return in
 * `wp_kses($x, Html::allowed_markup())` (see ShortcodeServiceProvider.php and
 * BlockRegistry.php directly -- no `phpcs:ignore` remains at either site).
 *
 * `test_shortcode_dispatch_does_not_leak_injected_payload()` and
 * `test_block_render_does_not_leak_injected_payload()` below prove that
 * guarantee using the testimonials shortcode/block as the vehicle, because
 * its template (templates/shortcodes/testimonials.php) renders two genuinely
 * user-supplied text fields -- the reviewer's name and review text, both
 * sourced from booking post meta that a customer submits through the public
 * review form -- each wrapped in esc_html() at the point of output (verified
 * by reading the template: lines ~103/111/115).
 *
 * That is real coverage, but it is NOT a lock on the dispatcher/block-callback
 * escaping itself: testimonials.php's own per-field esc_html() calls predate
 * this fix and would neutralize the exact same payloads even with the
 * dispatcher/callback wp_kses() wrap deleted entirely. A 2026-08 independent
 * review confirmed exactly this -- deleting the wp_kses() call from either
 * site left every test in this class green. `test_dispatcher_strips_a_raw_
 * unescaped_callback_return()` and `test_block_callback_strips_a_raw_
 * unescaped_shortcode_return()` close that gap: they register a synthetic
 * shortcode (via the real `mhmrentiva_shortcodes` filter, exactly how a
 * contributor would) whose callback returns a RAW, un-escaped payload with NO
 * per-field escaping of its own, so the ONLY thing that can neutralize it is
 * the dispatcher's/callback's own wp_kses() wrap. Both were confirmed to
 * FAIL when that wrap is removed (see this task's report, "Fix round 1", for
 * the mutation-testing record) -- this is the actual lock for a category
 * where the phpcs EscapeOutput sniff cannot see a `return` statement at all
 * (it only recognizes echo/print), so no static gate substitutes for it.
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeServiceProvider::handle_shortcode_execution
 * @covers \MHMRentiva\Blocks\BlockRegistry::render_callback
 */
final class ShortcodeBlockReturnEscapingTest extends WP_UnitTestCase
{
    private const NAME_PAYLOAD   = '<script>alert(1)</script>Evil';
    private const REVIEW_PAYLOAD = 'Nice car"><img src=x onerror=alert(2)>';

    /**
     * A raw, unescaped payload with NO per-field escaping anywhere along its
     * path -- unlike the testimonials-based tests above, nothing but the
     * dispatcher's/block-callback's own wp_kses() wrap can neutralize this.
     */
    private const RAW_PAYLOAD = '<script>alert(1)</script><a onclick="evil()">click me</a>Safe Text';

    // Used by test_dispatcher_strips_a_raw_unescaped_callback_return(): registered
    // through the real `mhmrentiva_shortcodes` filter, exactly how a contributor
    // would, so it goes through ShortcodeServiceProvider::handle_shortcode_execution()
    // itself.
    private const RAW_TAG = 'mhmrentiva_test_raw_return';

    // Used by test_block_callback_strips_a_raw_unescaped_shortcode_return():
    // registered with WP core's own add_shortcode() directly, deliberately
    // bypassing ShortcodeServiceProvider entirely, so that test isolates
    // BlockRegistry::render_callback()'s OWN wp_kses() wrap -- do_shortcode()
    // inside render_callback() doesn't care how its target tag was registered,
    // and a block config's `tag` is not required to point at one of
    // ShortcodeServiceProvider's own dispatched tags. If this test instead
    // reused RAW_TAG (dispatched through ShortcodeServiceProvider), the
    // dispatcher's OWN escaping would neutralize the payload first and the
    // test would stay green even if BlockRegistry's own wp_kses() wrap were
    // deleted -- exactly the false-confidence gap this suite exists to close.
    private const RAW_BLOCK_TAG  = 'mhmrentiva_test_raw_block_return';
    private const RAW_BLOCK_NAME = 'mhm-rentiva/test-raw-return';

    private int $vehicle_id;
    private int $booking_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicle_id = $this->factory->post->create(array(
            'post_type'   => 'mhmrentiva_vehicle',
            'post_status' => 'publish',
            'post_title'  => 'Test Vehicle',
        ));

        $this->booking_id = $this->factory->post->create(array(
            'post_type'   => 'mhmrentiva_booking',
            'post_status' => 'publish',
            'post_title'  => 'Test Booking',
        ));

        update_post_meta($this->booking_id, '_mhmrentiva_customer_review', self::REVIEW_PAYLOAD);
        update_post_meta($this->booking_id, '_mhmrentiva_review_approved', '1');
        update_post_meta($this->booking_id, '_mhmrentiva_customer_rating', 5);
        update_post_meta($this->booking_id, '_mhmrentiva_customer_name', self::NAME_PAYLOAD);
        update_post_meta($this->booking_id, '_mhmrentiva_customer_email', 'reviewer@example.com');
        update_post_meta($this->booking_id, '_mhmrentiva_vehicle_id', $this->vehicle_id);
    }

    protected function tearDown(): void
    {
        remove_filter('mhmrentiva_shortcodes', array($this, 'contribute_raw_test_shortcode'));
        remove_filter('mhmrentiva_blocks', array($this, 'contribute_raw_test_block'));
        remove_shortcode(self::RAW_TAG);
        remove_shortcode(self::RAW_BLOCK_TAG);
        if (\WP_Block_Type_Registry::get_instance()->is_registered(self::RAW_BLOCK_NAME)) {
            unregister_block_type(self::RAW_BLOCK_NAME);
        }

        parent::tearDown();
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

    /**
     * THE lock on ShortcodeServiceProvider::handle_shortcode_execution()'s own
     * escaping -- see the class docblock for why the testimonials-based test
     * above is not this. A contributor's callback (registered the real way,
     * through `mhmrentiva_shortcodes`) hands back a raw payload with no
     * escaping of its own; only the dispatcher's own wp_kses() wrap stands
     * between it and the page. Confirmed by mutation testing (see this
     * task's report) to FAIL if that wrap is removed.
     */
    public function test_dispatcher_strips_a_raw_unescaped_callback_return(): void
    {
        add_filter('mhmrentiva_shortcodes', array($this, 'contribute_raw_test_shortcode'));
        \MHMRentiva\Admin\Core\ShortcodeServiceProvider::register();

        $output = do_shortcode('[' . self::RAW_TAG . ']');

        $this->assert_raw_payload_neutralized($output);
    }

    /**
     * THE lock on BlockRegistry::render_callback()'s own escaping, isolated
     * from ShortcodeServiceProvider's (see RAW_BLOCK_TAG's docblock: the
     * underlying shortcode is registered with plain add_shortcode(), NOT
     * through ShortcodeServiceProvider, specifically so the dispatcher's own
     * escaping cannot be what neutralizes this payload). Confirmed by
     * mutation testing (see this task's report) to FAIL if BlockRegistry's
     * own wp_kses() wrap is removed.
     */
    public function test_block_callback_strips_a_raw_unescaped_shortcode_return(): void
    {
        add_shortcode(self::RAW_BLOCK_TAG, array($this, 'render_raw_return_payload'));
        add_filter('mhmrentiva_blocks', array($this, 'contribute_raw_test_block'));
        register_block_type(self::RAW_BLOCK_NAME, array(
            'attributes'      => array(),
            'render_callback' => array(\MHMRentiva\Blocks\BlockRegistry::class, 'render_callback'),
        ));

        $output = (string) render_block(array(
            'blockName'    => self::RAW_BLOCK_NAME,
            'attrs'        => array(),
            'innerBlocks'  => array(),
            'innerHTML'    => '',
            'innerContent' => array(),
        ));

        $this->assert_raw_payload_neutralized($output);
    }

    /**
     * `mhmrentiva_shortcodes` filter callback for
     * test_dispatcher_strips_a_raw_unescaped_callback_return(): contributes a
     * synthetic tag exactly the way a real add-on would.
     *
     * @param array<string, array<string, array>> $registry
     * @return array<string, array<string, array>>
     */
    public function contribute_raw_test_shortcode(array $registry): array
    {
        $registry['test'] = array(
            self::RAW_TAG => array(
                'class'         => self::class,
                'method'        => 'render_raw_return_payload',
                'dependencies'  => array(),
                'requires_auth' => false,
            ),
        );

        return $registry;
    }

    /**
     * `mhmrentiva_blocks` filter callback for
     * test_block_callback_strips_a_raw_unescaped_shortcode_return(): contributes
     * a synthetic block config whose `tag` points at RAW_BLOCK_TAG (registered
     * with plain add_shortcode(), not through ShortcodeServiceProvider).
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array<string, array<string, mixed>>
     */
    public function contribute_raw_test_block(array $blocks): array
    {
        $blocks['test-raw-return'] = array(
            'tag' => self::RAW_BLOCK_TAG,
            'css' => array(),
        );

        return $blocks;
    }

    /**
     * The "malicious contributor callback": returns a raw, completely
     * unescaped payload, deliberately with no per-field escaping of its own --
     * used as both the ShortcodeServiceProvider-dispatched callback (Test A)
     * and the plain add_shortcode() callback underlying the block test
     * (Test B).
     *
     * @param array|string $atts
     * @param string|null  $content
     * @param string       $tag
     * @return string
     */
    public function render_raw_return_payload($atts, ?string $content = null, string $tag = ''): string
    {
        return self::RAW_PAYLOAD;
    }

    private function assert_raw_payload_neutralized(string $output): void
    {
        $this->assertStringNotContainsString(
            '<script>',
            $output,
            'A raw <script> from a contributor callback must not survive as a live tag.'
        );
        $this->assertStringNotContainsString(
            'onclick',
            $output,
            'A raw onclick=... attribute from a contributor callback must not survive at all.'
        );

        // Proof this is real stripping, not the whole payload vanishing:
        // the anchor's own text and the trailing plain text must survive.
        $this->assertStringContainsString(
            'click me',
            $output,
            'The anchor text itself must survive -- proves only the tag/attribute was stripped, not the whole payload.'
        );
        $this->assertStringContainsString(
            'Safe Text',
            $output,
            'Trailing plain text must survive untouched.'
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
