<?php

namespace MHMRentiva\Admin\Tests\Integration;

use MHMRentiva\Admin\Services\FavoritesService;
use WP_UnitTestCase;

class FavoritesServiceTest extends WP_UnitTestCase
{
    private $user_id;
    private $vehicle_id;

    public function setUp(): void
    {
        parent::setUp();
        $this->user_id = (int) $this->factory->user->create();
        $this->vehicle_id = (int) $this->factory->post->create(['post_type' => 'vehicle']);

        // Ensure meta is clean
        delete_user_meta($this->user_id, 'mhm_rentiva_favorites');
    }

    public function test_add_favorite()
    {
        $result = FavoritesService::add($this->user_id, $this->vehicle_id);
        $this->assertTrue($result);

        $favorites = get_user_meta($this->user_id, 'mhm_rentiva_favorites', true);
        $this->assertContains($this->vehicle_id, $favorites);
    }

    public function test_remove_favorite()
    {
        FavoritesService::add($this->user_id, $this->vehicle_id);

        $result = FavoritesService::remove($this->user_id, $this->vehicle_id);
        $this->assertTrue($result);

        $favorites = get_user_meta($this->user_id, 'mhm_rentiva_favorites', true);
        $this->assertNotContains($this->vehicle_id, $favorites);
    }

    public function test_is_favorite()
    {
        $this->assertFalse(FavoritesService::is_favorite($this->user_id, $this->vehicle_id));

        FavoritesService::add($this->user_id, $this->vehicle_id);
        $this->assertTrue(FavoritesService::is_favorite($this->user_id, $this->vehicle_id));
    }

    public function test_get_user_favorites()
    {
        $v1 = $this->factory->post->create(['post_type' => 'vehicle']);
        $v2 = $this->factory->post->create(['post_type' => 'vehicle']);

        FavoritesService::add($this->user_id, $v1);
        FavoritesService::add($this->user_id, $v2);

        $favorites = FavoritesService::get_user_favorites($this->user_id);

        $this->assertCount(2, $favorites);
        $this->assertContains($v1, $favorites);
        $this->assertContains($v2, $favorites);
    }

    public function test_add_duplicate_favorite()
    {
        FavoritesService::add($this->user_id, $this->vehicle_id);
        $result = FavoritesService::add($this->user_id, $this->vehicle_id);

        // Should return true (already there)
        $this->assertTrue($result);

        $favorites = FavoritesService::get_user_favorites($this->user_id);
        $this->assertCount(1, $favorites); // Should not duplicate
    }
}
