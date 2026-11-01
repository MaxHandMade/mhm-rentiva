<?php
/**
 * My Account Dashboard -- Rentiva's copy of WooCommerce's
 * `myaccount/dashboard.php`.
 *
 * WooCommerceIntegration::locate_dashboard_template() serves this file instead
 * of WooCommerce's own while the Rentiva dashboard is hooked to
 * `woocommerce_account_dashboard`. The Rentiva dashboard greets the customer
 * itself, so the two introductory paragraphs of WooCommerce's template (the
 * "Hello ... (not ...? Log out)" line and the dashboard description) are left
 * out. The three actions below fire exactly as in WooCommerce's template, in
 * the same order, so every callback on them keeps running.
 *
 * A theme that overrides `woocommerce/myaccount/dashboard.php` keeps its own
 * file: this one replaces only WooCommerce's default.
 *
 * Its version tag is that of the WooCommerce template this copy mirrors;
 * WooDashboardTemplateTest pins it to the installed WooCommerce.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package MHMRentiva
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own actions, fired as its template fires them.

/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_before_my_account' );

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_after_my_account' );
