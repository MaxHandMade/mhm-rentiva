<?php
declare(strict_types=1);
namespace MHMRentiva\Tests\Unit\Blocks;

use MHMRentiva\Blocks\BlockRegistry;
use WP_UnitTestCase;

final class BlockRegistryFilterTest extends WP_UnitTestCase {
    private function config(): array {
        $m = new \ReflectionMethod( BlockRegistry::class, 'get_block_config' );
        $m->setAccessible( true );
        return $m->invoke( null );
    }
    public function test_lite_has_no_pro_blocks_and_no_seam_keys(): void {
        $blocks = $this->config();
        foreach ( array('transfer-results','messages','transfer-search','popular-routes','vendor-profile','vendor-directory') as $pro ) {
            $this->assertArrayNotHasKey( $pro, $blocks );
        }
        foreach ( $blocks as $slug => $cfg ) {
            $this->assertArrayNotHasKey( 'pro_seam', $cfg, "$slug" );
            $this->assertArrayNotHasKey( 'pro_feature', $cfg, "$slug" );
        }
        $this->assertArrayHasKey( 'search-results', $blocks );
    }
    public function test_filter_admits_a_subscriber_block(): void {
        add_filter( 'mhm_rentiva_blocks', static fn( $b ) => $b + array( 'x-demo' => array( 'tag' => 'rentiva_x_demo', 'title' => 'X', 'css' => 'x.css', 'base_url' => 'https://pro.example/', 'base_dir' => '/pro/' ) ) );
        $this->assertArrayHasKey( 'x-demo', $this->config() );
    }
}
