<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Messages\REST\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use MHMRentiva\Admin\REST\Helpers\AuthHelper;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Auth {

	/**
	 * Admin permission check
	 */
	public static function adminPermissionsCheck( WP_REST_Request $request ): bool {
		return AuthHelper::adminPermissionsCheck( $request );
	}

	/**
	 * Customer authentication check (WordPress User)
	 *
	 * @deprecated 4.0.0 Token system removed, using WordPress user auth
	 */
	public static function verifyCustomerToken( string $token ): ?array {
		// This method is no longer used - kept for backward compatibility
		return null;
	}

	/**
	 * Special authorization check for Messages
	 *
	 * @param WP_REST_Request $request REST request object
	 * @param int             $message_id Message ID
	 * @return bool|WP_Error True if successful, WP_Error if error
	 */
	public static function verifyMessageAuth( WP_REST_Request $request, int $message_id = 0 ): bool|WP_Error {
		return AuthHelper::verifyAuth( $request, $message_id, 'messages' );
	}
}
