<?php
declare(strict_types=1);

namespace MHMRentiva\Admin\Emails\PostTypes;

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailLog {

	public const TYPE = 'mhm_email_log';

	public static function register(): void {
		add_action( 'init', array( self::class, 'cpt' ) );
		add_action( 'mhm_rentiva_email_sent', array( self::class, 'handle_email_sent' ), 10, 5 );
	}

	public static function cpt(): void {
		$labels = array(
			'name'               => __( 'Email Logs', 'mhm-rentiva' ),
			'singular_name'      => __( 'Email Log', 'mhm-rentiva' ),
			'menu_name'          => __( 'Email Logs', 'mhm-rentiva' ),
			'add_new'            => __( 'Add New', 'mhm-rentiva' ),
			'add_new_item'       => __( 'Add New Email Log', 'mhm-rentiva' ),
			'edit_item'          => __( 'Edit Email Log', 'mhm-rentiva' ),
			'new_item'           => __( 'New Email Log', 'mhm-rentiva' ),
			'view_item'          => __( 'View Email Log', 'mhm-rentiva' ),
			'search_items'       => __( 'Search Email Logs', 'mhm-rentiva' ),
			'not_found'          => __( 'No email logs found.', 'mhm-rentiva' ),
			'not_found_in_trash' => __( 'No email logs found in Trash.', 'mhm-rentiva' ),
		);

		register_post_type(
			self::TYPE,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => false,
				'show_in_menu'    => false,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'show_in_rest'    => false,
			)
		);
	}

	/**
	 * Create a log entry when an email is sent
	 */
	public static function handle_email_sent( string $key, string $to, bool $ok, string $subject, array $context ): void {
		if ( ! \MHMRentiva\Admin\Settings\Groups\EmailSettings::is_log_enabled() ) {
			return;
		}

		$title = '[' . ( $ok ? __( 'Success', 'mhm-rentiva' ) : __( 'Failed', 'mhm-rentiva' ) ) . '] ' . $subject;

		$post_id = wp_insert_post(
			array(
				'post_type'    => self::TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return;
		}

		update_post_meta( $post_id, '_mhm_email_key', sanitize_text_field( $key ) );
		update_post_meta( $post_id, '_mhm_email_to', sanitize_email( $to ) );
		update_post_meta( $post_id, '_mhm_email_subject', sanitize_text_field( $subject ) );
		update_post_meta( $post_id, '_mhm_email_status', $ok ? 'success' : 'failed' );
		update_post_meta( $post_id, '_mhm_email_context', wp_json_encode( $context ) );
	}
}
