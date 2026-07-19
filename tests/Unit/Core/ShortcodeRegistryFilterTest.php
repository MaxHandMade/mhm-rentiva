<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Unit\Core;

use MHMRentiva\Admin\Core\ShortcodeServiceProvider;
use ReflectionClass;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * `mhm_rentiva_shortcodes` seam inversion (companion to BlockRegistryFilterTest).
 *
 * Lite no longer declares the 11 Pro shortcode tags (the whole `vendor` group,
 * `transfer` group, `rentiva_commission_resolver`/`rentiva_vendor_ledger` in
 * `account`, and `rentiva_messages` in `support`) at all -- they are contributed
 * by Pro's own `ShortcodeExtensions` filter subscriber, gated by
 * `\MHMRentiva\Pro\Edition`, not Lite's `Mode`/`pro_seam`/`pro_feature`.
 *
 * @covers \MHMRentiva\Admin\Core\ShortcodeServiceProvider::get_registry
 */
final class ShortcodeRegistryFilterTest extends WP_UnitTestCase
{
    private const PRO_TAGS = array(
        'rentiva_vendor_apply',
        'rentiva_vehicle_submit',
        'rentiva_vendor_bookings',
        'rentiva_vendor_profile',
        'rentiva_vendor_directory',
        'rentiva_commission_resolver',
        'rentiva_vendor_ledger',
        'rentiva_transfer_search',
        'rentiva_transfer_results',
        'rentiva_popular_routes',
        'rentiva_messages',
    );

    protected function tearDown(): void
    {
        remove_all_filters('mhm_rentiva_shortcodes');
        parent::tearDown();
    }

    /**
     * @return array<string, array<string, array>>
     */
    private function registry(): array
    {
        // Sidesteps the singleton and its constructor -- same pattern already used
        // by UnlicensedProSeamGateTest/SeamFeatureKeyCoverageTest in this suite.
        $provider = ( new ReflectionClass(ShortcodeServiceProvider::class) )->newInstanceWithoutConstructor();
        $method   = new ReflectionMethod(ShortcodeServiceProvider::class, 'get_registry');
        $method->setAccessible(true);

        return (array) $method->invoke($provider);
    }

    public function test_lite_has_no_pro_shortcodes_and_no_seam_keys(): void
    {
        $registry = $this->registry();

        $all_tags = array();
        foreach ($registry as $group => $shortcodes) {
            foreach ($shortcodes as $tag => $config) {
                $all_tags[] = $tag;
                $this->assertArrayNotHasKey('pro_seam', $config, "$group.$tag");
                $this->assertArrayNotHasKey('pro_feature', $config, "$group.$tag");
            }
        }

        foreach (self::PRO_TAGS as $pro_tag) {
            $this->assertNotContains($pro_tag, $all_tags, $pro_tag);
        }

        $this->assertContains('rentiva_booking_form', $all_tags, 'A core Lite tag must still be present.');
    }

    public function test_filter_admits_a_subscriber_shortcode(): void
    {
        add_filter(
            'mhm_rentiva_shortcodes',
            static function (array $registry): array {
                $registry['demo']['rentiva_x_demo'] = array(
                    'class'         => \MHMRentiva\Admin\Frontend\Shortcodes\ContactForm::class,
                    'requires_auth' => false,
                );
                return $registry;
            }
        );

        $registry = $this->registry();

        $this->assertArrayHasKey('demo', $registry);
        $this->assertArrayHasKey('rentiva_x_demo', $registry['demo']);
    }
}
