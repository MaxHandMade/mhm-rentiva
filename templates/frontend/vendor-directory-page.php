<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Outer page wrapper for the Vendor Directory page.
 *
 * Wired in `Plugin.php` via `template_include` priority 99 — pattern parity with
 * `vendor-profile-page.php` (Phase 9 dispatch fix of v4.37.0).
 */

get_header();
?>

<div id="primary" class="content-area primary">
    <main id="main" class="site-main" role="main">
        <article class="ast-article-single mhm-rentiva-vendor-directory-article">
            <div class="entry-content">
                <?php echo do_shortcode('[rentiva_vendor_directory]'); ?>
            </div><!-- .entry-content -->
        </article>
    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
