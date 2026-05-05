<?php
declare(strict_types=1);

namespace MHMRentiva\Tests\Integration\Vendor\Profile;

use MHMRentiva\Admin\Vendor\Profile\VendorAvatarFallback;

/**
 * v4.37.2 regression: deterministic SVG avatar fallback for vendors
 * without a custom avatar and without a real Gravatar.
 *
 * @group vendor-profile
 * @group vendor-avatar
 */
final class VendorAvatarFallbackTest extends \WP_UnitTestCase
{
    public function test_initials_extracts_first_letter_of_first_two_words_uppercased(): void
    {
        $this->assertSame('AY', VendorAvatarFallback::initials_for('Akif Yıldız'));
        $this->assertSame('MÇ', VendorAvatarFallback::initials_for('Mehmet Çelik'));
        $this->assertSame('ZD', VendorAvatarFallback::initials_for('Zeynep Demir'));
    }

    public function test_initials_handles_single_word_names(): void
    {
        $this->assertSame('A', VendorAvatarFallback::initials_for('Akif'));
    }

    public function test_initials_returns_question_mark_for_blank_name(): void
    {
        $this->assertSame('?', VendorAvatarFallback::initials_for(''));
        $this->assertSame('?', VendorAvatarFallback::initials_for('   '));
    }

    public function test_hue_is_deterministic_for_same_name(): void
    {
        $h1 = VendorAvatarFallback::hue_for('Akif Yıldız');
        $h2 = VendorAvatarFallback::hue_for('Akif Yıldız');
        $this->assertSame($h1, $h2, 'Same name must always resolve to the same hue.');
        $this->assertGreaterThanOrEqual(0, $h1);
        $this->assertLessThan(360, $h1);
    }

    public function test_hue_differs_for_different_names(): void
    {
        // Strictly speaking two random strings could collide on a 360-bucket
        // hash, but for the three Turkish vendor display names we seed in
        // smoke tests the hash diverges — pick names known to spread.
        $hues = [
            VendorAvatarFallback::hue_for('Akif Yıldız'),
            VendorAvatarFallback::hue_for('Mehmet Çelik'),
            VendorAvatarFallback::hue_for('Zeynep Demir'),
        ];
        $this->assertCount(3, array_unique($hues), 'These three names should not all collide on the same hue.');
    }

    public function test_svg_data_uri_carries_initials_and_uses_data_url_scheme(): void
    {
        $uri = VendorAvatarFallback::svg_data_uri('Akif Yıldız', 96);
        $this->assertStringStartsWith('data:image/svg+xml;utf8,', $uri);
        $decoded = rawurldecode(substr($uri, strlen('data:image/svg+xml;utf8,')));
        $this->assertStringContainsString('AY', $decoded);
        $this->assertStringContainsString('width="96"', $decoded);
        $this->assertStringContainsString('height="96"', $decoded);
    }

    public function test_substitutes_only_when_url_is_gravatar_mystery_man_for_vendor_user(): void
    {
        VendorAvatarFallback::register();

        $vendor_id = self::factory()->user->create([
            'display_name' => 'Akif Yıldız',
            'role'         => 'rentiva_vendor',
        ]);

        $args = [
            'url'  => 'https://secure.gravatar.com/avatar/abc123?s=96&d=mm',
            'size' => 96,
        ];
        $result = VendorAvatarFallback::maybe_substitute_fallback($args, $vendor_id);

        $this->assertStringStartsWith('data:image/svg+xml;utf8,', $result['url']);
    }

    public function test_passes_through_real_gravatar_url_unchanged(): void
    {
        VendorAvatarFallback::register();

        $vendor_id = self::factory()->user->create([
            'display_name' => 'Akif Yıldız',
            'role'         => 'rentiva_vendor',
        ]);

        $real_url = 'https://secure.gravatar.com/avatar/abc123?s=96';
        $args     = ['url' => $real_url, 'size' => 96];
        $result   = VendorAvatarFallback::maybe_substitute_fallback($args, $vendor_id);

        $this->assertSame($real_url, $result['url'], 'Real Gravatar URL must not be replaced.');
    }

    public function test_passes_through_when_user_is_not_a_vendor(): void
    {
        VendorAvatarFallback::register();

        $non_vendor = self::factory()->user->create(['role' => 'subscriber']);

        $url    = 'https://secure.gravatar.com/avatar/abc?d=mm';
        $args   = ['url' => $url, 'size' => 96];
        $result = VendorAvatarFallback::maybe_substitute_fallback($args, $non_vendor);

        $this->assertSame($url, $result['url'], 'Non-vendor users keep their original avatar URL.');
    }
}
