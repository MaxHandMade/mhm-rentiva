<?php
/**
 * Transfer Addon Modal.
 *
 * @package MHMRentiva\Admin\Transfer\Frontend
 */

declare(strict_types=1);

namespace MHMRentiva\Admin\Transfer\Frontend;

use MHMRentiva\Admin\Addons\AddonManager;
use MHMRentiva\Admin\Addons\AddonPricingType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the transfer add-on picker modal once per page.
 * Modal is hidden by default; transfer-addon-modal.js opens it when
 * a vehicle card's "Book Now" button is clicked.
 */
final class TransferAddonModal {

	/**
	 * Render the modal markup and data once per page.
	 *
	 * @return void
	 */
	public static function render(): void {
		$addons = AddonManager::get_available_addons( 'transfer' );
		if ( empty( $addons ) ) {
			// Render an empty marker so JS knows to bypass the modal entirely.
			echo '<div id="rentiva-transfer-addon-modal" data-empty="1" hidden></div>';
			return;
		}

		// Serialise addons as JSON for the JS controller.
		$payload = array_map(
			static function ( array $addon ): array {
				return array(
					'id'           => (int) $addon['id'],
					'title'        => (string) $addon['title'],
					'description'  => (string) $addon['description'],
					'price'        => (float) $addon['price'],
					'pricing_type' => (string) ( $addon['pricing_type'] ?? AddonPricingType::PER_BOOKING ),
					'required'     => (bool) $addon['required'],
				);
			},
			$addons
		);

		?>
		<div id="rentiva-transfer-addon-modal" class="rentiva-modal" hidden>
			<div class="rentiva-modal__backdrop" data-modal-close></div>
			<div class="rentiva-modal__panel" role="dialog" aria-modal="true"
				aria-labelledby="rentiva-transfer-addon-modal-title">
				<header class="rentiva-modal__header">
					<h2 id="rentiva-transfer-addon-modal-title" class="rentiva-modal__title">
						<?php esc_html_e( 'Add-ons for your VIP transfer', 'mhm-rentiva' ); ?>
					</h2>
					<p class="rentiva-modal__subtitle" data-route-line></p>
					<button type="button" class="rentiva-modal__close"
							data-modal-close
							aria-label="<?php esc_attr_e( 'Close', 'mhm-rentiva' ); ?>">×</button>
				</header>
				<div class="rentiva-modal__body" data-addon-list></div>
				<footer class="rentiva-modal__footer">
					<div class="rentiva-modal__total" data-total-line></div>
					<div class="rentiva-modal__actions">
						<button type="button" class="button" data-modal-close>
							<?php esc_html_e( 'Cancel', 'mhm-rentiva' ); ?>
						</button>
						<button type="button" class="button button-primary" data-modal-submit>
							<?php esc_html_e( 'Add to cart', 'mhm-rentiva' ); ?>
						</button>
					</div>
				</footer>
			</div>
		</div>
		<script type="application/json" id="rentiva-transfer-addon-modal-data">
			<?php echo wp_json_encode( $payload ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode is safe. ?>
		</script>
		<?php
	}
}
