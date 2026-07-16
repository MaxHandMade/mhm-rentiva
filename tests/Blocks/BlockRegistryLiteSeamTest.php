<?php

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Blocks\BlockRegistry;
use ReflectionClass;
use ReflectionMethod;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Lite must not register blocks it cannot render.
 *
 * Every block delegates to its shortcode through do_shortcode(), so a block whose
 * backing shortcode class is carved out of Lite has nothing to render. An
 * unregistered shortcode does not vanish -- it degrades to its own literal source
 * text -- so registering the block anyway put the raw `[rentiva_transfer_search]`
 * string in front of visitors and left the block in the editor inserter.
 *
 * This suite runs against the real Lite tree, where the Pro shortcode classes are
 * genuinely absent, so it exercises the seam rather than a simulation of it.
 *
 * @package MHMRentiva\Tests\Blocks
 */
class BlockRegistryLiteSeamTest extends WP_UnitTestCase
{

    /**
     * Blocks whose backing shortcode class Lite carves out, mapped to that class.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function pro_block_provider(): array
    {
        return array(
            'transfer-results' => array( 'transfer-results', 'MHMRentiva\Admin\Transfer\Frontend\TransferResults' ),
            'transfer-search'  => array( 'transfer-search', 'MHMRentiva\Admin\Transfer\Frontend\TransferShortcodes' ),
            'popular-routes'   => array( 'popular-routes', 'MHMRentiva\Admin\Transfer\Frontend\PopularRoutesShortcode' ),
            'messages'         => array( 'messages', 'MHMRentiva\Admin\Frontend\Shortcodes\Account\AccountMessages' ),
            'vendor-profile'   => array( 'vendor-profile', 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorProfile' ),
            'vendor-directory' => array( 'vendor-directory', 'MHMRentiva\Admin\Frontend\Shortcodes\Vendor\VendorDirectory' ),
        );
    }

    /**
     * Guards the premise of every other assertion here: if one of these classes
     * ever ships in Lite, the "absent" expectations below would pass vacuously
     * for the wrong reason.
     *
     * @dataProvider pro_block_provider
     */
    public function test_pro_shortcode_classes_are_absent_from_lite(string $slug, string $class): void
    {
        $this->assertFalse(
            class_exists($class),
            sprintf('Lite unexpectedly ships %s, backing the "%s" block.', $class, $slug)
        );
    }

    /**
     * The end-user-visible outcome: no Pro block type is registered, so the editor
     * inserter cannot offer it and a saved instance cannot render.
     *
     * Asserts against the live registry as the plugin's own `init` hook left it --
     * re-invoking register_blocks() here would both trip WP's "already registered"
     * notice and test a re-run rather than the real boot.
     *
     * @dataProvider pro_block_provider
     */
    public function test_pro_blocks_are_not_registered_in_lite(string $slug): void
    {
        $this->assertFalse(
            WP_Block_Type_Registry::get_instance()->is_registered('mhm-rentiva/' . $slug),
            sprintf('Block "mhm-rentiva/%s" is registered in Lite but has no shortcode to render.', $slug)
        );
    }

    /**
     * The gate must be surgical: core blocks, whose shortcode classes Lite does
     * ship, keep registering. A gate that dropped everything would satisfy the
     * test above while breaking the plugin -- and would also prove the registry
     * was simply never populated, making that test vacuous.
     */
    public function test_core_blocks_are_still_registered_in_lite(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();

        foreach (array( 'unified-search', 'search-results', 'vehicles-grid', 'booking-form', 'my-bookings' ) as $slug) {
            $this->assertTrue(
                $registry->is_registered('mhm-rentiva/' . $slug),
                sprintf('Core block "mhm-rentiva/%s" must still register in Lite.', $slug)
            );
        }
    }

    /**
     * The filter keys off the declared seam, not off a hardcoded slug list: every
     * block carrying a `pro_seam` whose class is absent drops, and nothing else
     * does.
     */
    public function test_available_blocks_drops_exactly_the_absent_pro_seams(): void
    {
        $all       = $this->get_declared_blocks();
        $available = $this->get_available_blocks();

        $expected_dropped = array();
        foreach ($all as $slug => $config) {
            if (isset($config['pro_seam']) && ! class_exists((string) $config['pro_seam'])) {
                $expected_dropped[] = $slug;
            }
        }

        sort($expected_dropped);
        $actual_dropped = array_values(array_diff(array_keys($all), array_keys($available)));
        sort($actual_dropped);

        $this->assertNotSame(array(), $expected_dropped, 'Premise failed: Lite declares no absent Pro seam.');
        $this->assertSame($expected_dropped, $actual_dropped);
    }

    /**
     * A block with no `pro_seam` is never dropped, regardless of build.
     */
    public function test_blocks_without_a_pro_seam_are_never_dropped(): void
    {
        $available = $this->get_available_blocks();

        foreach ($this->get_declared_blocks() as $slug => $config) {
            if (isset($config['pro_seam'])) {
                continue;
            }

            $this->assertArrayHasKey($slug, $available, sprintf('Seamless block "%s" was dropped.', $slug));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function get_declared_blocks(): array
    {
        $property = ( new ReflectionClass(BlockRegistry::class) )->getProperty('blocks');
        $property->setAccessible(true);

        return (array) $property->getValue();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function get_available_blocks(): array
    {
        $method = new ReflectionMethod(BlockRegistry::class, 'get_available_blocks');
        $method->setAccessible(true);

        return (array) $method->invoke(null);
    }
}
