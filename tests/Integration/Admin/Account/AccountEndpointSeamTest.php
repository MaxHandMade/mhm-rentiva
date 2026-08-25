<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Account;

use MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration;
use WP_UnitTestCase;

/**
 * `mhmrentiva_account_endpoints` used to be read only by
 * AccountController::add_endpoints(), which returns early when WooCommerce is
 * active -- that is, never on a real deployment. A subscriber's endpoint was
 * therefore advertised in the My Account nav while no rewrite rule and no
 * WooCommerce query var existed for it, and every such tab 404'd.
 *
 * Lite now feeds the seam into WooCommerce's own query vars, and
 * WC_Query::add_endpoints() turns each query var into a rewrite endpoint with
 * the mask WooCommerce picks for this site (class-wc-query.php:206-214).
 *
 * Lite's suite never loads the add-on, so what is pinned here is the
 * CONTRACT, exercised through a fake extension. The behaviour of the real
 * subscriber belongs in the add-on's own suite.
 *
 * @covers \MHMRentiva\Admin\Frontend\Account\WooCommerceIntegration
 */
final class AccountEndpointSeamTest extends WP_UnitTestCase
{
    private const FAKE_SLUG = 'zzz-fake-extension-endpoint';

    protected function setUp(): void
    {
        parent::setUp();
        remove_all_filters('mhmrentiva_account_endpoints');
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_account_endpoints');
        parent::tearDown();
    }

    private function subscribe(string ...$slugs): void
    {
        add_filter('mhmrentiva_account_endpoints', static function (array $endpoints) use ($slugs): array {
            return array_merge($endpoints, $slugs);
        });
    }

    public function test_extension_slug_reaches_woocommerce_query_vars(): void
    {
        $this->subscribe(self::FAKE_SLUG);

        $this->assertArrayHasKey(
            self::FAKE_SLUG,
            WooCommerceIntegration::add_query_vars(array()),
            'WC_Query::add_endpoints() builds rewrite endpoints from query vars; missing here means a 404.'
        );
    }

    public function test_lite_own_slugs_survive(): void
    {
        $vars = WooCommerceIntegration::add_query_vars(array());

        foreach (array( 'bookings', 'favorites', 'payment_history' ) as $key) {
            $this->assertArrayHasKey(WooCommerceIntegration::get_endpoint_slug($key), $vars);
        }
    }

    /**
     * add_rewrite_endpoint() validates nothing and calls $wp->add_query_var()
     * unconditionally (wp-includes/class-wp-rewrite.php:1752-1764). A
     * subscriber contributing a core query var would break every permalink on
     * the site -- and this code ships on WordPress.org.
     *
     * @dataProvider reserved_slug_provider
     */
    public function test_reserved_query_vars_are_dropped(string $reserved): void
    {
        $this->subscribe($reserved);

        $this->assertArrayNotHasKey(
            $reserved,
            WooCommerceIntegration::add_query_vars(array()),
            sprintf('"%s" is a reserved query var and must never be registered as an endpoint.', $reserved)
        );
    }

    /** @return array<int, array<int, string>> */
    public static function reserved_slug_provider(): array
    {
        return array(
            array( 'name' ),
            array( 'pagename' ),
            array( 'page' ),
            array( 'feed' ),
            array( 'p' ),
            array( 'orders' ),
        );
    }

    public function test_malformed_contributions_are_dropped(): void
    {
        add_filter('mhmrentiva_account_endpoints', static function (array $endpoints): array {
            $endpoints[] = '';
            $endpoints[] = array( 'not', 'a', 'string' );
            $endpoints[] = 42;
            return $endpoints;
        });

        $vars = WooCommerceIntegration::add_query_vars(array());

        $this->assertArrayNotHasKey('', $vars);
        $this->assertArrayNotHasKey('42', $vars);
    }

    /**
     * A contribution Lite already owns must not register a second time.
     */
    public function test_duplicate_of_a_lite_slug_is_dropped(): void
    {
        $own = WooCommerceIntegration::get_endpoint_slug('bookings');
        $this->subscribe($own);

        $vars = WooCommerceIntegration::add_query_vars(array());

        $this->assertCount(
            1,
            array_filter(array_keys($vars), static fn ($key): bool => $key === $own),
            'A slug Lite already owns must not be registered a second time by the seam.'
        );
    }
}
