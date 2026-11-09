<?php
declare(strict_types=1);
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.

/**
 * My Account - Dashboard Template (PR D, Task 3)
 *
 * Greeting and one line under it, the next-rental card, the upcoming list
 * (five rows, "View all"), and -- last -- the privacy controls. The KPI strip
 * and the quick actions are gone (R14).
 *
 * @var array $data AccountRenderer::get_dashboard_data()['data'].
 */

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Core\Utilities\Templates;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsQuery;
use MHMRentiva\Admin\Frontend\Account\AccountBookingsView;
use MHMRentiva\Admin\Frontend\Account\AccountController;
use MHMRentiva\Admin\Frontend\Account\AccountRenderer;
use MHMRentiva\Admin\Frontend\Shortcodes\Core\AbstractShortcode;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

$navigation    = $data['navigation'] ?? array();
$is_integrated = empty($navigation);
$user          = $data['user'] ?? wp_get_current_user();
$title_id      = wp_unique_id('rvs-acc-');

$vehicles_url = \MHMRentiva\Admin\Core\ShortcodeUrlManager::get_page_url('rentiva_vehicles_list');
if (! $vehicles_url) {
	$vehicles_url = get_post_type_archive_link('mhmrentiva_vehicle');
}
if (! $vehicles_url) {
	$vehicles_url = home_url('/');
}
// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public filter name kept for backward compatibility.
$vehicles_url = (string) apply_filters('mhmrentiva_vehicles_page_url', $vehicles_url);

$booking_rows = array_map(array( AccountRenderer::class, 'booking_view_row' ), (array) ( $data['upcoming_posts'] ?? array() ));
$list         = array(
	'rows'          => AccountBookingsView::rows($booking_rows),
	'counts'        => (array) ( $data['counts'] ?? array() ),
	'segment'       => AccountBookingsQuery::UPCOMING,
	'show_segments' => false,
	'has_bookings'  => (bool) ( $data['has_bookings'] ?? false ),
	'show_image'    => true,
	'show_dates'    => true,
	'show_status'   => true,
	'show_price'    => true,
	'show_pager'    => false,
	'current_page'  => 1,
	'total_pages'   => 1,
	'vehicles_url'  => $vehicles_url,
);

$bookings_url = function_exists('wc_get_account_endpoint_url')
	? \wc_get_account_endpoint_url(AccountController::get_endpoint_slug('bookings', 'rentiva-bookings'))
	: '';

$own_shell  = ! AbstractShortcode::in_rvs_shell();
$root_class = 'rvs-account mhm-rentiva-account-page' . ( $is_integrated ? ' mhm-integrated' : '' );
?>
<?php if ($own_shell) : ?>
<div class="rvs-shell <?php echo esc_attr($root_class); ?>" data-rvs-shortcode="rentiva_my_account">
<?php else : ?>
<div class="<?php echo esc_attr($root_class); ?>">
<?php endif; ?>
	<?php if (! $is_integrated) : ?>
		<?php echo wp_kses_post(Templates::render('account/navigation', array( 'navigation' => $navigation ), true)); ?>
	<?php endif; ?>

	<header class="rvs-account__head">
		<h1 id="<?php echo esc_attr($title_id); ?>" class="rvs-account__title">
			<?php
			/* translators: %s: the customer's first name (or display name). */
			echo esc_html(sprintf(__('Hello %s', 'mhm-rentiva'), (string) ( $data['greeting_name'] ?? '' )));
			?>
		</h1>
		<p class="rvs-account__sub"><?php echo esc_html( (string) ( $data['subline'] ?? '' )); ?></p>
	</header>

	<?php if (! empty($data['next_rental'])) : ?>
		<?php Templates::render('partials/rvs/account-next-rental', array( 'card' => $data['next_rental'] )); ?>
	<?php endif; ?>

	<?php Templates::render('partials/rvs/account-bookings', $list); ?>
	<?php if (! empty($list['has_bookings']) && '' !== $bookings_url) : ?>
		<p class="rvs-account__more"><a href="<?php echo esc_url($bookings_url); ?>"><?php esc_html_e('View all', 'mhm-rentiva'); ?> &rarr;</a></p>
	<?php endif; ?>

	<?php
	// The export / withdraw-consent / delete controls are backed by AJAX
	// handlers that ship with the separate add-on. Render them only when that
	// add-on is active (its handlers are registered); on the free plugin alone
	// they would be non-functional, so omit them rather than show dead buttons.
	$gdpr_enabled = SettingsCore::get('mhmrentiva_customer_gdpr_compliance', '1');
	if ('1' === $gdpr_enabled && has_action('wp_ajax_mhmrentiva_data_export')) :
		?>
		<section class="rvs-account__section rvs-account-privacy" aria-labelledby="<?php echo esc_attr($title_id . '-privacy'); ?>">
			<h2 id="<?php echo esc_attr($title_id . '-privacy'); ?>"><?php esc_html_e('Privacy Controls', 'mhm-rentiva'); ?></h2>
			<p class="rvs-account__sub"><?php esc_html_e('Manage your personal data and privacy settings', 'mhm-rentiva'); ?></p>
			<div class="rvs-account__actions">
				<button type="button" id="export-data" class="rvs-btn rvs-btn--secondary"><?php esc_html_e('Export My Data', 'mhm-rentiva'); ?></button>
				<button type="button" id="withdraw-consent" class="rvs-btn rvs-btn--secondary"><?php esc_html_e('Withdraw Consent', 'mhm-rentiva'); ?></button>
				<button type="button" id="delete-account" class="rvs-btn rvs-btn--secondary rvs-account-privacy__danger"><?php esc_html_e('Request Data Erasure', 'mhm-rentiva'); ?></button>
			</div>
		</section>
	<?php endif; ?>
</div>
