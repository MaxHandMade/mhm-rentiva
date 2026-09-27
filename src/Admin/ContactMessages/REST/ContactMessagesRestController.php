<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\ContactMessages\REST;

if (! defined('ABSPATH')) {
	exit;
}

use MHMRentiva\Admin\ContactMessages\ContactMessageRepository;
use MHMRentiva\Admin\ContactMessages\ContactStatus;
use MHMRentiva\Admin\Customers\CustomerIdentity;
use MHMRentiva\Admin\Frontend\Shortcodes\ContactMessagePostType;

/**
 * REST surface of the Contact Messages screen. Every route is
 * manage_options -- the same capability the post type maps every action to --
 * and every route re-checks the record's type, so another type's ID is a 404.
 */
final class ContactMessagesRestController {

	private const NS = 'mhm-rentiva/v1';

	public static function register_routes(): void
	{
		$can = static fn(): bool => current_user_can('manage_options');
		$id  = array(
			'id' => array(
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => true,
			),
		);

		register_rest_route(self::NS, '/contact-messages', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_list' ),
			'permission_callback' => $can,
			'args'                => array(
				'status'   => array(
					'type'    => 'string',
					'enum'    => array( '', 'new', 'read', 'replied', 'trash' ),
					'default' => '',
				),
				'type'     => array(
					'type'    => 'string',
					'enum'    => array_merge(array( '' ), ContactMessagePostType::TYPES),
					'default' => '',
				),
				'period'   => array(
					'type'              => 'string',
					'default'           => '',
					'validate_callback' => static fn($v): bool => is_string($v) && ( in_array($v, array( '', '7d', '30d' ), true) || 1 === preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ),
				),
				'search'   => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'page'     => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
				'per_page' => array(
					'type'    => 'integer',
					'default' => 20,
					'minimum' => 1,
					'maximum' => 100,
				),
			),
		));

