<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\About\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\About\Tabs\GeneralTab;
use MHMRentiva\Admin\About\Tabs\SupportTab;
use MHMRentiva\Admin\About\Tabs\SystemTab;
use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\Settings\Core\SettingsCore;

/**
 * REST endpoint for the About admin page.
 *
 * Route:  GET /mhm-rentiva/v1/about
 * Auth:   manage_options
 */
final class AboutController {

	private const REST_NAMESPACE = 'mhm-rentiva/v1';
	private const ROUTE          = '/about';
	private const CONTACT_PHONE  = '+90 538 556 4158';

	public static function register(): void
	{
		add_action( 'rest_api_init', array( self::class, 'register_route' ) );
	}

	public static function register_route(): void
	{
		register_rest_route(
			self::REST_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'check_permission' ),
			)
		);
	}

	public static function check_permission(): bool
	{
		return current_user_can( 'manage_options' );
	}

	public static function handle( \WP_REST_Request $_request ): \WP_REST_Response
	{
		return new \WP_REST_Response(
			array(
				'general'   => GeneralTab::get_data(),
				'features'  => array(
					'comparison' => Mode::get_comparison_table_data(),
				),
				'system'    => SystemTab::get_data(),
				'support'   => self::build_support(),
				'developer' => self::build_developer(),
			)
		);
	}

	private static function build_support(): array
	{
		return array(
			'is_pro'        => Mode::isPro(),
			'support_email' => 'support@wpalemi.com',
			'phone'         => apply_filters( 'mhm_rentiva_contact_phone', self::CONTACT_PHONE ),
			'links'         => array(
				'docs'          => 'https://maxhandmade.github.io/mhm-rentiva-docs/',
				'api_docs'      => 'https://maxhandmade.github.io/mhm-rentiva-docs/docs/api/overview',
				'youtube'       => 'https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA',
				'contact_form'  => 'https://wpalemi.com/support/',
				'wp_forum'      => 'https://wordpress.org/support/plugin/mhm-rentiva',
				'github_issues' => 'https://github.com/MaxHandMade/mhm-rentiva/issues',
			),
			'changelog'     => SupportTab::get_changelog(),
		);
	}

	private static function build_developer(): array
	{
		return array(
			'company_website' => 'https://wpalemi.com',
			'support_email'   => 'support@wpalemi.com',
			'phone'           => apply_filters( 'mhm_rentiva_contact_phone', self::CONTACT_PHONE ),
			'logo_url'        => MHM_RENTIVA_PLUGIN_URL . 'assets/images/mhm-logo.png',
		);
	}
}
