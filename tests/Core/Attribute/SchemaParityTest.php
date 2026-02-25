<?php

namespace MHMRentiva\Tests\Core\Attribute;

use MHMRentiva\Core\Attribute\AllowlistRegistry;
use MHMRentiva\Admin\Core\ShortcodeServiceProvider;
use MHMRentiva\Blocks\BlockRegistry;
use WP_UnitTestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Schema Parity Test
 *
 * Ensures that AllowlistRegistry stays in sync with block.json,
 * shortcode defaults, and legacy aliases.
 *
 * @package MHMRentiva\Tests\Core\Attribute
 * @since 4.11.0
 */
class SchemaParityTest extends WP_UnitTestCase
{

    /**
     * Verify that all 19 shortcode default attributes exist in Registry.
     */
    public function test_shortcode_defaults_are_in_registry()
    {
        $service_provider = ShortcodeServiceProvider::instance();
        $reflection       = new ReflectionClass($service_provider);
        $method           = $reflection->getMethod('get_shortcode_registry');
        $method->setAccessible(true);
        $registry = $method->invoke($service_provider);

        foreach ($registry as $group => $shortcodes) {
            foreach ($shortcodes as $tag => $config) {
                $class = $config['class'];
                if (! class_exists($class)) {
                    continue;
                }

                if (!method_exists($class, 'get_default_attributes')) {
                    continue;
                }

                $default_method = new ReflectionMethod($class, 'get_default_attributes');
                $default_method->setAccessible(true);
                $defaults = $default_method->invoke(null);

                $schema = AllowlistRegistry::get_schema($tag);

                foreach (array_keys($defaults) as $attr_key) {

                    $this->assertArrayHasKey(
                        $attr_key,
                        $schema,
                        sprintf('Shortcode attribute "%s" for tag "%s" is missing from AllowlistRegistry.', $attr_key, $tag)
                    );
                }
            }
        }
    }

