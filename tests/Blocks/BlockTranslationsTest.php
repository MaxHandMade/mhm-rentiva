<?php

namespace MHMRentiva\Tests\Blocks;

use MHMRentiva\Blocks\BlockRegistry;
use ReflectionClass;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

/**
 * Block editor scripts carry their owner's text domain and `languages/` path,
 * and the WP.org language pack is preferred over the bundled JSON catalogue
 * for Rentiva-owned translation paths only (spec §2.1).
 *
 * @package MHMRentiva\Tests\Blocks
 */
class BlockTranslationsTest extends WP_UnitTestCase
{

    /** @var string[] Files this test created; removed in tearDown. */
    private array $created_files = array();

    /** @var string[] Directories this test created; removed in tearDown (deepest first). */
    private array $created_dirs = array();

    /** @var string[] Script handles this test registered. */
    private array $created_handles = array();

    /** @var string[] Block types this test registered. */
    private array $created_blocks = array();

    public function tearDown(): void
    {
        foreach ($this->created_handles as $handle) {
            wp_deregister_script($handle);
        }
        foreach ($this->created_blocks as $name) {
            if (WP_Block_Type_Registry::get_instance()->is_registered($name)) {
                unregister_block_type($name);
            }
        }
        foreach ($this->created_files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        foreach (array_reverse($this->created_dirs) as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
        $this->created_files   = array();
        $this->created_dirs    = array();
        $this->created_handles = array();
        $this->created_blocks  = array();

        parent::tearDown();
    }

    /**
     * @return string[] Lite's own block slugs.
     */
    private function lite_slugs(): array
    {
        $property = ( new ReflectionClass(BlockRegistry::class) )->getProperty('blocks');
        $property->setAccessible(true);

        return array_keys((array) $property->getValue());
    }

    private function make_dir(string $dir): void
    {
        $parts = array();
        while (! is_dir($dir)) {
            $parts[] = $dir;
            $dir     = dirname($dir);
        }
        foreach (array_reverse($parts) as $d) {
            mkdir($d);
            $this->created_dirs[] = $d;
        }
    }

    private function write_file(string $path, string $contents): void
    {
        $this->make_dir(dirname($path));
        $this->assertFalse(file_exists($path), 'Refusing to overwrite an existing file: ' . $path);
        // A silent write failure would turn "pack wins" into "bundled kept" for the
        // wrong reason. WP_LANG_DIR/plugins must be writable by the test user
        // (CI: /tmp/wordpress-tests-lib; dev container: chown www-data once).
        $this->assertNotFalse(@file_put_contents($path, $contents), 'Could not write fixture: ' . $path);
        $this->created_files[] = $path;
    }

    private function bundled_file(string $handle_rel = 'assets/blocks/vehicles-grid/index.js'): string
    {
        return MHMRENTIVA_PLUGIN_DIR . 'languages/mhm-rentiva-tr_TR-' . md5($handle_rel) . '.json';
    }

    private function pack_file_for(string $file): string
    {
        return WP_LANG_DIR . '/plugins/' . basename($file);
    }

    private function filter(string $file, string $handle, string $domain = 'mhm-rentiva')
    {
        return apply_filters('load_script_translation_file', $file, $handle, $domain);
    }

    public function test_lite_handles_have_textdomain_and_path(): void
    {
        $slugs = $this->lite_slugs();
        $this->assertCount(16, $slugs);

        foreach ($slugs as $slug) {
            $handle = 'mhm-rentiva-block-' . $slug . '-editor';
            $this->assertArrayHasKey($handle, wp_scripts()->registered, $handle);
            $dep = wp_scripts()->registered[ $handle ];
            $this->assertSame('mhm-rentiva', $dep->textdomain, $handle);
            $this->assertSame(MHMRENTIVA_PLUGIN_DIR . 'languages/', $dep->translations_path, $handle);
        }
    }

    public function test_contributed_block_uses_its_own_domain(): void
    {
        $base_dir = get_temp_dir() . 'mhm-x-test-' . wp_generate_password(8, false) . '/';
        $this->write_file($base_dir . 'assets/blocks/x-test/index.js', '/* x-test */');
        $this->write_file(
            $base_dir . 'assets/blocks/x-test/block.json',
            (string) wp_json_encode(
                array(
                    'apiVersion' => 3,
                    'name'       => 'mhm-rentiva-test/x-test',
                    'title'      => 'X Test',
                    'category'   => 'rentiva',
                    'textdomain' => 'x-test',
                )
            )
        );

        $cfg = array(
            'tag'        => 'x_test',
            'title'      => 'X Test',
            'base_dir'   => $base_dir,
            'base_url'   => 'https://example.test/x-test/',
            'textdomain' => 'x-test',
            'css'        => array(),
        );
        $contribute = static function (array $blocks) use ($cfg): array {
            $blocks['x-test'] = $cfg;
            return $blocks;
        };
        add_filter('mhmrentiva_blocks', $contribute);

        $handle                  = 'mhm-rentiva-block-x-test-editor';
        $this->created_handles[] = $handle;
        $this->created_blocks[]  = 'mhm-rentiva-test/x-test';

        BlockRegistry::register_block('x-test', $cfg);
        remove_filter('mhmrentiva_blocks', $contribute);

        $this->assertArrayHasKey($handle, wp_scripts()->registered);
        $dep = wp_scripts()->registered[ $handle ];
        $this->assertSame('x-test', $dep->textdomain);
        $this->assertSame($base_dir . 'languages/', $dep->translations_path);
        $this->assertTrue(WP_Block_Type_Registry::get_instance()->is_registered('mhm-rentiva-test/x-test'));
    }

    public function test_filter_passes_false_through(): void
    {
        $this->assertFalse(
            apply_filters('load_script_translation_file', false, 'mhm-rentiva-block-vehicles-grid-editor', 'mhm-rentiva')
        );
    }

    public function test_pack_file_wins_when_present(): void
    {
        $file = $this->bundled_file();
        $pack = $this->pack_file_for($file);
        $this->write_file($pack, '{}');

        $this->assertSame($pack, $this->filter($file, 'mhm-rentiva-block-vehicles-grid-editor'));
    }

    public function test_bundled_file_kept_without_pack(): void
    {
        $file = $this->bundled_file();
        $this->assertFileDoesNotExist($this->pack_file_for($file));

        $this->assertSame($file, $this->filter($file, 'mhm-rentiva-block-vehicles-grid-editor'));
    }

    public function test_unregistered_handle_untouched(): void
    {
        $file = $this->bundled_file();
        $this->write_file($this->pack_file_for($file), '{}');

        $this->assertSame($file, $this->filter($file, 'mhm-rentiva-no-such-handle'));
    }

    public function test_foreign_handle_untouched(): void
    {
        $handle                  = 'mhm-rentiva-test-foreign';
        $this->created_handles[] = $handle;
        wp_register_script($handle, 'https://example.test/foreign.js', array(), '1', true);
        wp_set_script_translations($handle, 'foreign', '/var/tmp/foreign-plugin/languages/');

        $file = '/var/tmp/foreign-plugin/languages/foreign-tr_TR-' . md5('foreign.js') . '.json';
        $this->write_file($this->pack_file_for($file), '{}');

        $this->assertSame($file, $this->filter($file, $handle, 'foreign'));
    }

    public function test_path_format_difference_still_matches(): void
    {
        $handle                  = 'mhm-rentiva-test-format';
        $this->created_handles[] = $handle;
        wp_register_script($handle, 'https://example.test/format.js', array(), '1', true);
        // No trailing slash, backslash separators: the core stores the value raw.
        $raw_path = str_replace('/', '\\', untrailingslashit(MHMRENTIVA_PLUGIN_DIR . 'languages/'));
        wp_set_script_translations($handle, 'mhm-rentiva', $raw_path);
        $this->assertSame($raw_path, wp_scripts()->registered[ $handle ]->translations_path);

        $file = $this->bundled_file('format.js');
        $pack = $this->pack_file_for($file);
        $this->write_file($pack, '{}');

        $this->assertSame($pack, $this->filter($file, $handle));
    }

    public function test_react_handle_goes_through_filter(): void
    {
        // A React page handle registered the way ui-core does it: `$base_dir . 'languages/'`.
        $handle                  = 'mhm-rentiva-test-react-page';
        $this->created_handles[] = $handle;
        wp_register_script($handle, 'https://example.test/react-page.js', array(), '1', true);
        wp_set_script_translations($handle, 'mhm-rentiva', MHMRENTIVA_PLUGIN_DIR . 'languages/');

        $file = $this->bundled_file('build/react-page.js');
        $pack = $this->pack_file_for($file);
        $this->write_file($pack, '{}');

        $this->assertSame($pack, $this->filter($file, $handle));
    }
}
