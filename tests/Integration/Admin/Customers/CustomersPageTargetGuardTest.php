<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Admin\Customers;

use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Customers\CustomersPage;
use MHMRentiva\Tests\Support\UserManagementCapabilities;
use WP_UnitTestCase;

/**
 * WordPress.org T8 #2, screen side — and a merge tripwire.
 *
 * render_customer_view() and render_customer_edit() take ?customer_id= straight
 * from the URL. Until the T8 round their only gate was the page-level
 * edit_users check in render(), which is a blanket capability: it says the
 * caller may manage users, not WHICH user. So the screens would open, and edit,
 * any account on the site by address alone.
 *
 * WHY THIS TEST EXISTS SEPARATELY FROM THE REST ONES: the Faz 2 branch rewrote
 * these two methods and deleted the `$customer = get_user_by(...)` line the T8
 * guard hangs off. When that branch merges, CustomersPage.php is the single
 * source conflict in the whole merge, and the instinctive resolution -- keep the
 * newer rewrite -- silently deletes the guard. Nothing else would notice: the
 * REST suites cover the routes, not the screens, and no CI runs on either
 * branch. This test is the thing that goes red instead.
 *
 * Both methods are private (they are not part of the admin-page API), so they
 * are invoked by reflection, the same way CustomerUserCapabilityTest already
 * reaches render_customer_edit().
 */
final class CustomersPageTargetGuardTest extends WP_UnitTestCase
{
    use UserManagementCapabilities;

    public function setUp(): void
    {
        parent::setUp();
        CustomerIdentity::flush_memo();
        $actor_id = (int) self::factory()->user->create(array('role' => 'administrator'));
        // The Customers surface is gated on edit_users, which an administrator
        // does not hold on a network -- core rewrites it to do_not_allow for
        // anyone who is not a super admin. Ask for what the mode requires so
        // the assertions below measure this plugin's guard rather than core's
        // capability rewrite. No-op on a single site.
        $this->grant_user_management_privilege($actor_id);
        wp_set_current_user($actor_id);
    }

    public function tearDown(): void
    {
        unset($_GET['customer_id']);
        wp_set_current_user(0);
        parent::tearDown();
    }

    private function invoke(string $method): void
    {
        $ref = new \ReflectionMethod(CustomersPage::class, $method);
        $ref->setAccessible(true);
        $ref->invoke(new CustomersPage());
    }

    /**
     * @return string The wp_die() message, or '' if the method rendered instead.
     */
    private function renderAndCatchDie(string $method, int $customer_id): string
    {
        $_GET['customer_id'] = (string) $customer_id;

        ob_start();
        try {
            $this->invoke($method);

            return '';
        } catch (\WPDieException $e) {
            return $e->getMessage();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function guardedMethods(): array
    {
        return array(
            'view' => array('render_customer_view'),
            'edit' => array('render_customer_edit'),
        );
    }

    /**
     * @dataProvider guardedMethods
     */
    public function test_an_account_that_is_not_a_customer_is_refused(string $method): void
    {
        // An editor: a real account, not ID 1, whose login is not 'admin' -- so the
        // Customers list shows it, and before T8 these screens opened it.
        $bystander = (int) self::factory()->user->create(array('role' => 'editor'));

        $message = $this->renderAndCatchDie($method, $bystander);

        $this->assertNotSame('', $message, "{$method} must refuse an account that is not a customer.");
    }

    /**
     * @dataProvider guardedMethods
     */
    public function test_a_second_administrator_is_refused(string $method): void
    {
        $other_admin = (int) self::factory()->user->create(
            array(
                'role'       => 'administrator',
                'user_login' => 'second_owner',
            )
        );

        $message = $this->renderAndCatchDie($method, $other_admin);

        $this->assertNotSame('', $message, "{$method} must refuse another administrator's account.");
    }

    /**
     * @dataProvider guardedMethods
     */
    public function test_the_refusal_does_not_disclose_whether_the_account_exists(string $method): void
    {
        $bystander = (int) self::factory()->user->create(array('role' => 'editor'));
        $absent    = 999999;

        // Same wording for "not yours" and "not there". A distinct message would
        // turn ?customer_id= into a probe for which user IDs exist.
        $this->assertSame(
            $this->renderAndCatchDie($method, $absent),
            $this->renderAndCatchDie($method, $bystander),
            "{$method} must refuse a foreign account with the same message as a missing one."
        );
    }

    /**
     * @dataProvider guardedMethods
     */
    public function test_a_real_customer_still_opens(string $method): void
    {
        $customer = (int) self::factory()->user->create(array('role' => 'customer'));

        $this->assertSame(
            '',
            $this->renderAndCatchDie($method, $customer),
            "{$method} must still render for a genuine customer -- the guard may not cost the feature its job."
        );
    }

    /**
     * @dataProvider guardedMethods
     */
    public function test_a_denied_per_target_meta_capability_is_honoured(string $method): void
    {
        // The other half of the guard, on its own. On single site edit_user maps
        // straight to edit_users, which the caller has, so without denying the
        // meta cap explicitly this half is untestable and could be deleted with
        // every test still green.
        $customer = (int) self::factory()->user->create(array('role' => 'customer'));

        $deny = static function (array $caps, string $cap, int $user_id, array $args) use ($customer): array {
            if ('edit_user' === $cap && isset($args[0]) && (int) $args[0] === $customer) {
                return array('do_not_allow');
            }

            return $caps;
        };
        add_filter('map_meta_cap', $deny, 10, 4);

        try {
            $message = $this->renderAndCatchDie($method, $customer);
        } finally {
            remove_filter('map_meta_cap', $deny, 10);
        }

        $this->assertNotSame('', $message, "{$method} must honour a denied per-target edit_user capability.");
    }
}
