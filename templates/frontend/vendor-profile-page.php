<?php
/**
 * Public vendor profile page template (rewrite-routed body wrapper).
 *
 * Loaded by VendorProfileRewrite's `template_include` filter when the request
 * matches `/{translated_base}/{slug}/` and the slug resolves to an active
 * vendor. The actual profile body delegates to the canonical
 * `[rentiva_vendor_profile]` shortcode renderer so block, Elementor widget,
 * and URL-routed paths share the exact same HTML.
 *
 * Theme override: copy this file to `<active-theme>/mhm-rentiva/vendor-profile-page.php`.
 *
 * @package Mhm_Rentiva
 * @since   4.37.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$mhm_rentiva_vendor_slug = (string) get_query_var(\MHMRentiva\Admin\Vendor\Profile\VendorProfileRewrite::QUERY_VAR);

get_header();
?>

<div class="mhm-rentiva-vendor-profile-page">
    <main id="primary" class="site-main mhm-rentiva-vendor-profile-main">
        <?php
        echo do_shortcode(
            sprintf('[rentiva_vendor_profile slug="%s"]', esc_attr($mhm_rentiva_vendor_slug))
        );
        ?>
    </main>
</div>

<?php
get_footer();
