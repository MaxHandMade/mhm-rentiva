<?php
declare(strict_types=1);

/**
 * My Account - Navigation Template
 *
 * @var array $navigation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local to this partial view.
// Active page detection.
$mhm_rentiva_current_endpoint = 'dashboard';
$mhm_rentiva_query_endpoint   = get_query_var( 'endpoint' );

if ( is_string( $mhm_rentiva_query_endpoint ) && $mhm_rentiva_query_endpoint !== '' ) {
	$mhm_rentiva_current_endpoint = sanitize_key( $mhm_rentiva_query_endpoint );
}

$mhm_rentiva_request_uri = '';
if ( isset( $_SERVER['REQUEST_URI'] ) ) {
	$mhm_rentiva_request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
}

$mhm_rentiva_current_url = home_url( $mhm_rentiva_request_uri );
foreach ( $navigation as $mhm_rentiva_endpoint => $mhm_rentiva_nav_item ) {
	if ( strpos( $mhm_rentiva_current_url, (string) $mhm_rentiva_nav_item['url'] ) !== false ) {
		$mhm_rentiva_current_endpoint = $mhm_rentiva_endpoint;
		break;
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
?>

<nav class="mhm-account-navigation">
	<ul class="account-menu">
		<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Foreach variables are local to template rendering scope. ?>
		<?php foreach ( $navigation as $mhm_rentiva_endpoint => $mhm_rentiva_nav_item ) : ?>
			<li class="menu-item <?php echo $mhm_rentiva_current_endpoint === $mhm_rentiva_endpoint ? 'active' : ''; ?>">
				<a href="<?php echo esc_url( $mhm_rentiva_nav_item['url'] ); ?>">
					<span class="menu-icon"><?php echo wp_kses_post( $mhm_rentiva_nav_item['icon'] ); ?></span>
					<span class="menu-title"><?php echo esc_html( $mhm_rentiva_nav_item['title'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
