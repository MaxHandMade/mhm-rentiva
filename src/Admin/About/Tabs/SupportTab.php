<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\About\Tabs;

use MHMRentiva\Admin\Licensing\Mode;
use MHMRentiva\Admin\About\Helpers;
use MHMRentiva\Admin\Core\Tabs\AbstractTab;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support tab
 */
final class SupportTab extends AbstractTab {


	protected static function get_tab_id(): string {
		return 'support';
	}

	protected static function get_tab_title(): string {
		return __( 'Support', 'mhm-rentiva' );
	}

	protected static function get_tab_description(): string {
		return __( 'Support channels, documentation and version history', 'mhm-rentiva' );
	}

	protected static function get_tab_content( array $data = array() ): array {
		// If no data is passed, get the changelog
		if ( empty( $data ) ) {
			$data = self::get_changelog();
		}

		return array(
			'title'       => self::get_tab_title(),
			'description' => self::get_tab_description(),
			'sections'    => array(
				array(
					'type'          => 'custom',
					'custom_render' => array( self::class, 'render_support_cards' ),
				),
				array(
					'type'          => 'custom',
					'title'         => __( 'Version History', 'mhm-rentiva' ),
					'custom_render' => array( self::class, 'render_changelog' ),
				),
			),
		);
	}

	/**
	 * Support cards render
	 */
	public static function render_support_cards( array $section, array $data = array() ): void {
		echo '<div class="support-grid">';

		// Documentation card
		$company_website = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_company_website();

		echo '<div class="support-card">';
		echo '<h3>' . esc_html__( 'Documentation', 'mhm-rentiva' ) . '</h3>';
		echo '<p>' . esc_html__( 'Detailed user guides, video tutorials and API documentation.', 'mhm-rentiva' ) . '</p>';
		echo '<div class="support-links">';
		echo wp_kses_post(
			Helpers::render_external_link(
				'https://maxhandmade.github.io/mhm-rentiva-docs/',
				esc_html__( 'User Guide', 'mhm-rentiva' ),
				array( 'class' => 'button button-secondary' )
			)
		);
		echo wp_kses_post(
			Helpers::render_external_link(
				'https://maxhandmade.github.io/mhm-rentiva-docs/docs/developer/rest-api/',
				esc_html__( 'API Documentation', 'mhm-rentiva' ),
				array( 'class' => 'button button-secondary' )
			)
		);
		echo wp_kses_post(
			Helpers::render_external_link(
				'https://www.youtube.com/channel/UC3qBE6ZCCEc8ugFUYXwtcpA',
				esc_html__( 'Video Tutorials', 'mhm-rentiva' ),
				array( 'class' => 'button button-secondary' )
			)
		);
		echo '</div>';
		echo '</div>';

		$support_email = \MHMRentiva\Admin\Settings\Core\SettingsCore::get_support_email();

		// Support channels card
		echo '<div class="support-card">';
		echo '<h3>' . esc_html__( 'Support Channels', 'mhm-rentiva' ) . '</h3>';
		echo '<p>' . esc_html__( 'Contact us for your questions.', 'mhm-rentiva' ) . '</p>';
		echo '<div class="support-links">';
		echo wp_kses_post(
			Helpers::render_external_link(
				'https://maxhandmade.com/iletisim/',
				esc_html__( 'Contact Form', 'mhm-rentiva' ),
				array( 'class' => 'button button-primary' )
			)
		);

		if ( Mode::isPro() ) {
			echo wp_kses_post(
				Helpers::render_external_link(
					'mailto:' . $support_email,
					esc_html__( 'Priority Support', 'mhm-rentiva' ),
					array( 'class' => 'button button-secondary' )
				)
			);
		}

		echo '<div class="contact-info">';
		echo '<p><strong>' . esc_html__( 'Email:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $support_email ) . '</p>';
		$phone_number = apply_filters( 'mhm_rentiva_contact_phone', __( '+90 538 556 4158', 'mhm-rentiva' ) );
		echo '<p><strong>' . esc_html__( 'Phone:', 'mhm-rentiva' ) . '</strong> ' . esc_html( $phone_number ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

		// Community card
		echo '<div class="support-card">';
		echo '<h3>' . esc_html__( 'Community', 'mhm-rentiva' ) . '</h3>';
		echo '<p>' . esc_html__( 'Share your experiences with other users.', 'mhm-rentiva' ) . '</p>';
		echo '<div class="support-links">';
		echo wp_kses_post(
			Helpers::render_external_link(
				'https://wordpress.org/support/plugin/mhm-rentiva',
				esc_html__( 'WordPress Support Forum', 'mhm-rentiva' ),
				array( 'class' => 'button button-secondary dashicons-before dashicons-wordpress' )
			)
		);
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Changelog render
	 */
	public static function render_changelog( array $section, array $data = array() ): void {
		$changelog = self::get_changelog();

		echo '<div class="changelog-list">';

		if ( ! empty( $changelog ) ) {
			foreach ( $changelog as $release ) {
				echo '<div class="changelog-item ' . esc_attr( $release['type'] ?? '' ) . '">';
				echo '<div class="changelog-header">';
				echo '<div class="version-info">';
				echo '<strong>v' . esc_html( $release['version'] ) . '</strong>';
				echo '<span class="release-date">' . esc_html( $release['date'] ) . '</span>';

				if ( ( 'current' === ( $release['type'] ?? '' ) ) ) {
					echo '<span class="current-badge">' . esc_html__( 'Current Version', 'mhm-rentiva' ) . '</span>';
				}

				echo '</div>';
				echo '</div>';
				echo '<div class="changelog-content">';
				echo '<ul>';

				foreach ( $release['changes'] as $change ) {
					echo '<li>' . esc_html( $change ) . '</li>';
				}

				echo '</ul>';
				echo '</div>';
				echo '</div>';
			}
		} else {
			echo '<p>' . esc_html__( 'Version history information not found.', 'mhm-rentiva' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Get the changelog
	 */
	public static function get_changelog(): array {
		// Detect current WordPress locale
		$locale = get_locale();

		// Use Turkish changelog if locale is Turkish
		$changelog_filename = 'changelog.json';
		if ( strpos( $locale, 'tr_' ) === 0 ) {
			$changelog_filename = 'changelog-tr.json';
		}

		$changelog_file = MHM_RENTIVA_PLUGIN_DIR . $changelog_filename;

		if ( ! file_exists( $changelog_file ) ) {
			// Fallback to default changelog.json if localized version doesn't exist
			$changelog_file = MHM_RENTIVA_PLUGIN_DIR . 'changelog.json';

			if ( ! file_exists( $changelog_file ) ) {
				return self::get_default_changelog();
			}
		}

		$changelog = json_decode( file_get_contents( $changelog_file ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'MHM Rentiva Changelog JSON Error: ' . json_last_error_msg() );
			return self::get_default_changelog();
		}

		return $changelog;
	}

	/**
	 * Default changelog
	 */
	private static function get_default_changelog(): array {
		return array(
			array(
				'version' => MHM_RENTIVA_VERSION,
				'date'    => gmdate( 'Y-m-d' ),
				'type'    => 'current',
				'changes' => array(
					__( 'Current version', 'mhm-rentiva' ),
					__( 'About page added', 'mhm-rentiva' ),
					__( 'Messaging system added', 'mhm-rentiva' ),
					__( 'Advanced reports system', 'mhm-rentiva' ),
				),
			),
		);
	}
}
