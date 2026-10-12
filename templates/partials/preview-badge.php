<?php
/**
 * Editor-preview badge: tells the editor that the output is sample content.
 *
 * Rendered by RenderContext::badge(), only in a preview.
 *
 * @var string $message Already-translated badge text.
 *
 * @package MHMRentiva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p class="mhm-rv-preview-badge" role="note"><?php echo esc_html( isset( $message ) ? (string) $message : '' ); ?></p>
