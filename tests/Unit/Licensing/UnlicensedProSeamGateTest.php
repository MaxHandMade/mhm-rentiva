<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Core\ShortcodeServiceProvider;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Blocks\BlockRegistry;
use ReflectionClass;
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
 * @covers \MHMRentiva\Blocks\BlockRegistry::get_available_blocks
 * @covers \MHMRentiva\Admin\Core\ShortcodeServiceProvider::drop_absent_pro_seams
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

    /**
     * Mutation proof: revert BlockRegistry::get_available_blocks() to
     * `class_exists($seam)` and this fails -- the stand-in class exists, so the
     * unlicensed block would survive.
     */
    public function test_block_registry_drops_a_present_seam_that_the_licence_refuses(): void
    {
        $available = $this->available_blocks_for(
            array(
                'unlicensed-pro' => array( 'tag' => 'x', 'pro_seam' => self::PRESENT_CLASS, 'pro_feature' => 'pro' ),
                'plain-free'     => array( 'tag' => 'y' ),
            )
        );

        $this->assertArrayNotHasKey(
            'unlicensed-pro',
            $available,
            'A block whose class ships but whose licence is absent must not register.'
        );
        // Positive control: a gate that dropped everything would satisfy the
        // assertion above while breaking the plugin.
        $this->assertArrayHasKey('plain-free', $available, 'A block with no seam must never be dropped.');
    }

    /**
     * A `pro_seam` with no `pro_feature` FAILS CLOSED.
     *
     * This test used to assert the exact opposite -- that such a seam "keeps its
     * historic presence-only behaviour" and stays registered. That was not a
     * harmless default; it was the bug, pinned in place by a green test. A seam
     * with no feature key routed to Mode::allowsSeam(null), which returns true by
     * contract, so declaring something a Pro seam and forgetting its key GAVE IT
     * AWAY. Six registry entries across the three registries shipped exactly that
     * way (messages + the whole vendor group), and on a Pro-installed-but-
     * unlicensed site they registered with their real renderers. Verified at
     * runtime with isPro=false before the fix.
     *
     * The registries now default a keyless seam to 'pro', so the failure mode of a
     * forgotten key is a closed feature rather than a free one.
     *
     * Note the scope: Mode::allowsSeam(null) still returns true -- that contract is
     * unchanged and still covered above. What changed is that the REGISTRIES no
     * longer pass null for a seam that declared itself Pro.
     *
     * Mutation proof: restore the `: null` default in get_available_blocks() and
     * this fails -- the stand-in class exists, so the keyless seam would survive.
     */
    public function test_block_registry_drops_a_present_seam_that_declares_no_feature(): void
    {
        $available = $this->available_blocks_for(
            array(
                'seam-no-feature' => array( 'tag' => 'x', 'pro_seam' => self::PRESENT_CLASS ),
                'plain-free'      => array( 'tag' => 'y' ),
            )
        );

        $this->assertArrayNotHasKey(
            'seam-no-feature',
            $available,
            'A pro_seam with no pro_feature must fail closed, not register for free.'
        );
        // Positive control: only entries that DECLARE a seam are affected. A block
        // with no pro_seam at all is core and must never be touched.
        $this->assertArrayHasKey('plain-free', $available, 'A block with no seam must never be dropped.');
    }

    /**
     * The same fail-closed default, in the shortcode registry. Both registries must
     * default identically or a block outlives the shortcode it renders through --
     * which prints raw `[rentiva_vendor_apply]` text at visitors.
     *
     * Mutation proof: restore the `: null` default in drop_absent_pro_seams() and
     * this fails.
     */
    public function test_shortcode_registry_drops_a_present_seam_that_declares_no_feature(): void
    {
        $filtered = $this->drop_seams_from(
            array(
                'grp' => array(
                    'keyless_seam' => array( 'class' => self::PRESENT_CLASS, 'pro_seam' => true ),
                    'free_tag'     => array( 'class' => self::PRESENT_CLASS ),
                ),
            )
        );

        $this->assertArrayNotHasKey(
            'keyless_seam',
            $filtered['grp'],
            'A pro_seam shortcode with no pro_feature must fail closed.'
        );
        $this->assertArrayHasKey('free_tag', $filtered['grp'], 'A non-seam shortcode must never be dropped.');
    }

    public function test_block_registry_still_drops_a_seam_whose_class_is_absent(): void
    {
        $available = $this->available_blocks_for(
            array(
                'absent-seam' => array( 'tag' => 'x', 'pro_seam' => 'MHMRentiva\No\Such\Class', 'pro_feature' => 'pro' ),
            )
        );

        $this->assertArrayNotHasKey('absent-seam', $available, 'The original class guard must still hold.');
    }

    // -- ShortcodeServiceProvider --------------------------------------------------

    /**
     * Mutation proof: drop the `$licensed` term from drop_absent_pro_seams() and
     * this fails -- the tag would stay in the registry, and process_registration()
     * would then call the Pro class's own register(), handing over its hooks too.
     */
    public function test_shortcode_registry_drops_a_present_seam_that_the_licence_refuses(): void
    {
        $filtered = $this->drop_seams_from(
            array(
                'grp' => array(
                    'unlicensed_tag' => array( 'class' => self::PRESENT_CLASS, 'pro_seam' => true, 'pro_feature' => 'pro' ),
                    'free_tag'       => array( 'class' => self::PRESENT_CLASS ),
                ),
            )
        );

        $this->assertArrayNotHasKey('unlicensed_tag', $filtered['grp'], 'An unlicensed Pro shortcode must not register.');
        $this->assertArrayHasKey('free_tag', $filtered['grp'], 'A non-seam shortcode must never be dropped.');
    }

    /**
     * The lapsed-licence case must not trade a leak for a different user-visible
     * defect. An unregistered shortcode degrades to its literal source text, so a
     * site whose licence lapsed would start printing `[rentiva_transfer_search]`
     * at visitors on pages it has had for months. Those tags are tracked so a
     * silent no-op renderer can be registered for them instead.
     */
    public function test_a_seam_closed_by_the_licence_is_tracked_for_silent_rendering(): void
    {
        $provider = $this->provider();
        $this->drop_seams_with($provider, array(
            'grp' => array(
                'unlicensed_tag' => array( 'class' => self::PRESENT_CLASS, 'pro_seam' => true, 'pro_feature' => 'pro' ),
            ),
        ));

        $this->assertSame(
            array( 'unlicensed_tag' ),
            $this->read_unlicensed_tags($provider),
            'A tag closed by the licence must be silenced, not left to print its raw source text.'
        );
    }

    /**
     * A seam whose class was never shipped is a different case: this build never
     * had the tag, so no page can contain it and there is nothing to silence.
     * Registering a no-op for it would resurrect a tag Lite deliberately carved.
     */
    public function test_a_seam_absent_from_the_build_is_not_tracked_for_silent_rendering(): void
    {
        $provider = $this->provider();
        $this->drop_seams_with($provider, array(
            'grp' => array(
                'absent_tag' => array( 'class' => 'MHMRentiva\No\Such\Class', 'pro_seam' => true, 'pro_feature' => 'pro' ),
            ),
        ));

        $this->assertSame(array(), $this->read_unlicensed_tags($provider));
    }

    // -- helpers -------------------------------------------------------------------

    /**
     * Runs BlockRegistry's seam filter over a synthetic block set, restoring the
     * real one afterwards so no other test sees the substitution.
     *
     * @param array<string, array<string, mixed>> $blocks
     * @return array<string, array<string, mixed>>
     */
    private function available_blocks_for(array $blocks): array
    {
        $reflection = new ReflectionClass(BlockRegistry::class);
        $property   = $reflection->getProperty('blocks');
        $property->setAccessible(true);
        $original = $property->getValue();

        $property->setValue(null, $blocks);
        try {
            $method = $reflection->getMethod('get_available_blocks');
            $method->setAccessible(true);

            return (array) $method->invoke(null);
        } finally {
            $property->setValue(null, $original);
        }
    }

    private function provider(): ShortcodeServiceProvider
    {
        // Sidesteps the singleton and its constructor: this test wants the filter,
        // not a second live registration pass over the whole plugin.
        return (new ReflectionClass(ShortcodeServiceProvider::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<string, array<string, array>> $registry
     * @return array<string, array<string, array>>
     */
    private function drop_seams_from(array $registry): array
    {
        return $this->drop_seams_with($this->provider(), $registry);
    }

    /**
     * @param array<string, array<string, array>> $registry
     * @return array<string, array<string, array>>
     */
    private function drop_seams_with(ShortcodeServiceProvider $provider, array $registry): array
    {
        $method = new \ReflectionMethod(ShortcodeServiceProvider::class, 'drop_absent_pro_seams');
        $method->setAccessible(true);

        return (array) $method->invoke($provider, $registry);
    }

    /**
     * @return array<int, string>
     */
    private function read_unlicensed_tags(ShortcodeServiceProvider $provider): array
    {
        $property = new \ReflectionProperty(ShortcodeServiceProvider::class, 'unlicensed_seam_tags');
        $property->setAccessible(true);

        return (array) $property->getValue($provider);
    }
}
