<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Admin\Core\AssetManager;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * A server-rendered block's preview in the editor is styled only by the handles
 * of its block.json `style`: no shortcode runs in that document to enqueue them.
 * Pointing at a handle nobody registers fails silently (a bare preview, no notice).
 *
 * @package MHMRentiva\Tests\Blocks
 */
class BlockEditorStylesTest extends WP_UnitTestCase
{
    /**
     * The blocks on the rvs standard, with the sheet their preview needs.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function rvs_block_provider(): array
    {
        return array(
            'comparison' => array( 'vehicle-comparison', 'mhm-rentiva-rvs-comparison' ),
            'calendar'   => array( 'availability-calendar', 'mhm-rentiva-rvs-availability' ),
            'vehicle'    => array( 'vehicle-details', 'mhm-rentiva-rvs-vehicle' ),
            'booking'    => array( 'booking-form', 'mhm-rentiva-rvs-booking' ),
        );
    }

    /**
     * @dataProvider rvs_block_provider
     */
    public function test_block_json_names_the_registered_rvs_sheet( string $slug, string $handle ): void
    {
        AssetManager::register_core_styles();

        $path = MHMRENTIVA_PLUGIN_DIR . 'assets/blocks/' . $slug . '/block.json';
        $json = json_decode( (string) file_get_contents( $path ), true );

        $this->assertContains( $handle, (array) $json['style'] );
        $this->assertTrue( wp_style_is( $handle, 'registered' ), $handle . ' is not registered.' );
    }

    /**
     * @dataProvider rvs_block_provider
     */
    public function test_the_registered_block_type_keeps_the_sheet( string $slug, string $handle ): void
    {
        $name = 'mhm-rentiva/' . $slug;
        $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
        if ( null === $type ) {
            \MHMRentiva\Blocks\BlockRegistry::register_blocks();
            $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
        }

        $this->assertNotNull( $type );
        $this->assertContains( $handle, (array) $type->style_handles );
    }
}
