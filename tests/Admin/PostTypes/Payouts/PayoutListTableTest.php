<?php

declare(strict_types=1);

namespace MHMRentiva\Tests\Admin\PostTypes\Payouts;

use MHMRentiva\Admin\PostTypes\Payouts\PayoutListTable;
use WP_UnitTestCase;

class PayoutListTableTest extends WP_UnitTestCase
{
    private int $admin_id;

    public function set_up()
    {
        parent::set_up();

        $_POST = array();

        $this->admin_id = $this->factory->user->create(array('role' => 'administrator'));
        $admin_user     = new \WP_User($this->admin_id);
        $admin_user->add_cap('mhm_rentiva_approve_payout');

        wp_set_current_user($this->admin_id);
    }

    public function tear_down()
    {
        $_POST = array();
        wp_set_current_user(0);

        parent::tear_down();
    }

    /** @test */
    public function it_requires_a_valid_nonce_for_bulk_approval()
    {
        $_POST['payout_ids'] = array('15');

        $result = PayoutListTable::process_bulk_approve();

        $this->assertSame(0, $result['approved']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(array('Security check failed.'), $result['errors']);
    }

    /** @test */
    public function it_returns_an_empty_result_when_no_payout_ids_are_selected()
    {
        $_POST['_wpnonce'] = wp_create_nonce('bulk-payouts');

        $result = PayoutListTable::process_bulk_approve();

        $this->assertSame(0, $result['approved']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(array(), $result['errors']);
    }
}
