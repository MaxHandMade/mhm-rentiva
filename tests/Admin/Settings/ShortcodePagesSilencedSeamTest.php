<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\Settings;

use MHMRentiva\Admin\Core\ShortcodeServiceProvider;
use MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions;
use WP_UnitTestCase;

/**
 * The Shortcode Pages tool must not offer a SILENCED seam.
 *
 * THE BUG THIS PINS
 * -----------------
 * get_config() asked shortcode_exists(). When a licence closes a seam,
 * ShortcodeServiceProvider drops the entry and then deliberately re-registers the
 * tag as `__return_empty_string`, so that pages already carrying
 * [rentiva_transfer_search] render nothing rather than printing their raw source
 * text at visitors. That silencing shim is a real registration -- so
 * shortcode_exists() answers YES for exactly the tags the tool must NOT offer.
 *
 * The owner saw the result with no licence installed: rentiva_messages,
 * rentiva_transfer_search, rentiva_vendor_apply and six more, all listed "Aktif",
 * each offering to create a page that could only ever render blank.
 *
 * WHY THE SYNTHETIC SILENCE (and why the obvious test is worthless)
 * ----------------------------------------------------------------
 * ShortcodePagesLiteSeamTest already asserts these tags are not offered, and it
 * passed throughout -- because this suite runs against a Lite tree with no Pro
 * installed, where the seam classes are absent, so the tags are dropped and never
 * silenced. Silencing only happens when the class IS present but the licence is
 * not: the one configuration the Lite suite cannot reproduce, and the only one
 * where this bug exists. That is why 820 green tests missed it.
 *
 * So this test registers the shim by hand -- the same `__return_empty_string` the
 * provider registers -- reproducing a silenced seam honestly. It is the only shape
 * that fails when the fix is reverted.
 *
 * @covers \MHMRentiva\Admin\Settings\ShortcodePages\ShortcodePageActions::get_config
 */
final class ShortcodePagesSilencedSeamTest extends WP_UnitTestCase
{

    /** A tag the tool knows about, and which this Lite tree does not register. */
    private const SILENCED_TAG = 'rentiva_vendor_apply';

    protected function tearDown(): void
    {
        remove_shortcode(self::SILENCED_TAG);
        parent::tearDown();
    }

    /**
     * Guards the premise: the tag must be one the tool's own table lists, and one
     * this tree does not really register. If it were genuinely registered, the
     * assertion below would pass for the wrong reason.
     */
    public function test_premise_the_tag_is_known_to_the_tool_but_not_really_registered(): void
    {
        $this->assertArrayNotHasKey(
            self::SILENCED_TAG,
            ShortcodeServiceProvider::instance()->get_registered_shortcodes(),
            'Premise failed: this tree really registers the tag, so it is not a closed seam.'
        );
    }

    /**
     * Mutation proof: revert get_config() to `shortcode_exists($shortcode)` and
     * this fails -- the shim makes shortcode_exists() true, so the tool would offer
     * the tag again.
     */
    public function test_a_silenced_seam_is_not_offered(): void
    {
        // Exactly what ShortcodeServiceProvider::render_unlicensed_seams() does.
        add_shortcode(self::SILENCED_TAG, '__return_empty_string');

        $this->assertTrue(
            shortcode_exists(self::SILENCED_TAG),
            'Premise failed: the silencing shim did not register, so nothing is being tested.'
        );

        $this->assertArrayNotHasKey(
            self::SILENCED_TAG,
            ( new ShortcodePageActions() )->get_config(),
            'The tool offered a silenced seam. Creating that page publishes a shortcode that renders blank.'
        );
    }

    /**
     * Defence in depth: create_page() resolves against the same filtered config, so
     * a hand-crafted request naming a silenced tag cannot publish a page either.
     */
    public function test_creating_a_page_for_a_silenced_seam_is_refused(): void
    {
        add_shortcode(self::SILENCED_TAG, '__return_empty_string');

        $this->assertNull(
            ( new ShortcodePageActions() )->create_page(self::SILENCED_TAG),
            'create_page() published a page for a silenced seam.'
        );
    }

    /**
     * Positive control. A filter that dropped everything would satisfy the
     * assertions above while breaking the tool.
     */
    public function test_core_shortcodes_are_still_offered(): void
    {
        $config = ( new ShortcodePageActions() )->get_config();

        $this->assertNotSame(array(), $config, 'Premise failed: the tool offers nothing at all.');
        $this->assertArrayHasKey('rentiva_my_bookings', $config);
        $this->assertArrayHasKey('rentiva_unified_search', $config);
    }
}
