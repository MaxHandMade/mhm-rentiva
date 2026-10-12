<?php

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Blocks\BlockRegistry;
use ReflectionClass;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Lite's blocks live in a single "Rentiva" inserter category (spec §2.1);
 * block names, and therefore saved content, are unchanged.
 *
 * @package MHMRentiva\Tests\Blocks
 */
class BlockCategoryTest extends WP_UnitTestCase
{

    public function test_rentiva_category_registered_once(): void
    {
        $post       = self::factory()->post->create_and_get();
        $categories = get_block_categories($post);

        $matches = array_values(
            array_filter(
                $categories,
                static function ($category): bool {
                    return isset($category['slug']) && 'rentiva' === $category['slug'];
                }
            )
        );

        $this->assertCount(1, $matches);
        $this->assertSame('Rentiva', $matches[0]['title']);
    }

    public function test_register_category_does_not_duplicate(): void
    {
        $input  = array(
            array(
                'slug'  => 'rentiva',
                'title' => 'Rentiva',
                'icon'  => null,
            ),
        );
        $output = BlockRegistry::register_category($input);

        $this->assertSame($input, $output);
    }

    public function test_lite_blocks_in_rentiva_category(): void
    {
        $property = ( new ReflectionClass(BlockRegistry::class) )->getProperty('blocks');
        $property->setAccessible(true);
        $slugs = array_keys((array) $property->getValue());
        $this->assertCount(16, $slugs);

        $registry = WP_Block_Type_Registry::get_instance();
        foreach ($slugs as $slug) {
            $type = $registry->get_registered('mhm-rentiva/' . $slug);
            $this->assertNotNull($type, $slug);
            $this->assertSame('rentiva', $type->category, $slug);
        }
    }

    public function test_saved_block_markup_still_renders(): void
    {
        $html = do_blocks('<!-- wp:mhm-rentiva/vehicles-grid /-->');

        $this->assertNotSame('', trim($html));
        $this->assertStringContainsString('mhm-', $html);
    }
}
