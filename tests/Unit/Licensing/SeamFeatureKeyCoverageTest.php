<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Licensing;

use MHMRentiva\Admin\Frontend\Widgets\Elementor\ElementorIntegration;
use MHMRentiva\Admin\Licensing\Mode;
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

    // ShortcodeServiceProvider's own pro_seam declarations were removed by the
    // `mhm_rentiva_shortcodes` seam inversion: Lite's get_raw_shortcode_registry()
    // carries zero Pro entries now (they moved to Pro's own ShortcodeExtensions
    // filter subscriber, gated via \MHMRentiva\Pro\Edition, not
    // Mode/pro_seam/pro_feature). A shortcode seam feature-key audit therefore
    // belongs in Pro's own suite going forward -- there is nothing left to scan
    // here. See mhm-rentiva/tests/Unit/Core/ShortcodeRegistryFilterTest.php and
    // mhm-rentiva-pro/tests/Integration/Pro/ShortcodeExtensionsTest.php.

    // BlockRegistry's own pro_seam declarations were removed by the
    // `mhm_rentiva_blocks` seam inversion: Lite's self::$blocks carries zero Pro
    // entries now (they moved to Pro's own BlockExtensions filter subscriber,
    // gated via \MHMRentiva\Pro\Edition, not Mode/pro_seam/pro_feature). A block
    // seam feature-key audit therefore belongs in Pro's own suite going forward
    // -- there is nothing left to scan here. See
    // mhm-rentiva/tests/Unit/Blocks/BlockRegistryFilterTest.php and
    // mhm-rentiva-pro/tests/Integration/Pro/BlockExtensionsTest.php.

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

    // The block-vs-shortcode feature-key agreement check (formerly
    // test_block_and_shortcode_registries_agree_on_each_shared_tag) was removed
    // for the same reason as above: BlockRegistry no longer declares pro_seam
    // entries in Lite, so there is no block-side feature key left to compare
    // against the shortcode registry's.

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
