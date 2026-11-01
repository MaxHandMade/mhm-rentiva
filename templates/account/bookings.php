<?php
declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * My Account - Bookings (PR D, Task 2).
 *
 * The list itself is templates/partials/rvs/account-bookings.php. On a
 * WooCommerce endpoint this template writes the `rvs-` shell; inside the
 * [rentiva_my_bookings] shortcode (block, widget) the shortcode wrote it
 * already ($rvs_shell), so there is no second one.
 *
 * @var array $data AccountRenderer::get_bookings_data() / get_bookings_preview_data().
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsView;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;

$navigation    = $data['navigation'] ?? array();
$is_integrated = empty($navigation);

// An editor preview hands its sample rows ready-made in `booking_rows`;
// otherwise they are built from the booking posts.
$booking_rows = isset($data['booking_rows']) && is_array($data['booking_rows'])
	? $data['booking_rows']
	: array_map(array( AccountRenderer::class, 'booking_view_row' ), (array) ( $data['bookings'] ?? array() ));

$counts = (array) ( $data['counts'] ?? array() );

$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
if (! $vehicles_url) {
	$vehicles_url = get_post_type_archive_link('mhmrentiva_vehicle');
}
if (! $vehicles_url) {
	$vehicles_url = home_url('/');
}
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public filter name kept for backward compatibility.
$vehicles_url = (string) apply_filters('mhmrentiva_vehicles_page_url', $vehicles_url);

$list = array(
	'rows'          => AccountBookingsView::rows($booking_rows),
	'counts'        => $counts,
	'segment'       => (string) ( $data['segment'] ?? '' ),
	'show_segments' => (bool) ( $data['show_segments'] ?? false ),
	'has_bookings'  => (bool) ( $data['has_bookings'] ?? array() !== $booking_rows ),
	// The thumbnail column belonged to pages without WooCommerce's menu.
	'show_image'    => ! $is_integrated && (bool) ( $data['show_image'] ?? true ),
	'show_dates'    => (bool) ( $data['show_booking_dates'] ?? true ),
	'show_status'   => (bool) ( $data['show_status'] ?? true ),
	'show_price'    => (bool) ( $data['show_price'] ?? true ),
	'show_pager'    => ! empty($data['show_pagination']) && (int) ( $data['total_pages'] ?? 1 ) > 1,
	'current_page'  => (int) ( $data['current_page'] ?? 1 ),
	'total_pages'   => (int) ( $data['total_pages'] ?? 1 ),
	'vehicles_url'  => $vehicles_url,
);

$own_shell = ! AbstractShortcode::in_rvs_shell();
// `mhm-integrated`: inside WooCommerce's menu (no own navigation), as before.
$root_class = 'rvs-account mhm-rentiva-account-page' . ( $is_integrated ? ' mhm-integrated' : '' );
?>
<?php if ($own_shell) : ?>
<div class="rvs-shell <?php echo esc_attr($root_class); ?>" data-rvs-shortcode="rentiva_my_bookings">
<?php else : ?>
<div class="<?php echo esc_attr($root_class); ?>">
<?php endif; ?>
	<?php if (! $is_integrated) : ?>
		<?php echo wp_kses_post(Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>
	<?php Templates::render('partials/rvs/account-bookings', $list); ?>
</div>