    /**
     * Verify that all 19 block.json attributes exist in Registry.
     */
    public function test_block_json_attributes_are_in_registry()
    {
        $block_dir = MHM_RENTIVA_PLUGIN_PATH . 'assets/blocks';
        $it        = new \RecursiveDirectoryIterator($block_dir);
        $display   = new \RecursiveIteratorIterator($it);

        $blocks_found = 0;

        foreach ($display as $file) {
            if ($file->getFilename() === 'block.json') {
                $blocks_found++;
                $content    = json_decode(file_get_contents($file->getPathname()), true);
                $block_name = $content['name'];
                $tag        = $this->resolve_tag_from_block_name($block_name);

                if (! $tag) {
                    $this->fail(sprintf('Could not resolve shortcode tag for block "%s".', $block_name));
                }

                $schema     = AllowlistRegistry::get_schema($tag);
                $attributes = $content['attributes'] ?? [];

                foreach (array_keys($attributes) as $raw_key) {
                    // We need to check both the key and its potential canonical version
                    $canonical_found = false;

                    // 1. Direct check
                    if (isset($schema[$raw_key])) {
                        $canonical_found = true;
                    } else {
                        // 2. Alias check
                        foreach ($schema as $s_key => $s_config) {
                            if (isset($s_config['aliases']) && in_array($raw_key, $s_config['aliases'], true)) {
                                $canonical_found = true;
                                break;
                            }
                        }
                    }

                    // 3. camelCase to snake_case fallback check (if not strictly defined yet)
                    if (! $canonical_found) {
                        $normalized = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $raw_key));
                        if (isset($schema[$normalized])) {
                            $canonical_found = true;
                        }
                    }

                    $error_msg = sprintf('Block attribute "%s" for block "%s" (tag: %s) is missing from AllowlistRegistry mapping.', $raw_key, $block_name, $tag);

                    $this->assertTrue(
                        $canonical_found,
                        $error_msg
                    );
                }
            }
        }

        $this->assertEquals(19, $blocks_found, 'Expected 19 block.json files to be found.');
    }

    /**
     * Resolves shortcode tag from block name.
     */
    private function resolve_tag_from_block_name(string $block_name): ?string
    {
        $slug = str_replace('mhm-rentiva/', '', $block_name);

        $reflection = new ReflectionClass(BlockRegistry::class);
        $property   = $reflection->getProperty('blocks');
        $property->setAccessible(true);
        $registry   = $property->getValue();

        return $registry[$slug]['tag'] ?? null;
    }

    /**
     * Verify no duplicate PHP array keys exist in ALLOWLIST.
     * In PHP, duplicate keys in a const array silently overwrite the first.
     * This test reads the raw file and detects key collisions.
     *
     * @covers \MHMRentiva\Core\Attribute\AllowlistRegistry
     */
    public function test_allowlist_has_no_duplicate_keys(): void
    {
        $file = file_get_contents(MHM_RENTIVA_PLUGIN_PATH . 'src/Core/Attribute/AllowlistRegistry.php');

        // Extract ONLY the ALLOWLIST constant section (before TAG_MAPPING starts)
        if (! preg_match('/public const ALLOWLIST = \[(.*?)private const TAG_MAPPING = \[/s', $file, $section_match)) {
            $this->fail('Could not extract ALLOWLIST section from AllowlistRegistry.php');
        }
        $allowlist_section = $section_match[1];

        // Match top-level ALLOWLIST keys: exactly 8 spaces indent (class body 4 + array level 4)
        // Sub-keys like 'aliases', 'values', 'type', 'group' are at 12 spaces — not matched here
        preg_match_all("/^        '([a-z_]+)'\s*=>\s*\[/m", $allowlist_section, $matches);
        $keys = $matches[1];

        $counted    = array_count_values($keys);
        $duplicates = array_filter($counted, fn($count) => $count > 1);

        $this->assertEmpty(
            $duplicates,
            'AllowlistRegistry::ALLOWLIST has duplicate PHP array keys: ' . implode(', ', array_keys($duplicates))
        );
    }

    /**
     * Verify ids attribute is type idlist (not string).
     * C1 duplicate key caused type drift: idlist → string.
     *
     * @covers \MHMRentiva\Core\Attribute\AllowlistRegistry
     */
    public function test_ids_attribute_type_is_idlist(): void
    {
        $allowlist = ( new \ReflectionClass( \MHMRentiva\Core\Attribute\AllowlistRegistry::class ) )
            ->getReflectionConstant( 'ALLOWLIST' )
            ->getValue();

        $this->assertArrayHasKey( 'ids', $allowlist, 'ids key missing from ALLOWLIST' );
        $this->assertSame( 'idlist', $allowlist['ids']['type'], 'ids type should be idlist, not string' );
        $this->assertContains( 'vehicleIds', $allowlist['ids']['aliases'], 'ids aliases should include vehicleIds' );
        $this->assertContains( 'vehicle_ids', $allowlist['ids']['aliases'], 'ids aliases should include vehicle_ids' );
    }

    /**
     * Verify default_tab attribute is type enum (not string).
     * C1 duplicate key caused type drift: enum → string.
     *
     * @covers \MHMRentiva\Core\Attribute\AllowlistRegistry
     */
    public function test_default_tab_type_is_enum(): void
    {
        $allowlist = ( new \ReflectionClass( \MHMRentiva\Core\Attribute\AllowlistRegistry::class ) )
            ->getReflectionConstant( 'ALLOWLIST' )
            ->getValue();

        $this->assertArrayHasKey( 'default_tab', $allowlist );
        $this->assertSame( 'enum', $allowlist['default_tab']['type'], 'default_tab type should be enum, not string' );
        // values constraint is verified in test_enum_attributes_have_values (Task 3/4)
    }

    /**
     * Verify all enum attributes in ALLOWLIST have non-empty values arrays.
     * Enum attributes without values cannot perform input validation — M1 defect.
     *
     * @covers \MHMRentiva\Core\Attribute\AllowlistRegistry
     */
    public function test_enum_attributes_have_values(): void
    {
        $allowlist = ( new \ReflectionClass( \MHMRentiva\Core\Attribute\AllowlistRegistry::class ) )
            ->getReflectionConstant( 'ALLOWLIST' )
            ->getValue();

        $enum_attrs_without_values = [];
        foreach ( $allowlist as $key => $config ) {
            if ( ( $config['type'] ?? '' ) === 'enum' && empty( $config['values'] ) ) {
                $enum_attrs_without_values[] = $key;
            }
        }

        $this->assertEmpty(
            $enum_attrs_without_values,
            'These enum attributes are missing values arrays: ' . implode( ', ', $enum_attrs_without_values )
        );
    }
}
