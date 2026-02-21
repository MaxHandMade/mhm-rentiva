<?php

declare(strict_types=1);

namespace MHMRentiva\Admin\Settings\Groups;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MHMRentiva\Admin\Settings\Comments\CommentsSettings;

/**
 * ✅ COMMENTS SETTINGS GROUP
 * * Manages all settings related to comments using Accordion UI
 */
final class CommentsSettingsGroup {



	// Use consistent constants
	public const SECTION_ID   = 'mhm_rentiva_comments_section';
	public const OPTION_GROUP = 'mhm_rentiva_settings';
	public const SUB_KEY      = 'mhm_rentiva_comments_settings';

	/**
	 * Get default settings for this group.
	 * Returns the keys as they are stored in the master 'mhm_rentiva_settings' option.
	 *
	 * @return array
	 */
	public static function get_default_settings(): array {
		$defaults = CommentsSettings::get_default_settings();

		return array(
			'comments_approval'        => $defaults['approval'],
			'comments_limits'          => $defaults['limits'],
			'comments_display'         => $defaults['display'],
			'comments_spam_protection' => $defaults['spam_protection'],
			'comments_notifications'   => $defaults['notifications'],
			'comments_cache'           => $defaults['cache'],
		);
	}

	/**
	 * Safe sanitize text field that handles null values
	 */
	public static function sanitize_text_field_safe( $value ) {
		if ( $value === null || $value === '' ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}

	public static function register(): void {
		// Central Page Constant
		$page_slug = \MHMRentiva\Admin\Settings\Core\SettingsCore::PAGE;

		// Comments Settings Section
		add_settings_section(
			self::SECTION_ID,
			__( 'Comments Settings', 'mhm-rentiva' ),
			array( self::class, 'render_section_description' ),
			$page_slug
		);

		// Group 1: General Configuration
		add_settings_field(
			'group_general_config',
			__( 'General Configuration', 'mhm-rentiva' ),
			array( self::class, 'render_group_general_config' ),
			$page_slug,
			self::SECTION_ID
		);

		// Group 2: Display Settings
		add_settings_field(
			'group_display_settings',
			__( 'Display Settings', 'mhm-rentiva' ),
			array( self::class, 'render_group_display_settings' ),
			$page_slug,
			self::SECTION_ID
		);

		// Group 3: Content Limits
		add_settings_field(
			'group_content_limits',
			__( 'Content Limits', 'mhm-rentiva' ),
			array( self::class, 'render_group_content_limits' ),
			$page_slug,
			self::SECTION_ID
		);

		// Group 4: Spam & Security
		add_settings_field(
			'group_spam_security',
			__( 'Spam & Security', 'mhm-rentiva' ),
			array( self::class, 'render_group_spam_security' ),
			$page_slug,
			self::SECTION_ID
		);

		// Group 5: Notifications
		add_settings_field(
			'group_notifications',
			__( 'Notifications', 'mhm-rentiva' ),
			array( self::class, 'render_group_notifications' ),
			$page_slug,
			self::SECTION_ID
		);

		// Group 6: Cache & Performance
		add_settings_field(
			'group_cache_performance',
			__( 'Cache & Performance', 'mhm-rentiva' ),
			array( self::class, 'render_group_cache_performance' ),
			$page_slug,
			self::SECTION_ID
		);
	}

	public static function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure comment and rating system settings for vehicles.', 'mhm-rentiva' ) . '</p>';
	}

	// --- GROUP RENDERING METHODS ---

