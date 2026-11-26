<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\AddCustomerPage;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersOptimizer;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * The customer sub-pages drawn in PHP (details, edit, add) start with the
 * kit's page header and a back link to their parent, inside the kit shell, so
 * the block under the header takes the shell rhythm instead of a local margin.
 *
 * @covers \MHMRentiva\Admin\Customers\CustomerSubpageHeader
 */
final class CustomerSubpagesStartWithABackLinkTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    private int $customer = 0;

    public function setUp(): void
    {
        parent::setUp();
        CustomerIdentity::flush_memo();
        CustomersOptimizer::clear_cache();
        $actor_id = (int) self::factory()->user->create(array('role' => 'administrator'));
        $this->grant_user_management_privilege($actor_id);
        wp_set_current_user($actor_id);
        $this->customer = (int) self::factory()->user->create(array('role' => 'customer', 'display_name' => 'Deniz Kaya'));
    }

    public function tearDown(): void
    {
        unset($_GET['customer_id']);
        wp_set_current_user(0);
        CustomersOptimizer::clear_cache();
        parent::tearDown();
    }

    private function render(string $method): string
    {
        $_GET['customer_id'] = (string) $this->customer;
        $ref = new \ReflectionMethod(CustomersPage::class, $method);
        $ref->setAccessible(true);

        ob_start();
        $ref->invoke(new CustomersPage());

        return (string) ob_get_clean();
    }

    /** @return array{0: string, 1: string} The back link's href and text. */
    private function backLink(string $html): array
    {
        $this->assertTrue(function_exists('mhmuicore_page_header_html'), 'The ui-core PHP page header is loaded.');
        $this->assertSame(1, preg_match('/<a class="mhmui-page-header__back" href="([^"]+)">(.*?)<\/a>/s', $html, $m), 'The page header draws a back link.');

        return array(html_entity_decode($m[1]), trim(wp_strip_all_tags($m[2])));
    }

    public function test_details_go_back_to_the_customers_list(): void
    {
        $html = $this->render('render_customer_view');

        list( $href, $text ) = $this->backLink($html);
        $this->assertSame(admin_url('admin.php?page=mhm-rentiva-customers'), $href);
        $this->assertStringContainsString('All customers', $text);
        $this->assertMatchesRegularExpression('/<h1 class="mhmui-page-header__title">\s*Customer Details\s*<\/h1>/', $html);
        $this->assertStringContainsString('mhmui-admin mhmui-admin-page', $html);
        $this->assertStringContainsString('rv-cust-scope mhmui-section', $html);
    }

    public function test_edit_goes_back_to_that_customers_details(): void
    {
        $html = $this->render('render_customer_edit');

        list( $href, $text ) = $this->backLink($html);
        $this->assertSame(admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $this->customer), $href);
        $this->assertStringContainsString('Back to customer details', $text);
        $this->assertStringContainsString('rv-cust-scope mhmui-section', $html);
    }

    public function test_add_goes_back_to_the_customers_list(): void
    {
        ob_start();
        AddCustomerPage::render();
        $html = (string) ob_get_clean();

        list( $href ) = $this->backLink($html);
        $this->assertSame(admin_url('admin.php?page=mhm-rentiva-customers'), $href);
        $this->assertStringContainsString('<hr class="wp-header-end">', $html, 'Admin notices keep their anchor under the header.');
    }
}
