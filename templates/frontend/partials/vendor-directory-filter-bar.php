<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $data */
/** @var array<string, mixed> $query_args */

$action_url = home_url('/' . \MHMRentiva\Admin\Vendor\Directory\VendorDirectoryUrlBase::resolve() . '/');
?>
<form class="mhm-vendor-directory-filter-bar" method="get" action="<?php echo esc_url($action_url); ?>">

    <details class="mhm-vendor-directory-filter-mobile-toggle" open>
        <summary><?php echo esc_html__('Filter', 'mhm-rentiva'); ?></summary>
    </details>

    <div class="mhm-vendor-directory-filter-inner">
        <label class="mhm-vendor-directory-filter">
            <span class="mhm-vendor-directory-filter-label"><?php echo esc_html__('City', 'mhm-rentiva'); ?></span>
            <select name="city">
                <option value=""><?php echo esc_html__('All cities', 'mhm-rentiva'); ?></option>
                <?php foreach ($data['city_pool'] as $city) : ?>
                    <option value="<?php echo esc_attr($city); ?>" <?php selected($query_args['city'] ?? '', $city); ?>>
                        <?php echo esc_html($city); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="mhm-vendor-directory-filter">
            <span class="mhm-vendor-directory-filter-label"><?php echo esc_html__('Badge', 'mhm-rentiva'); ?></span>
            <select name="badge">
                <option value="" <?php selected($query_args['badge'] ?? '', ''); ?>><?php echo esc_html__('All vendors', 'mhm-rentiva'); ?></option>
                <option value="verified" <?php selected($query_args['badge'] ?? '', 'verified'); ?>><?php echo esc_html__('Verified only', 'mhm-rentiva'); ?></option>
                <option value="new" <?php selected($query_args['badge'] ?? '', 'new'); ?>><?php echo esc_html__('New only', 'mhm-rentiva'); ?></option>
            </select>
        </label>

        <label class="mhm-vendor-directory-filter">
            <span class="mhm-vendor-directory-filter-label"><?php echo esc_html__('Minimum rating', 'mhm-rentiva'); ?></span>
            <select name="min_rating">
                <option value="0" <?php selected( (int) ( $query_args['min_rating'] ?? 0 ), 0); ?>><?php echo esc_html__('All ratings', 'mhm-rentiva'); ?></option>
                <option value="3" <?php selected( (int) ( $query_args['min_rating'] ?? 0 ), 3); ?>><?php echo esc_html__('3+ stars', 'mhm-rentiva'); ?></option>
                <option value="4" <?php selected( (int) ( $query_args['min_rating'] ?? 0 ), 4); ?>><?php echo esc_html__('4+ stars', 'mhm-rentiva'); ?></option>
                <option value="5" <?php selected( (int) ( $query_args['min_rating'] ?? 0 ), 5); ?>><?php echo esc_html__('5 stars', 'mhm-rentiva'); ?></option>
            </select>
        </label>

        <label class="mhm-vendor-directory-filter">
            <span class="mhm-vendor-directory-filter-label"><?php echo esc_html__('Sort by', 'mhm-rentiva'); ?></span>
            <select name="sort">
                <option value="rating" <?php selected($query_args['sort'] ?? 'rating', 'rating'); ?>><?php echo esc_html__('Highest rated', 'mhm-rentiva'); ?></option>
                <option value="newest" <?php selected($query_args['sort'] ?? 'rating', 'newest'); ?>><?php echo esc_html__('Newest', 'mhm-rentiva'); ?></option>
                <option value="alpha" <?php selected($query_args['sort'] ?? 'rating', 'alpha'); ?>><?php echo esc_html__('A → Z', 'mhm-rentiva'); ?></option>
            </select>
        </label>

        <button type="submit" class="mhm-vendor-directory-filter-submit"><?php echo esc_html__('Apply', 'mhm-rentiva'); ?></button>
    </div><!-- .mhm-vendor-directory-filter-inner -->
</form>
