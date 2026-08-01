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
$mhmrentiva_current_endpoint = 'dashboard';
$mhmrentiva_query_endpoint   = get_query_var( 'endpoint' );

if ( is_string( $mhmrentiva_query_endpoint ) && $mhmrentiva_query_endpoint !== '' ) {
	$mhmrentiva_current_endpoint = sanitize_key( $mhmrentiva_query_endpoint );
}

$mhmrentiva_request_uri = '';
if ( isset( $_SERVER['REQUEST_URI'] ) ) {
	$mhmrentiva_request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
}

$mhmrentiva_current_url = home_url( $mhmrentiva_request_uri );
foreach ( $navigation as $mhmrentiva_endpoint => $mhmrentiva_nav_item ) {
	if ( strpos( $mhmrentiva_current_url, (string) $mhmrentiva_nav_item['url'] ) !== false ) {
		$mhmrentiva_current_endpoint = $mhmrentiva_endpoint;
		break;
	}
}
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
?>

<nav class="mhm-account-navigation">
	<ul class="account-menu">
		<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Foreach variables are local to template rendering scope. ?>
		<?php foreach ( $navigation as $mhmrentiva_endpoint => $mhmrentiva_nav_item ) : ?>
			<li class="menu-item <?php echo $mhmrentiva_current_endpoint === $mhmrentiva_endpoint ? 'active' : ''; ?>">
				<a href="<?php echo esc_url( $mhmrentiva_nav_item['url'] ); ?>">
					<span class="menu-icon"><?php echo wp_kses_post( $mhmrentiva_nav_item['icon'] ); ?></span>
					<span class="menu-title"><?php echo esc_html( $mhmrentiva_nav_item['title'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
