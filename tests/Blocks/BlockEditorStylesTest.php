<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Admin\Core\AssetManager;
use MHMRentiva\Blocks\BlockRegistry;
use WP_Block_Type;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * A server-rendered block's preview in the editor is styled only by its block.json
 * handles: no shortcode runs in that document to enqueue them. Pointing at a handle
 * nobody registers fails silently (a bare preview, no notice).
 *
 * These blocks name their sheet under `editorStyle` alone: on a classic theme a
 * `style` handle is enqueued on every front page, and their shortcodes enqueue it
 * where they render.
 *
 * @package MHMRentiva\Tests\Blocks
 */
class BlockEditorStylesTest extends WP_UnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function editor_only_provider(): array
    {
        return array(
            'comparison' => array( 'vehicle-comparison', 'mhm-rentiva-rvs-comparison' ),
            'contact'    => array( 'contact', 'mhm-rentiva-rvs-contact' ),
            'rating'     => array( 'vehicle-rating-form', 'mhm-rentiva-rvs-reviews' ),
            'testimonials' => array( 'testimonials', 'mhm-rentiva-rvs-reviews' ),
            'calendar'   => array( 'availability-calendar', 'mhm-rentiva-rvs-availability' ),
            'vehicle'    => array( 'vehicle-details', 'mhm-rentiva-rvs-vehicle' ),
            'booking'    => array( 'booking-form', 'mhm-rentiva-rvs-booking' ),
        );
    }

    /**
     * @dataProvider editor_only_provider
     */
    public function test_block_json_names_the_sheet_for_the_editor_only( string $slug, string $handle ): void
    {
        $path = MHMRENTIVA_PLUGIN_DIR . 'assets/blocks/' . $slug . '/block.json';
        $json = json_decode( (string) file_get_contents( $path ), true );

        $this->assertContains( $handle, (array) $json['editorStyle'] );
        $this->assertNotContains( $handle, (array) ( $json['style'] ?? array() ) );
    }

    /**
     * @dataProvider editor_only_provider
     */
    public function test_the_registered_block_type_keeps_it_for_the_editor_only( string $slug, string $handle ): void
    {
        $type = $this->block_type( $slug );

        $this->assertContains( $handle, (array) $type->editor_style_handles );
        $this->assertNotContains( $handle, (array) $type->style_handles );
    }

    /**
     * The shared layer the sheet stands on comes with it in the editor too.
     *
     * @dataProvider editor_only_provider
     */
    public function test_the_sheet_is_registered_and_pulls_in_the_rvs_tokens( string $slug, string $handle ): void
    {
        AssetManager::register_core_styles();

        $this->assertTrue( wp_style_is( $handle, 'registered' ), $handle . ' is not registered.' );
        $this->assertContains( 'mhm-rentiva-rvs-tokens', $this->dependency_chain( $handle ) );
    }

    /**
     * @return array<int, string>
     */
    private function dependency_chain( string $handle ): array
    {
        $seen  = array();
        $queue = array( $handle );
        while ( array() !== $queue ) {
            $current = (string) array_shift( $queue );
            if ( in_array( $current, $seen, true ) || ! isset( wp_styles()->registered[ $current ] ) ) {
                continue;
            }
            $seen[] = $current;
            $queue  = array_merge( $queue, wp_styles()->registered[ $current ]->deps );
        }

        return $seen;
    }

    private function block_type( string $slug ): WP_Block_Type
    {
        $name = 'mhm-rentiva/' . $slug;
        $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
        if ( null === $type ) {
            BlockRegistry::register_blocks();
            $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
        }
        $this->assertInstanceOf( WP_Block_Type::class, $type );

        return $type;
    }
}