		register_rest_route(self::NS, '/contact-messages/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_item' ),
			'permission_callback' => $can,
			'args'                => $id,
		));

		register_rest_route(self::NS, '/contact-messages/(?P<id>\d+)/technical', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( self::class, 'get_technical' ),
			'permission_callback' => $can,
			'args'                => $id,
		));

		self::register_write_routes($can, $id);
	}

	/**
	 * Write routes (Task 5). Kept as a separate method so this task compiles alone.
	 *
	 * @param callable $can
	 * @param array<string,mixed> $id
	 */
	private static function register_write_routes(callable $can, array $id): void
	{
		register_rest_route(self::NS, '/contact-messages/(?P<id>\d+)/status', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'set_status' ),
			'permission_callback' => $can,
			'args'                => $id + array(
				'status' => array(
					'type'     => 'string',
					'enum'     => ContactStatus::ALL,
					'required' => true,
				),
			),
		));

		register_rest_route(self::NS, '/contact-messages/(?P<id>\d+)/read', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'mark_read' ),
			'permission_callback' => $can,
			'args'                => $id,
		));

		register_rest_route(self::NS, '/contact-messages/bulk', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( self::class, 'bulk' ),
			'permission_callback' => $can,
			'args'                => array(
				'ids'    => array(
					'type'     => 'array',
					'items'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'minItems' => 1,
					'maxItems' => 100,
					'required' => true,
				),
				'action' => array(
					'type'     => 'string',
					'enum'     => array( 'read', 'replied', 'trash', 'restore', 'delete' ),
					'required' => true,
				),
			),
		));

		register_rest_route(self::NS, '/contact-messages/(?P<id>\d+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( self::class, 'delete_item' ),
			'permission_callback' => $can,
			'args'                => $id + array(
				'force' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		));
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function set_status(\WP_REST_Request $request)
	{
		$post = self::find( (int) $request['id']);
		if ($post instanceof \WP_Error) {
			return $post;
		}
		ContactStatus::set($post->ID, (string) $request['status']);
		return new \WP_REST_Response(self::row($post));
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function mark_read(\WP_REST_Request $request)
	{
		$post = self::find( (int) $request['id']);
		if ($post instanceof \WP_Error) {
			return $post;
		}
		if (ContactStatus::STATUS_NEW === ContactStatus::get($post->ID)) {
			ContactStatus::set($post->ID, ContactStatus::STATUS_READ);
		}
		return new \WP_REST_Response(self::row($post));
	}

	public static function bulk(\WP_REST_Request $request): \WP_REST_Response
	{
		$action  = (string) $request['action'];
		$results = array();

		foreach (array_unique(array_map('intval', (array) $request['ids'])) as $id) {
			$post = self::find($id);
			if ($post instanceof \WP_Error) {
				$results[] = array(
					'id'    => $id,
					'ok'    => false,
					'error' => 'not_found',
				);
				continue;
			}

			switch ($action) {
				case 'read':
					// Same rule as mark_read(): reading a message never downgrades an
					// already-replied one back to unread.
					if (ContactStatus::STATUS_NEW === ContactStatus::get($id)) {
						ContactStatus::set($id, ContactStatus::STATUS_READ);
					}
					$ok = true;
					break;
				case 'replied':
					$ok = ContactStatus::set($id, $action);
					break;
				case 'trash':
					$ok = 'trash' !== $post->post_status && false !== wp_trash_post($id);
					break;
				case 'restore':
					$ok = self::untrash($id);
					break;
				default: // delete
					$ok = 'trash' === $post->post_status && false !== wp_delete_post($id, true);
			}

			$results[] = $ok ? array(
				'id' => $id,
				'ok' => true,
			) : array(
				'id'    => $id,
				'ok'    => false,
				'error' => 'not_allowed',
			);
		}

		ContactStatus::forget_badge();

		return new \WP_REST_Response(array( 'results' => $results ));
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function delete_item(\WP_REST_Request $request)
	{
		$post = self::find( (int) $request['id']);
		if ($post instanceof \WP_Error) {
			return $post;
		}

		if ( (bool) $request['force']) {
			if ('trash' !== $post->post_status) {
				return new \WP_Error('rest_not_in_trash', __('Only a message in the trash can be deleted permanently.', 'mhm-rentiva'), array( 'status' => 400 ));
			}
			$deleted = false !== wp_delete_post($post->ID, true);
			ContactStatus::forget_badge();
			return new \WP_REST_Response(array(
				'id'      => $post->ID,
				'trashed' => false,
				'deleted' => $deleted,
			));
		}

		$trashed = false !== wp_trash_post($post->ID);
		ContactStatus::forget_badge();

		// A site with EMPTY_TRASH_DAYS = 0 makes wp_trash_post() delete permanently
		// instead of changing status, so "trashed" success does not mean the record
		// still exists -- check for real before reporting which one happened.
		if ($trashed && null === get_post($post->ID)) {
			return new \WP_REST_Response(array(
				'id'      => $post->ID,
				'trashed' => false,
				'deleted' => true,
			));
		}

		return new \WP_REST_Response(array(
			'id'      => $post->ID,
			'trashed' => $trashed,
			'deleted' => false,
		));
	}

	/**
	 * Restore to the status the message had before it was trashed (private).
	 * Core's default is `draft`, which would drop it from every tab.
	 */
	public static function untrash(int $id): bool
	{
		if (ContactMessagePostType::TYPE !== get_post_type($id)) {
			return false;
		}
		if ('trash' !== get_post_status($id)) {
			return false;
		}

		$had = false !== has_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status');
		if (! $had) {
			add_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3);
		}
		try {
			return false !== wp_untrash_post($id);
		} finally {
			if (! $had) {
				remove_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10);
			}
		}
	}

	public static function get_list(\WP_REST_Request $request): \WP_REST_Response
	{
		$per_page = (int) $request['per_page'];
		$result   = ContactMessageRepository::list(array(
			'status'   => (string) $request['status'],
			'type'     => (string) $request['type'],
			'period'   => (string) $request['period'],
			'search'   => (string) $request['search'],
			'page'     => (int) $request['page'],
			'per_page' => $per_page,
		));

		$items = array();
		foreach ($result['items'] as $post_id) {
			$post = get_post($post_id);
			if ($post instanceof \WP_Post) {
				$items[] = self::row($post);
			}
		}

		return new \WP_REST_Response(array(
			'items'  => $items,
			'total'  => $result['total'],
			'pages'  => (int) max(1, ceil($result['total'] / $per_page)),
			'page'   => (int) $request['page'],
			'counts' => ContactMessageRepository::counts(),
			'stats'  => ContactMessageRepository::stats(),
		));
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function get_item(\WP_REST_Request $request)
	{
		$post = self::find( (int) $request['id']);
		return $post instanceof \WP_Error ? $post : new \WP_REST_Response(self::detail($post));
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function get_technical(\WP_REST_Request $request)
	{
		$post = self::find( (int) $request['id']);
		if ($post instanceof \WP_Error) {
			return $post;
		}

		$response = new \WP_REST_Response(array(
			'ip_address' => (string) get_post_meta($post->ID, '_mhmrentiva_contact_ip_address', true),
			'user_agent' => (string) get_post_meta($post->ID, '_mhmrentiva_contact_user_agent', true),
		));
		$response->header('Cache-Control', 'no-store, private');

		return $response;
	}

	/** @return \WP_Post|\WP_Error */
	public static function find(int $id)
	{
		$post = get_post($id);
		if (! $post instanceof \WP_Post || ContactMessagePostType::TYPE !== $post->post_type) {
			return new \WP_Error('rest_post_invalid_id', __('Invalid message ID.', 'mhm-rentiva'), array( 'status' => 404 ));
		}

		return $post;
	}

	/** @return array<string,mixed> */
	public static function row(\WP_Post $post): array
	{
		$meta   = static fn(string $k): string => (string) get_post_meta($post->ID, '_mhmrentiva_contact_' . $k, true);
		$name   = $meta('name');
		$email  = $meta('email');
		$type   = $meta('type');
		$status = ContactStatus::get($post->ID);
		$text   = trim(wp_specialchars_decode(wp_strip_all_tags($post->post_content)));

		return array(
			'id'             => $post->ID,
			'name'           => $name,
			'email'          => $email,
			'email_linkable' => self::email_linkable($email),
			'initials'       => self::initials($name),
			'type'           => in_array($type, ContactMessagePostType::TYPES, true) ? $type : 'general',
			'type_label'     => ContactMessagePostType::type_label($type),
			'snippet'        => mb_substr(preg_replace('/\s+/u', ' ', $text) ?? '', 0, 140),
			'vehicle'        => self::vehicle( (int) $meta('vehicle_id')),
			'has_attachment' => '' !== $meta('attachment'),
			'rating'         => max(0, min(5, (int) $meta('rating'))),
			'status'         => $status,
			'status_label'   => ContactStatus::label($status),
			// mysql_to_rfc3339() emits no UTC offset; gmdate('c', ...) does, and clients need it to compare dates reliably.
			'date_iso'       => gmdate('c', (int) strtotime($post->post_date_gmt . ' UTC')),
			'date_label'     => wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) strtotime($post->post_date_gmt . ' UTC')),
			'trashed'        => 'trash' === $post->post_status,
		);
	}

	/** @return array<string,mixed> */
	public static function detail(\WP_Post $post): array
	{
		$row  = self::row($post);
		$meta = static fn(string $k): string => (string) get_post_meta($post->ID, '_mhmrentiva_contact_' . $k, true);

		$fields = array();
		$add    = static function (string $key, string $label, string $value) use (&$fields): void {
			if ('' !== $value && '0' !== $value) {
				$fields[] = array(
					'key'   => $key,
					'label' => $label,
					'value' => $value,
				);
			}
		};
		$add('type', __('Enquiry type', 'mhm-rentiva'), $row['type_label']);
		$add('priority', __('Priority', 'mhm-rentiva'), ContactMessagePostType::priority_label($meta('priority')));
		$add('submitted', __('Submitted', 'mhm-rentiva'), $row['date_label']);
		$add('preferred_date', __('Preferred date', 'mhm-rentiva'), $meta('preferred_date'));
		$add('company', __('Company', 'mhm-rentiva'), $meta('company'));
		$add('rating', __('Rating', 'mhm-rentiva'), $row['rating'] > 0 ? sprintf('%d/5', $row['rating']) : '');

		$attachment = null;
		$url        = $meta('attachment');
		if ('' !== $url) {
			$base       = (string) ( wp_upload_dir()['baseurl'] ?? '' );
			$attachment = array(
				'name'         => sanitize_file_name(wp_basename( (string) wp_parse_url($url, PHP_URL_PATH))),
				'download_url' => ( '' !== $base && str_starts_with($url, trailingslashit($base)) ) ? esc_url_raw($url) : null,
			);
		}

		return array_merge($row, array(
			'content'    => trim(wp_specialchars_decode(wp_strip_all_tags($post->post_content))),
			'fields'     => $fields,
			'attachment' => $attachment,
			'sender'     => array(
				'email'          => $row['email'],
				'email_linkable' => $row['email_linkable'],
				'phone'          => $meta('phone'),
				'customer_url'   => self::customer_url($row['email']),
				'other_count'    => ContactMessageRepository::other_count($row['email'], $post->ID),
			),
		));
	}

	/** The is_email() check alone is not enough: it accepts `?&%=#` in the local part, and such an address would inject mailto: query fields. */
	private static function email_linkable(string $email): bool
	{
		if (! is_email($email)) {
			return false;
		}
		$local = (string) strstr($email, '@', true);
		return 1 !== preg_match('/[?&%=#]/', $local);
	}

	private static function initials(string $name): string
	{
		$parts = preg_split('/\s+/u', trim($name)) ?: array();
		$out   = '';
		foreach (array_slice(array_filter($parts), 0, 2) as $part) {
			$out .= mb_substr($part, 0, 1);
		}
		return mb_strtoupper($out);
	}

	/** @return array{id:int,title:string}|null */
	private static function vehicle(int $id): ?array
	{
		return $id > 0 && 'mhmrentiva_vehicle' === get_post_type($id)
			? array(
				'id'    => $id,
				'title' => get_the_title($id),
			)
			: null;
	}

	private static function customer_url(string $email): ?string
	{
		if ('' === $email) {
			return null;
		}
		$user = get_user_by('email', $email);
		if (! $user instanceof \WP_User || ! CustomerIdentity::is_customer($user->ID) || ! current_user_can('edit_user', $user->ID)) {
			return null;
		}
		return admin_url('admin.php?page=mhm-rentiva-customers&action=view&customer_id=' . $user->ID);
	}
}
