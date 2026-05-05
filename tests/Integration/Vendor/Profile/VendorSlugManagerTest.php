<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Core\MetaKeys;
use MHMRentiva\Admin\Vendor\Profile\VendorSlugManager;

/**
 * @group vendor-profile
 * @group vendor-slug
 */
final class VendorSlugManagerTest extends \WP_UnitTestCase
{
    public function test_generate_slug_from_display_name_simple(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);

        $slug = VendorSlugManager::generate_for_user($user_id);

        $this->assertSame('akif-otomotiv', $slug);
    }

    public function test_generate_slug_strips_turkish_diacritics_to_ascii(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Ötömötiv Şirketi']);

        $slug = VendorSlugManager::generate_for_user($user_id);

        $this->assertSame('akif-otomotiv-sirketi', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug, 'slug must be ASCII lowercase + digits + dash');
    }

    public function test_generate_slug_falls_back_to_user_login_when_display_name_empty(): void
    {
        $user_id = self::factory()->user->create([
            'user_login'   => 'vendor99',
            'display_name' => '',
        ]);

        $slug = VendorSlugManager::generate_for_user($user_id);

        $this->assertSame('vendor99', $slug);
    }

    public function test_assign_slug_handles_collision_with_suffix(): void
    {
        $user_a = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        $user_b = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);

        $slug_a = VendorSlugManager::assign_slug($user_a);
        $slug_b = VendorSlugManager::assign_slug($user_b);

        $this->assertSame('akif-otomotiv', $slug_a);
        $this->assertSame('akif-otomotiv-2', $slug_b);
    }

    public function test_assign_slug_persists_to_user_meta(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);

        VendorSlugManager::assign_slug($user_id);

        $this->assertSame('akif-otomotiv', get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true));
    }

    public function test_assign_slug_idempotent_keeps_existing_slug(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'custom-slug');

        $slug = VendorSlugManager::assign_slug($user_id);

        $this->assertSame('custom-slug', $slug, 'should not regenerate when slug already set');
    }

    public function test_assign_slug_collision_increments_until_free(): void
    {
        // Pre-occupy slugs 1, 2, 3
        self::factory()->user->create(['user_login' => 'u1', 'meta_input' => [MetaKeys::VENDOR_SLUG => 'akif-otomotiv']]);
        self::factory()->user->create(['user_login' => 'u2', 'meta_input' => [MetaKeys::VENDOR_SLUG => 'akif-otomotiv-2']]);
        self::factory()->user->create(['user_login' => 'u3', 'meta_input' => [MetaKeys::VENDOR_SLUG => 'akif-otomotiv-3']]);

        $new_user = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        $slug = VendorSlugManager::assign_slug($new_user);

        $this->assertSame('akif-otomotiv-4', $slug);
    }

    public function test_change_slug_appends_old_to_history(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        VendorSlugManager::assign_slug($user_id);  // akif-otomotiv

        VendorSlugManager::change_slug($user_id, 'akif-rent-a-car');

        $this->assertSame('akif-rent-a-car', get_user_meta($user_id, MetaKeys::VENDOR_SLUG, true));
        $history = (array) get_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, true);
        $this->assertSame(['akif-otomotiv'], $history);
    }

    public function test_history_caps_at_10_entries(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'V']);
        update_user_meta($user_id, MetaKeys::VENDOR_SLUG, 'current');
        for ($i = 1; $i <= 12; $i++) {
            VendorSlugManager::change_slug($user_id, 'slug-' . $i);
        }
        $history = (array) get_user_meta($user_id, MetaKeys::VENDOR_SLUG_HISTORY, true);
        $this->assertCount(10, $history);
        $this->assertSame('slug-11', $history[0], 'newest history entry first');
    }

    public function test_find_user_by_history_slug(): void
    {
        $user_id = self::factory()->user->create(['display_name' => 'Akif Otomotiv']);
        VendorSlugManager::assign_slug($user_id);
        VendorSlugManager::change_slug($user_id, 'akif-rent-a-car');

        $found = VendorSlugManager::find_user_by_history_slug('akif-otomotiv');

        $this->assertSame($user_id, $found);
    }
}
