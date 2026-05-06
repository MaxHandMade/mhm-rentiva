<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** @var array<string, mixed> $data */

$current = (int) $data['pagination']['current'];
$total   = (int) $data['pagination']['total_pages'];

$big   = 999999999;
$links = paginate_links([
    'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
    'format'    => '?paged=%#%',
    'current'   => max(1, $current),
    'total'     => $total,
    'prev_text' => '«',
    'next_text' => '»',
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- public listing filter, no state mutation.
    'add_args'  => array_filter([
        'city'       => isset($_GET['city']) ? sanitize_text_field(wp_unslash( (string) $_GET['city'])) : '',
        'badge'      => isset($_GET['badge']) ? sanitize_text_field(wp_unslash( (string) $_GET['badge'])) : '',
        'min_rating' => isset($_GET['min_rating']) ? absint(wp_unslash($_GET['min_rating'])) : 0,
        'sort'       => isset($_GET['sort']) ? sanitize_text_field(wp_unslash( (string) $_GET['sort'])) : '',
    ]),
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
]);

if (!empty($links)) :
    ?>
<nav class="mhm-vendor-directory-pagination" aria-label="
	<?php
    /* translators: 1: current page, 2: total pages */
    echo esc_attr(sprintf(__('Page %1$d of %2$d', 'mhm-rentiva'), $current, $total));
	?>
">
    <?php echo wp_kses_post(is_array($links) ? implode(' ', $links) : (string) $links); ?>
</nav>
<?php endif; ?>
