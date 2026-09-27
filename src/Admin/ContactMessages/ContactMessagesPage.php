<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

/**
 * Contact Messages admin screen (React). Replaces the core list and edit
 * screens of the private contact post type; plain visits to those screens
 * are redirected here, core actions posted to them still run.
 */
final class ContactMessagesPage {

	use \MHMRentiva\Admin\Core\Traits\AdminHelperTrait;

	public const SLUG = 'mhm-rentiva-contact-messages';

	public static function register(): void
	{
		add_action('admin_enqueue_scripts', array( self::class, 'enqueue_assets' ));
		add_action('load-edit.php', array( self::class, 'maybe_redirect_legacy_list' ));
		add_action('load-post.php', array( self::class, 'maybe_redirect_legacy_post' ));
		foreach (array( 'save_post_' . ContactMessagePostType::TYPE, 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook) {
			add_action($hook, array( ContactStatus::class, 'forget_badge' ), 10, 0);
		}
	}

	/** Submenu title with core's count bubble; the count query runs only for users who can see it. */
	public static function menu_title(): string
	{
		$label = __('Contact Messages', 'mhm-rentiva');
		if (! current_user_can('manage_options')) {
			return $label;
		}
		$n = ContactMessageRepository::new_count();
		if ($n <= 0) {
			return $label;
		}

		return sprintf(
			'%1$s <span class="awaiting-mod count-%2$d"><span class="pending-count" aria-hidden="true">%3$s</span><span class="screen-reader-text">%4$s</span></span>',
			esc_html($label),
			$n,
			esc_html(number_format_i18n($n)),
			/* translators: %s: number of new contact messages. */
			esc_html(sprintf(_n('%s new message', '%s new messages', $n, 'mhm-rentiva'), number_format_i18n($n)))
		);
	}

	public function render(): void
	{
		if (! current_user_can('manage_options')) {
			return;
		}

		// PHP prints the heading and the wp-header-end marker (house standard,
		// AdminHelperTrait): admin notices are moved there at DOM-ready, before
		// React mounts. React's PageHeader renders at level 2 below it.
		echo '<div class="wrap mhm-rentiva-wrap contact-messages-page">';
		$this->render_admin_header(
			(string) get_admin_page_title(),
			array(
				array(
					'type' => 'documentation',
					'url'  => \MHMRentiva\Admin\Core\Utilities\UXHelper::get_docs_url(),
				),
			),
			true,
			__( 'Requests sent through the contact form on your site', 'mhm-rentiva' )
		);
		echo '<div id="mhm-contact-messages-root"></div></div>';
	}

	public static function enqueue_assets(string $hook): void
	{
		if (false === strpos($hook, self::SLUG)) {
			return;
		}

		\MHMRentiva\Admin\Core\AssetManager::enqueue_react_page('contact-messages');
		\MHMRentiva\Admin\Core\AssetManager::enqueue_kit('admin');

		wp_enqueue_style(
			'mhm-rentiva-contact-messages',
			MHMRENTIVA_PLUGIN_URL . 'build/admin/contact-messages.css',
			array(),
			\MHMRentiva\Admin\Core\AssetManager::get_file_version('build/admin/contact-messages.css')
		);

		$types = array();
		foreach (ContactMessagePostType::TYPES as $type) {
			$types[ $type ] = ContactMessagePostType::type_label($type);
		}

		wp_localize_script('mhm-rentiva-react-contact-messages', 'mhmRentivaContactMessages', array(
			'types'   => $types,
			'pageUrl' => admin_url('admin.php?page=' . self::SLUG),
		));
	}

	/**
	 * The same test WP_List_Table::current_action() applies, read from the
	 * request because the list table does not exist yet at load-edit.php.
	 *
	 * @param array<string,mixed> $query
	 */
	public static function legacy_list_should_redirect(array $query, string $typenow): bool
	{
		if (ContactMessagePostType::TYPE !== $typenow) {
			return false;
		}
		// current_action() (core's WP_List_Table) treats filter_action as "present"
		// only when it is non-empty; a blank ?filter_action= (the filter form
		// submitted with no value picked) is still a plain viewing request.
		if (( isset($query['filter_action']) && '' !== (string) $query['filter_action'] ) || isset($query['delete_all']) || isset($query['delete_all2'])) {
			return false;
		}
		foreach (array( 'action', 'action2' ) as $key) {
			$value = isset($query[ $key ]) ? (string) $query[ $key ] : '';
			if ('' !== $value && '-1' !== $value) {
				return false;
			}
		}
		return true;
	}

	/** @param array<string,mixed> $query */
	public static function legacy_post_redirect_id(array $query): int
	{
		$action = isset($query['action']) ? (string) $query['action'] : '';
		$id     = isset($query['post']) ? absint($query['post']) : 0;
		if (( '' !== $action && 'edit' !== $action ) || $id <= 0) {
			return 0;
		}
		return ContactMessagePostType::TYPE === get_post_type($id) ? $id : 0;
	}

	public static function maybe_redirect_legacy_list(): void
	{
		global $typenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing decision from $_REQUEST (same source as WP_List_Table::current_action()); no state changes here.
		$query = (array) wp_unslash($_REQUEST);
		if (self::legacy_list_should_redirect($query, (string) $typenow)) {
			$trash = isset($query['post_status']) && 'trash' === $query['post_status'];
			wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . ( $trash ? '&status=trash' : '' )));
			exit;
		}
	}

	public static function maybe_redirect_legacy_post(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing decision from $_REQUEST (same source as WP_List_Table::current_action()); no state changes here.
		$id = self::legacy_post_redirect_id( (array) wp_unslash($_REQUEST));
		if ($id > 0) {
			wp_safe_redirect(ContactMessagePostType::admin_detail_url($id));
			exit;
		}
	}
}
