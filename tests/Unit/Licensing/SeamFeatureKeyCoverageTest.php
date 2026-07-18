<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Core\ShortcodeServiceProvider;
use MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Blocks\BlockRegistry;
use ReflectionClass;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Every Pro seam must NAME the licence feature it needs.
 *
 * THE BUG THIS PINS
 * -----------------
 * Declaring `pro_seam` without `pro_feature` did not gate a feature loosely -- it
 * did not gate it at all. The key was optional and defaulted to null, and
 * Mode::allowsSeam(null) returns true by contract ("a seam with no licence
 * requirement"). So on the configuration that actually pays the bills -- Pro
 * INSTALLED but UNLICENSED -- the class existed, the licence check passed, and the
 * seam registered with its real renderer.
 *
 * Six entries shipped that way across all three registries, consistently:
 *   shortcodes: vendor_apply, vehicle_submit, vendor_bookings, vendor_profile,
 *               vendor_directory, commission_resolver, messages
 *   blocks:     customer-messages, vendor-profile, vendor-directory
 *   elementor:  MyMessagesWidget, VendorProfileWidget, VendorDirectoryWidget
 * The owner found them by opening the browser: the Shortcode Pages tool listed
 * them all as "Aktif", which was not a mislabel -- they really were live.
 *
 * WHY NOTHING CAUGHT IT
 * ---------------------
 * The Lite suite runs against a tree with NO Pro installed, so class_exists() is
 * false and every seam drops for the wrong reason. "Pro installed but unlicensed"
 * is the one configuration the Lite suite cannot reproduce, and it is the only one
 * where this bug is observable. The fail-closed default is the belt; this test is
 * the braces, and it works by reading the registries as DATA, so it needs no Pro
 * tree at all.
 *
 * WHY A COVERAGE TEST AND NOT JUST THE DEFAULT
 * --------------------------------------------
 * The fail-closed default makes a forgotten key safe (closed) rather than free,
 * but "safe" is not "correct": defaulting messages to 'pro' would close it for a
 * customer whose licence grants `messaging` but not the whole edition. The default
 * prevents a leak; this test prevents the silent mis-gate.
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeServiceProvider::get_shortcode_registry
 * @covers \MHMRentiva\Blocks\BlockRegistry
 * @covers \MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration::pro_widget_classes
 */
final class SeamFeatureKeyCoverageTest extends WP_UnitTestCase
{

    /**
     * Feature keys Mode actually routes. A typo'd key is worse than a missing one:
     * LicenseManager::canUse() would simply never grant it, closing the feature for
     * a paying customer with no error anywhere.
     *
     * Mirrors FeatureTokenIssuer::featuresFor() on the licence server, plus 'pro'
     * for whole-edition seams.
     *
     * @var array<int, string>
     */
    private const KNOWN_FEATURES = array(
        'pro',
        'vendor_marketplace',
        'advanced_reports',
        'messaging',
        'full_rest_api',
        'gdpr_tools',
        'custom_emails',
        'export',
    );

    /**
     * @return array<string, mixed>
     */
    private function shortcode_registry(): array
    {
        // The RAW table on purpose: get_shortcode_registry() returns it with the
        // seams already dropped, so auditing that would scan an empty set and pass
        // vacuously -- which the premise guard below caught when this test was
        // first written against it.
        $provider = ( new ReflectionClass(ShortcodeServiceProvider::class) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod(ShortcodeServiceProvider::class, 'get_raw_shortcode_registry');
        $method->setAccessible(true);

        return (array) $method->invoke($provider);
    }

    public function test_every_shortcode_seam_declares_a_known_feature(): void
    {
        $offenders = array();
        $seams     = 0;

        foreach ($this->shortcode_registry() as $group => $shortcodes) {
            foreach ((array) $shortcodes as $tag => $config) {
                if (empty($config['pro_seam'])) {
                    continue;
                }
                ++$seams;

                $feature = $config['pro_feature'] ?? null;
                if (null === $feature) {
                    $offenders[] = sprintf('%s (group %s): no pro_feature -- would register unlicensed', $tag, $group);
                    continue;
                }
                if (! in_array((string) $feature, self::KNOWN_FEATURES, true)) {
                    $offenders[] = sprintf('%s (group %s): unknown pro_feature "%s"', $tag, $group, (string) $feature);
                }
            }
        }

        $this->assertGreaterThan(0, $seams, 'Premise failed: no pro_seam entries found -- the scan read nothing.');
        $this->assertSame(array(), $offenders, "Shortcode seams that are not gated:\n" . implode("\n", $offenders));
    }

    public function test_every_block_seam_declares_a_known_feature(): void
    {
        $property = new \ReflectionProperty(BlockRegistry::class, 'blocks');
        $property->setAccessible(true);

        $offenders = array();
        $seams     = 0;

        foreach ((array) $property->getValue() as $name => $config) {
            if (empty($config['pro_seam'])) {
                continue;
            }
            ++$seams;

            $feature = $config['pro_feature'] ?? null;
            if (null === $feature) {
                $offenders[] = sprintf('%s: no pro_feature -- would register unlicensed', (string) $name);
                continue;
            }
            if (! in_array((string) $feature, self::KNOWN_FEATURES, true)) {
                $offenders[] = sprintf('%s: unknown pro_feature "%s"', (string) $name, (string) $feature);
            }
        }

        $this->assertGreaterThan(0, $seams, 'Premise failed: no pro_seam blocks found -- the scan read nothing.');
        $this->assertSame(array(), $offenders, "Block seams that are not gated:\n" . implode("\n", $offenders));
    }

    public function test_every_elementor_pro_widget_declares_a_known_feature(): void
    {
        $method = new ReflectionMethod(ElementorIntegration::class, 'pro_widget_classes');
        $method->setAccessible(true);

        $offenders = array();
        $widgets   = (array) $method->invoke(null);

        foreach ($widgets as $class => $feature) {
            if (null === $feature) {
                $offenders[] = sprintf('%s: null feature -- would register unlicensed', (string) $class);
                continue;
            }
            if (! in_array((string) $feature, self::KNOWN_FEATURES, true)) {
                $offenders[] = sprintf('%s: unknown feature "%s"', (string) $class, (string) $feature);
            }
        }

        $this->assertNotSame(array(), $widgets, 'Premise failed: no Pro widgets found -- the scan read nothing.');
        $this->assertSame(array(), $offenders, "Elementor Pro widgets that are not gated:\n" . implode("\n", $offenders));
    }

    /**
     * The three registries must agree on the feature key for the same tag. If the
     * block says 'messaging' and the shortcode says 'pro', a licence granting only
     * messaging registers the block while its backing shortcode stays closed -- and
     * the block then renders through a silenced shortcode, printing nothing.
     */
    public function test_block_and_shortcode_registries_agree_on_each_shared_tag(): void
    {
        $by_tag = array();
        foreach ($this->shortcode_registry() as $shortcodes) {
            foreach ((array) $shortcodes as $tag => $config) {
                if (! empty($config['pro_seam'])) {
                    $by_tag[(string) $tag] = (string) ( $config['pro_feature'] ?? 'pro' );
                }
            }
        }

        $property = new \ReflectionProperty(BlockRegistry::class, 'blocks');
        $property->setAccessible(true);

        $mismatches = array();
        $compared   = 0;

        foreach ((array) $property->getValue() as $name => $config) {
            if (empty($config['pro_seam'])) {
                continue;
            }
            $tag = (string) ( $config['tag'] ?? '' );
            if (! isset($by_tag[$tag])) {
                continue;
            }
            ++$compared;

            $block_feature = (string) ( $config['pro_feature'] ?? 'pro' );
            if ($block_feature !== $by_tag[$tag]) {
                $mismatches[] = sprintf(
                    '%s: block says "%s", shortcode says "%s"',
                    $tag,
                    $block_feature,
                    $by_tag[$tag]
                );
            }
        }

        $this->assertGreaterThan(0, $compared, 'Premise failed: no shared seam tags compared.');
        $this->assertSame(array(), $mismatches, "Registries disagree on a seam's feature:\n" . implode("\n", $mismatches));
    }

    /**
     * Guards the premise of the whole file: if a licence were ever active in this
     * tree, "gated" would stop being observable.
     */
    public function test_premise_this_tree_is_unlicensed(): void
    {
        $this->assertFalse(Mode::isPro(), 'Premise failed: this tree reports a Pro licence.');
        $this->assertFalse(Mode::canUseVendorMarketplace());
        $this->assertFalse(Mode::canUseMessages());
    }
}
