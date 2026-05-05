<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Vendor\Profile;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\Core\MetaKeys;

/**
 * Deterministic initials-based avatar fallback for vendors.
 *
 * Replaces the default Gravatar "mystery-man" silhouette with a coloured
 * SVG circle bearing the vendor's initials. Plays nicely with the rest of
 * the WordPress avatar ecosystem:
 *
 *   1. Custom Vendor Avatar (set via VendorProfileExtension media uploader)
 *      — surfaced through {@see VendorProfileProvider::resolve_avatar_url()}
 *      and never reaches this fallback.
 *   2. Real Gravatar — when the vendor's email has a Gravatar registered,
 *      `get_avatar_data` returns the real URL and our filter is a no-op.
 *   3. Third-party avatar plugins (Simple Local Avatars, Avatar Privacy,
 *      WP User Avatar, etc.) hook `get_avatar_data` at default priority 10.
 *      We register at priority 99 so we run after them; if they returned
 *      a non-Gravatar URL we leave it alone.
 *   4. Only when the resolved URL is the Gravatar mystery-man placeholder
 *      do we substitute the SVG.
 *
 * Theme-agnostic — colour comes from a deterministic hash of the user's
 * display name (Slack/Gmail/Material pattern), so each vendor gets a
 * stable unique colour without depending on the host site's brand palette.
 *
 * @since 4.37.2
 */
final class VendorAvatarFallback
{
    /**
     * Default size in CSS pixels for the fallback SVG. Mirrors the medium
     * Gravatar size so the avatar slot doesn't visibly resize when the
     * fallback substitutes for a Gravatar mystery-man.
     */
    private const DEFAULT_SIZE = 96;

    public static function register(): void
    {
        add_filter('get_avatar_data', [self::class, 'maybe_substitute_fallback'], 99, 2);
    }

    /**
     * Replace Gravatar mystery-man placeholder with deterministic SVG when
     * the avatar belongs to a vendor user with no custom avatar set.
     *
     * @param array<string,mixed>  $args
     * @param mixed                $id_or_email
     * @return array<string,mixed>
     */
    public static function maybe_substitute_fallback($args, $id_or_email): array
    {
        if (!is_array($args)) {
            return $args;
        }

        $user_id = self::resolve_user_id($id_or_email);
        if ($user_id <= 0) {
            return $args;
        }

        if (!self::is_vendor($user_id)) {
            return $args;
        }

        // Custom uploaded avatar wins — it's surfaced through the profile
        // provider and never reaches the get_avatar_data filter chain in
        // the same form, but defensively respect it here too.
        $custom_avatar_id = (int) get_user_meta($user_id, MetaKeys::VENDOR_AVATAR_ID, true);
        if ($custom_avatar_id > 0) {
            return $args;
        }

        $current_url = isset($args['url']) ? (string) $args['url'] : '';
        if (!self::is_gravatar_placeholder($current_url)) {
            // A 3rd-party avatar plugin or real Gravatar resolved a real URL.
            // Leave it untouched.
            return $args;
        }

        $size = isset($args['size']) ? (int) $args['size'] : self::DEFAULT_SIZE;
        if ($size <= 0) {
            $size = self::DEFAULT_SIZE;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return $args;
        }

        $args['url']         = self::svg_data_uri((string) $user->display_name, $size);
        $args['found_avatar'] = true;
        return $args;
    }

    /**
     * Build a `data:image/svg+xml;utf8,...` URI carrying the initials avatar.
     * Inline so no external HTTP request is made on profile render.
     */
    public static function svg_data_uri(string $display_name, int $size = self::DEFAULT_SIZE): string
    {
        $initials = self::initials_for($display_name);
        [$bg, $fg] = self::colour_pair_for($display_name);

        $font_size = (int) round($size * 0.42);

        $svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="' . esc_attr($display_name) . '">';
        $svg .= '<rect width="' . $size . '" height="' . $size . '" rx="' . (int) round($size / 2) . '" fill="' . $bg . '"/>';
        $svg .= '<text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif" font-weight="600" font-size="' . $font_size . '" fill="' . $fg . '">' . esc_html($initials) . '</text>';
        $svg .= '</svg>';

        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }

    /**
     * Extract up to two initials from a display name. Handles Turkish
     * dotted/dotless I and other diacritics by uppercasing first and
     * falling back to the first character of each word.
     */
    public static function initials_for(string $display_name): string
    {
        $name = trim($display_name);
        if ($name === '') {
            return '?';
        }

        $words = preg_split('/\s+/u', $name) ?: [];
        $initials = '';
        $taken = 0;
        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $first = function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
            $initials .= function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first);
            $taken++;
            if ($taken === 2) {
                break;
            }
        }
        return $initials !== '' ? $initials : '?';
    }

    /**
     * Pick a (background, foreground) colour pair from a deterministic hash
     * of the display name. Same name → same colour every render.
     *
     * Saturation 55% / lightness 50% gives strong colours that meet WCAG
     * AA contrast against white text at any hue.
     *
     * @return array{0:string,1:string}
     */
    public static function colour_pair_for(string $display_name): array
    {
        $hue = self::hue_for($display_name);
        return [
            self::hsl_to_hex($hue, 55, 50),
            '#ffffff',
        ];
    }

    /**
     * Map a string to a stable hue in the 0-359 range.
     */
    public static function hue_for(string $display_name): int
    {
        $name = $display_name === '' ? '?' : $display_name;
        $hash = crc32($name);
        // crc32 may return a negative int on 32-bit PHP — coerce to unsigned.
        $hash = $hash < 0 ? $hash + 0x1_00_00_00_00 : $hash;
        return (int) ($hash % 360);
    }

    /**
     * HSL to hex conversion with no external dependency.
     */
    private static function hsl_to_hex(int $hue, int $saturation, int $lightness): string
    {
        $h = ($hue % 360) / 360;
        $s = max(0, min(100, $saturation)) / 100;
        $l = max(0, min(100, $lightness)) / 100;

        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue_to_rgb($p, $q, $h + 1 / 3);
            $g = self::hue_to_rgb($p, $q, $h);
            $b = self::hue_to_rgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hue_to_rgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }
        return $p;
    }

    /**
     * Heuristic to recognise the Gravatar "no avatar found" placeholder.
     * Accepts both the Gravatar `d=mm`/`d=mp` querystring style and direct
     * URLs to the placeholder asset paths.
     */
    private static function is_gravatar_placeholder(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        if (strpos($url, 'gravatar.com') === false) {
            return false;
        }
        // Gravatar placeholder query keys: d=mm (mystery-man), d=mp (mystery-person),
        // d=blank (transparent). Real avatars have a hash and no such default fallback hit.
        if (preg_match('/[?&]d=(mm|mp|blank|mysteryman)/i', $url)) {
            return true;
        }
        return false;
    }

    /**
     * Resolve a WP user ID from the variety of $id_or_email shapes
     * `get_avatar_data` may receive.
     *
     * @param mixed $id_or_email
     */
    private static function resolve_user_id($id_or_email): int
    {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }
        if ($id_or_email instanceof \WP_User) {
            return (int) $id_or_email->ID;
        }
        if ($id_or_email instanceof \WP_Comment) {
            return (int) $id_or_email->user_id;
        }
        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? (int) $user->ID : 0;
        }
        return 0;
    }

    /**
     * Cheap check — does this user have the rentiva_vendor role?
     */
    private static function is_vendor(int $user_id): bool
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        return in_array('rentiva_vendor', (array) $user->roles, true);
    }
}