	/**
	 * Group 1: General Configuration
	 */
	public static function render_group_general_config(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-general">';
		echo '<span>' . esc_html__( 'General Configuration', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		self::render_checkbox_field(
			'approval',
			'auto_approve',
			__( 'Auto Approve Comments', 'mhm-rentiva' ),
			__( 'Automatically approve new comments without moderation.', 'mhm-rentiva' ),
			$settings
		);

		self::render_checkbox_field(
			'approval',
			'require_login',
			__( 'Require Login', 'mhm-rentiva' ),
			__( 'Require users to be logged in to comment.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_checkbox_field(
			'approval',
			'allow_guest_comments',
			__( 'Allow Guest Comments', 'mhm-rentiva' ),
			__( 'Allow guest users to comment (requires login to be disabled).', 'mhm-rentiva' ),
			$settings
		);

		echo '</div></div>';
	}

	/**
	 * Group 2: Display Settings
	 */
	public static function render_group_display_settings(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-display">';
		echo '<span>' . esc_html__( 'Display Settings', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		self::render_number_field(
			'limits',
			'comments_per_page',
			__( 'Comments Per Page', 'mhm-rentiva' ),
			__( 'Number of comments to display per page.', 'mhm-rentiva' ),
			$settings,
			10,
			1,
			100
		);

		self::render_checkbox_field(
			'display',
			'show_ratings',
			__( 'Show Ratings', 'mhm-rentiva' ),
			__( 'Show star ratings with comments.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_checkbox_field(
			'display',
			'show_avatars',
			__( 'Show Avatars', 'mhm-rentiva' ),
			__( 'Show user avatars with comments.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_checkbox_field(
			'display',
			'allow_editing',
			__( 'Allow Comment Editing', 'mhm-rentiva' ),
			__( 'Allow users to edit their own comments.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_checkbox_field(
			'display',
			'allow_deletion',
			__( 'Allow Comment Deletion', 'mhm-rentiva' ),
			__( 'Allow users to delete their own comments.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_number_field(
			'display',
			'edit_time_limit',
			__( 'Edit Time Limit (hours)', 'mhm-rentiva' ),
			__( 'Time limit in hours for editing comments (0 = no limit).', 'mhm-rentiva' ),
			$settings,
			24,
			0,
			168
		);

		echo '</div></div>';
	}

	/**
	 * Group 3: Content Limits
	 */
	public static function render_group_content_limits(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-limits">';
		echo '<span>' . esc_html__( 'Content Limits', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		self::render_number_field(
			'limits',
			'comment_length_min',
			__( 'Minimum Comment Length', 'mhm-rentiva' ),
			__( 'Minimum number of characters required for comments.', 'mhm-rentiva' ),
			$settings,
			5,
			1,
			1000
		);

		self::render_number_field(
			'limits',
			'comment_length_max',
			__( 'Maximum Comment Length', 'mhm-rentiva' ),
			__( 'Maximum number of characters allowed for comments.', 'mhm-rentiva' ),
			$settings,
			1000,
			10,
			5000
		);

		echo '</div></div>';
	}

	/**
	 * Group 4: Spam & Security
	 * Fixed: Names are now dynamically generated using constants.
	 */
	public static function render_group_spam_security(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-spam">';
		echo '<span>' . esc_html__( 'Spam & Security', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		// 1. Spam Protection Enabled (Standard Helper)
		self::render_checkbox_field(
			'spam_protection',
			'enabled',
			__( 'Enable Spam Protection', 'mhm-rentiva' ),
			__( 'Enable spam protection for comments.', 'mhm-rentiva' ),
			$settings,
			true
		);

		// 2. Rate Limiting Enabled (Deep Nested - Dynamic Name Construction)
		$rl_enabled = isset( $settings['spam_protection']['rate_limiting']['enabled'] ) ? (bool) $settings['spam_protection']['rate_limiting']['enabled'] : true;
		// Dynamic name construction for 3rd level depth
		$name_rl_enabled = self::OPTION_GROUP . '[' . self::SUB_KEY . '][spam_protection][rate_limiting][enabled]';
		$id_rl_enabled   = 'mhm_rentiva_spam_protection_rate_limiting_enabled';

		echo '<div class="mhm-form-group mhm-checkbox-group">';
		echo '<label for="' . esc_attr( $id_rl_enabled ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name_rl_enabled ) . '" value="0">';
		echo '<input type="checkbox" id="' . esc_attr( $id_rl_enabled ) . '" name="' . esc_attr( $name_rl_enabled ) . '" value="1" ' . checked( $rl_enabled, true, false ) . '> ';
		echo esc_html__( 'Enable Rate Limiting', 'mhm-rentiva' );
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'Enable rate limiting to prevent spam.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		// 3. Rate Limiting Time Window (Deep Nested - Dynamic Name Construction)
		$rl_window      = isset( $settings['spam_protection']['rate_limiting']['time_window'] ) ? absint( $settings['spam_protection']['rate_limiting']['time_window'] ) : 1;
		$name_rl_window = self::OPTION_GROUP . '[' . self::SUB_KEY . '][spam_protection][rate_limiting][time_window]';

		echo '<div class="mhm-form-group">';
		echo '<label>' . esc_html__( 'Rate Limiting Time Window (minutes)', 'mhm-rentiva' ) . '</label>';
		echo '<input type="number" name="' . esc_attr( $name_rl_window ) . '" value="' . esc_attr( $rl_window ) . '" min="1" max="60" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Time window for rate limiting in minutes.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		// 4. Rate Limiting Max Attempts (Deep Nested - Dynamic Name Construction)
		$rl_attempts      = isset( $settings['spam_protection']['rate_limiting']['max_attempts'] ) ? absint( $settings['spam_protection']['rate_limiting']['max_attempts'] ) : 1;
		$name_rl_attempts = self::OPTION_GROUP . '[' . self::SUB_KEY . '][spam_protection][rate_limiting][max_attempts]';

		echo '<div class="mhm-form-group">';
		echo '<label>' . esc_html__( 'Max Attempts Per Time Window', 'mhm-rentiva' ) . '</label>';
		echo '<input type="number" name="' . esc_attr( $name_rl_attempts ) . '" value="' . esc_attr( $rl_attempts ) . '" min="1" max="10" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Maximum number of comment attempts per time window.', 'mhm-rentiva' ) . '</p>';
		echo '</div>';

		// 5. Spam Words (Using new Helper)
		$spam_words       = isset( $settings['spam_protection']['spam_words'] ) && is_array( $settings['spam_protection']['spam_words'] )
			? $settings['spam_protection']['spam_words']
			: array( 'spam', 'viagra', 'casino', 'loan', 'free money', 'click here' );
		$spam_words_value = implode( ', ', array_map( 'sanitize_text_field', $spam_words ) );

		// Note: For spam words, we use 'spam_protection' as group and 'spam_words' as field.
		// The helper will handle the dynamic name generation.
		self::render_textarea_field(
			'spam_protection',
			'spam_words',
			__( 'Spam Words', 'mhm-rentiva' ),
			__( 'Comma-separated list of spam words to filter out.', 'mhm-rentiva' ),
			$spam_words_value // Pass the string value directly
		);

		echo '</div></div>';
	}

	/**
	 * Group 5: Notifications
	 */
	public static function render_group_notifications(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-notifications">';
		echo '<span>' . esc_html__( 'Notifications', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		self::render_checkbox_field(
			'notifications',
			'admin_new_comment',
			__( 'Notify Admin on New Comment', 'mhm-rentiva' ),
			__( 'Send email notification to admin when new comment is posted.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_checkbox_field(
			'notifications',
			'user_comment_approved',
			__( 'Notify User on Comment Approval', 'mhm-rentiva' ),
			__( 'Send email notification to user when comment is approved.', 'mhm-rentiva' ),
			$settings,
			true
		);

		echo '</div></div>';
	}

	/**
	 * Group 6: Cache & Performance
	 */
	public static function render_group_cache_performance(): void {
		$settings = CommentsSettings::get_settings();

		echo '<div class="mhm-accordion-group">';
		echo '<div class="mhm-accordion-header" id="accordion-comments-cache">';
		echo '<span>' . esc_html__( 'Cache & Performance', 'mhm-rentiva' ) . '</span>';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '</div>';
		echo '<div class="mhm-accordion-content">';

		self::render_checkbox_field(
			'cache',
			'enabled',
			__( 'Enable Comment Cache', 'mhm-rentiva' ),
			__( 'Enable caching for comments to improve performance.', 'mhm-rentiva' ),
			$settings,
			true
		);

		self::render_number_field(
			'cache',
			'duration',
			__( 'Cache Duration (minutes)', 'mhm-rentiva' ),
			__( 'Cache duration in minutes.', 'mhm-rentiva' ),
			$settings,
			15,
			1,
			1440
		);

		echo '</div></div>';
	}

	// --- HELPER METHODS ---

	/**
	 * Render checkbox field helper
	 */
	private static function render_checkbox_field( string $group, string $field, string $label, string $description, array $settings, bool $default = false ): void {
		$value = isset( $settings[ $group ][ $field ] ) ? (bool) $settings[ $group ][ $field ] : $default;
		$id    = 'mhm_rentiva_' . $group . '_' . $field;
		$name  = self::OPTION_GROUP . '[' . self::SUB_KEY . '][' . esc_attr( $group ) . '][' . esc_attr( $field ) . ']';

		echo '<div class="mhm-form-group mhm-checkbox-group">';
		echo '<label for="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( $value, true, false ) . '>';
		echo esc_html( $label );
		echo '</label>';

		if ( ! empty( $description ) ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Render number field helper
	 */
	private static function render_number_field( string $group, string $field, string $label, string $description, array $settings, int $default = 0, int $min = 0, int $max = 100 ): void {
		$value = isset( $settings[ $group ][ $field ] ) ? absint( $settings[ $group ][ $field ] ) : $default;
		$name  = self::OPTION_GROUP . '[' . self::SUB_KEY . '][' . esc_attr( $group ) . '][' . esc_attr( $field ) . ']';

		echo '<div class="mhm-form-group">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<input type="number" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render textarea field helper
	 * Added for Spam Words consistency
	 *
	 * @param string|mixed $value_override Optional direct value override (useful when value needs processing like implode)
	 */
	private static function render_textarea_field( string $group, string $field, string $label, string $description, $value_override = null ): void {
		// Name construction using constants
		$name = self::OPTION_GROUP . '[' . self::SUB_KEY . '][' . esc_attr( $group ) . '][' . esc_attr( $field ) . ']';

		// If value is provided directly (processed), use it. Otherwise try to fetch from settings if passed (omitted here for simplicity as we pass value)
		$value = (string) $value_override;

		echo '<div class="mhm-form-group">';
		echo '<label>' . esc_html( $label ) . '</label>';
		echo '<textarea name="' . esc_attr( $name ) . '" rows="3" cols="50" class="large-text" style="width:100%">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render the settings section
	 */
	public static function render_settings_section(): void {
		if ( class_exists( '\MHMRentiva\Admin\Settings\View\SettingsViewHelper' ) ) {
			\MHMRentiva\Admin\Settings\View\SettingsViewHelper::render_section_cleanly( self::SECTION_ID );
		}
	}
}

