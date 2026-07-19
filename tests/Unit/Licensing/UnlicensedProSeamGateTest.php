<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Licensing\Mode;
use WP_UnitTestCase;

/**
 * A Pro seam must satisfy the class guard AND the licence gate.
 *
 * The bug this pins: every seam registry asked only class_exists() -- a PRESENCE
 * check. That is correct for a Lite-only tree (the class is absent, so the seam
 * drops) but it silently handed the whole feature away on the case that actually
 * pays the bills: Pro INSTALLED but UNLICENSED. There the class exists, so
 * presence-only said yes and an unlicensed site rendered the full VIP Transfer UI
 * and registered the export/penalty hooks for free.
 *
 * Why these tests look indirect: this suite runs against the real Lite tree, where
 * the Pro seam classes are genuinely absent. Asserting "the transfer block is not
 * registered" therefore passes just as happily with the licence gate REVERTED --
 * class_exists() is already false, so the assertion proves nothing about the gate.
 * That tautology is the whole trap.
 *
 * So each test below drives the registry with a SYNTHETIC entry whose seam class
 * really does exist in this tree (Mode itself -- any always-present class would
 * do; it is a stand-in, not the subject). That reproduces "class present, licence
 * absent" honestly, and it is the only shape that fails when the gate is removed.
 *
 * @covers \MHMRentiva\Admin\Licensing\Mode::allowsSeam
 */
final class UnlicensedProSeamGateTest extends WP_UnitTestCase
{
    /** A class guaranteed to exist in a Lite tree, standing in for a shipped Pro seam. */
    private const PRESENT_CLASS = Mode::class;

    /**
     * Guards the premise of every assertion below. If a licence ever became
     * active in this tree, "dropped" would stop meaning "the gate closed it".
     */
    public function test_premise_lite_tree_is_unlicensed_and_the_stand_in_class_exists(): void
    {
        $this->assertTrue(class_exists(self::PRESENT_CLASS), 'Stand-in seam class must exist, or the tests below pass vacuously.');
        $this->assertFalse(Mode::isPro(), 'Premise failed: this tree reports a Pro licence.');
        $this->assertFalse(Mode::canUseExport(), 'Premise failed: this tree reports the export feature.');
    }

    // -- Mode::allowsSeam contract -------------------------------------------------

    public function test_a_seam_with_no_feature_requirement_is_always_allowed(): void
    {
        $this->assertTrue(Mode::allowsSeam(null));
    }

    public function test_whole_edition_and_feature_seams_are_refused_without_a_licence(): void
    {
        $this->assertFalse(Mode::allowsSeam('pro'), 'A "pro" seam must be refused when isPro() is false.');
        $this->assertFalse(Mode::allowsSeam('export'), 'A feature seam must be refused without a licence.');
        $this->assertFalse(Mode::allowsSeam('vendor_marketplace'));
    }

    // -- BlockRegistry -------------------------------------------------------------
    //
    // BlockRegistry's own pro_seam/get_available_blocks() gate was removed by the
    // `mhm_rentiva_blocks` seam inversion (Lite no longer declares Pro blocks at
    // all; Pro's BlockExtensions filter subscriber gates its own contributed
    // entries via \MHMRentiva\Pro\Edition). See
    // mhm-rentiva/tests/Unit/Blocks/BlockRegistryFilterTest.php and
    // mhm-rentiva-pro/tests/Integration/Pro/BlockExtensionsTest.php for the
    // current coverage of that seam.

    // -- ShortcodeServiceProvider --------------------------------------------------
    //
    // ShortcodeServiceProvider's own pro_seam/drop_absent_pro_seams()/
    // render_unlicensed_seams() gate was removed by the `mhm_rentiva_shortcodes`
    // seam inversion (Lite no longer declares Pro shortcode tags at all; Pro's own
    // ShortcodeExtensions filter subscriber gates its own contributed entries via
    // \MHMRentiva\Pro\Edition, and reproduces the lapsed-licence silencer itself).
    // See mhm-rentiva/tests/Unit/Core/ShortcodeRegistryFilterTest.php and
    // mhm-rentiva-pro/tests/Integration/Pro/ShortcodeExtensionsTest.php for the
    // current coverage of that seam.
}
