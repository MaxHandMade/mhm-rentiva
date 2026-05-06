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
<main class="mhm-vendor-directory-page" role="main">
    <?php echo do_shortcode('[rentiva_vendor_directory]'); ?>
</main>
<?php
get_footer();
