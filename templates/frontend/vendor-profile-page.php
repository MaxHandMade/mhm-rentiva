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
$mhm_rentiva_directory_url = home_url('/' . \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase::resolve() . '/');

get_header();
?>

<div id="primary" class="content-area primary">
    <main id="main" class="site-main" role="main">
        <article class="ast-article-single mhm-rentiva-vendor-profile-article">
            <div class="entry-content">

                <nav class="mhm-vendor-profile-back-nav" aria-label="<?php echo esc_attr__('Breadcrumb', 'mhm-rentiva'); ?>">
                    <a href="<?php echo esc_url($mhm_rentiva_directory_url); ?>">
                        ← <?php echo esc_html__('All vendors', 'mhm-rentiva'); ?>
                    </a>
                </nav>

                <?php
                echo do_shortcode(
                    sprintf('[rentiva_vendor_profile slug="%s"]', esc_attr($mhm_rentiva_vendor_slug))
                );
                ?>

            </div><!-- .entry-content -->
        </article>
    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
