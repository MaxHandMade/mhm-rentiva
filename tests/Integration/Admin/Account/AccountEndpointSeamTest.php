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
        WooCommerceIntegration::reset_reserved_query_vars();
    }

    protected function tearDown(): void
    {
        remove_all_filters('mhmrentiva_account_endpoints');
        WooCommerceIntegration::reset_reserved_query_vars();
        parent::tearDown();
    }

    private function subscribe(string ...$slugs): void
    {
        add_filter('mhmrentiva_account_endpoints', static function (array $endpoints) use ($slugs): array {
            return array_merge($endpoints, $slugs);
        });
    }

    /**
     * What WooCommerce actually hands the `woocommerce_get_query_vars` filter:
     * its own query vars. Calling add_query_vars() with an empty array would
     * be a shape the filter never sees, and would hide the fact that an
     * extension must not be able to claim one of WooCommerce's own names.
     *
     * @return array<string, string>
     */
    private function wc_query_vars(): array
    {
        return array(
            'orders'          => 'orders',
            'view-order'      => 'view-order',
            'edit-account'    => 'edit-account',
            'customer-logout' => 'customer-logout',
        );
    }

    public function test_extension_slug_reaches_woocommerce_query_vars(): void
    {
        $this->subscribe(self::FAKE_SLUG);

        $this->assertArrayHasKey(
            self::FAKE_SLUG,
            WooCommerceIntegration::add_query_vars($this->wc_query_vars()),
            'WC_Query::add_endpoints() builds rewrite endpoints from query vars; missing here means a 404.'
        );
    }

    public function test_lite_own_slugs_survive(): void
    {
        $vars = WooCommerceIntegration::add_query_vars($this->wc_query_vars());

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

        // Asserted against the validated slug list rather than add_query_vars()'s
        // return: a name like 'orders' is already in that array because
        // WooCommerce put it there, so its presence would prove nothing about
        // whether the contribution was refused.
        $this->assertNotContains(
            $reserved,
            WooCommerceIntegration::get_extension_endpoint_slugs(array_keys($this->wc_query_vars())),
            sprintf('"%s" is a reserved query var and must never be accepted as an endpoint.', $reserved)
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

        $vars = WooCommerceIntegration::add_query_vars($this->wc_query_vars());

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

        $vars = WooCommerceIntegration::add_query_vars($this->wc_query_vars());

        $this->assertCount(
            1,
            array_filter(array_keys($vars), static fn ($key): bool => $key === $own),
            'A slug Lite already owns must not be registered a second time by the seam.'
        );
    }

    /**
     * The flush trigger hashes the endpoint set to decide whether the rewrite
     * rules need rebuilding. Hashing only Lite's own map means a licence
     * activating -- which changes the endpoint set -- leaves the hash
     * untouched, so the flush the new endpoint needs never runs.
     */
    public function test_flush_hash_changes_when_a_subscriber_joins(): void
    {
        delete_option('mhmrentiva_woocommerce_endpoints_hash');
        delete_option('mhmrentiva_woocommerce_endpoints_flushed');
        delete_option('mhmrentiva_woocommerce_endpoints_version');

        WooCommerceIntegration::maybe_flush_rewrite_rules();
        $without = (string) get_option('mhmrentiva_woocommerce_endpoints_hash', '');

        $this->subscribe(self::FAKE_SLUG);
        WooCommerceIntegration::maybe_flush_rewrite_rules();
        $with = (string) get_option('mhmrentiva_woocommerce_endpoints_hash', '');

        $this->assertNotSame(
            $without,
            $with,
            'A new extension endpoint must change the flush hash, or no flush ever runs for it.'
        );
    }

    /**
     * admin_init fires in wp-admin and admin-ajax only. A licence lapses on
     * whatever request happens to be first, and until someone opens wp-admin
     * the cached rewrite rule keeps matching a URL nothing renders any more.
     */
    public function test_flush_trigger_is_registered_for_front_end_requests_too(): void
    {
        WooCommerceIntegration::register();

        $this->assertNotFalse(
            has_action('wp', array( WooCommerceIntegration::class, 'maybe_flush_rewrite_rules' )),
            'The flush trigger must have a path that runs outside wp-admin.'
        );
    }
}
