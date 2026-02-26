<?php

namespace MHMRentiva\Tests\Integration\Admin\Services;

use MHMRentiva\Admin\Services\CompareService;

class CompareServiceTest extends \WP_UnitTestCase
{
    private $user_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->user_id = $this->factory->user->create();
        wp_set_current_user($this->user_id);

        // Clear static cache in CompareService to ensure test isolation
        $reflection = new \ReflectionClass(CompareService::class);
        $property = $reflection->getProperty('cached_list');
        $property->setAccessible(true);
        $property->setValue(null, null);

        // Clear any existing meta
        delete_user_meta($this->user_id, 'mhm_rentiva_compare');
    }

    public function test_add_and_get_list()
    {
        $vehicle_id = 123;
        $result = CompareService::add($vehicle_id);

        $this->assertTrue($result);
        $list = CompareService::get_list();
        $this->assertContains($vehicle_id, $list);
        $this->assertCount(1, $list);
    }

    public function test_add_duplicate()
    {
        $vehicle_id = 123;
        CompareService::add($vehicle_id);
        $result = CompareService::add($vehicle_id); // Add again

        $this->assertTrue($result); // Should return true (success/already exists)
        $list = CompareService::get_list();
        $this->assertCount(1, $list);
    }

    public function test_remove()
    {
        $vehicle_id = 123;
        CompareService::add($vehicle_id);

        $result = CompareService::remove($vehicle_id);
        $this->assertTrue($result);

        $list = CompareService::get_list();
        $this->assertNotContains($vehicle_id, $list);
        $this->assertCount(0, $list);
    }

    public function test_max_limit()
    {
        // Max limit is 3
        CompareService::add(1);
        CompareService::add(2);
        CompareService::add(3);

        $this->expectException(\Exception::class);
        CompareService::add(4);
    }

    public function test_is_in_compare()
    {
        $vehicle_id = 999;
        $this->assertFalse(CompareService::is_in_compare($vehicle_id));

        CompareService::add($vehicle_id);
        $this->assertTrue(CompareService::is_in_compare($vehicle_id));
    }
}
