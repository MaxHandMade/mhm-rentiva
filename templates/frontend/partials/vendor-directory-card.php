<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $vendor */

$badge_label = '';
if (( $vendor['badge_status'] ?? '' ) === 'verified') {
    $badge_label = __('✓ Verified vendor', 'mhm-rentiva');
} elseif (( $vendor['badge_status'] ?? '' ) === 'new') {
    $badge_label = __('New vendor', 'mhm-rentiva');
}

$avatar_src = '';
$avatar_id  = (int) ( $vendor['avatar_id'] ?? 0 );
if ($avatar_id > 0) {
    $avatar_src = (string) ( wp_get_attachment_image_url($avatar_id, 'thumbnail') ?: '' );
}
if ($avatar_src === '') {
    $avatar_src = \MHMRentiva\Admin\Vendor\Profile\VendorAvatarFallback::svg_data_uri( (string) $vendor['display_name'], 96);
}
$avatar_is_svg = strpos($avatar_src, 'data:image/svg+xml') === 0;

$rating_avg   = (float) ( $vendor['rating_avg'] ?? 0 );
$rating_count = (int) ( $vendor['rating_count'] ?? 0 );
?>
<a class="mhm-vendor-directory-card-link" href="<?php echo esc_url( (string) $vendor['profile_url']); ?>">
    <article class="mhm-vendor-directory-card">
        <div class="mhm-vendor-directory-card-avatar">
            <img src="<?php echo $avatar_is_svg ? esc_attr($avatar_src) : esc_url($avatar_src); ?>" alt="<?php echo esc_attr( (string) $vendor['display_name']); ?>" width="64" height="64" loading="lazy">
        </div>
        <div class="mhm-vendor-directory-card-body">
            <h3 class="mhm-vendor-directory-card-name"><?php echo esc_html( (string) $vendor['display_name']); ?></h3>

            <p class="mhm-vendor-directory-card-meta">
                <?php if (!empty($vendor['city'])) : ?>
                    <span class="mhm-vendor-directory-card-city"><?php echo esc_html( (string) $vendor['city']); ?></span>
                    <span aria-hidden="true">·</span>
                <?php endif; ?>
                <span class="mhm-vendor-directory-card-vehicle-count">
                    <?php
                    $vc = (int) ( $vendor['vehicle_count'] ?? 0 );
                    /* translators: %d: vehicle count */
                    echo esc_html(sprintf(_n('%d vehicle', '%d vehicles', $vc, 'mhm-rentiva'), $vc));
                    ?>
                </span>
            </p>

            <?php if ($badge_label !== '') : ?>
                <p class="mhm-vendor-directory-card-badge mhm-vendor-directory-card-badge-<?php echo esc_attr( (string) $vendor['badge_status']); ?>">
                    <?php echo esc_html($badge_label); ?>
                </p>
            <?php endif; ?>

            <?php if ($rating_count > 0) : ?>
                <p class="mhm-vendor-directory-card-rating" aria-label="
                <?php
                    /* translators: 1: rating average, 2: rating count */
                    echo esc_attr(sprintf(__('%1$s out of 5 (%2$d ratings)', 'mhm-rentiva'), number_format_i18n($rating_avg, 1), $rating_count));
                ?>
                ">
                    <span aria-hidden="true">★ <?php echo esc_html(number_format_i18n($rating_avg, 1)); ?></span>
                    <span class="mhm-vendor-directory-card-rating-count">
                        (
                        <?php
                        /* translators: %d: rating count */
                        echo esc_html(sprintf(_n('%d rating', '%d ratings', $rating_count, 'mhm-rentiva'), $rating_count));
                        ?>
                        )
                    </span>
                </p>
            <?php endif; ?>

            <span class="mhm-vendor-directory-card-cta"><?php echo esc_html__('View profile →', 'mhm-rentiva'); ?></span>
        </div>
    </article>
</a>
