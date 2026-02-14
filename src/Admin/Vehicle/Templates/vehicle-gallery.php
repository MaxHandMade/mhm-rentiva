<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Template-scope variables are local render context.
declare(strict_types=1);

/**
 * Vehicle Gallery Template
 *
 * @package MHMRentiva
 *
 * @var \WP_Post $post
 * @var array $gallery_images
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Template variables are passed from VehicleGallery::render_gallery_meta_box()
// $post, $gallery_images
?>

<div class="mhm-vehicle-gallery-container">
	
	<!-- Gallery Header -->
	<div class="mhm-gallery-header">
		<h4 class="mhm-gallery-title">
			<span class="dashicons dashicons-format-gallery"></span>
			<?php esc_html_e( 'Vehicle Gallery', 'mhm-rentiva' ); ?>
		</h4>
		<button type="button" class="mhm-gallery-add-btn">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add Image', 'mhm-rentiva' ); ?>
		</button>
	</div>

	<!-- Gallery Grid -->
	<div class="mhm-gallery-grid" id="vehicle-gallery-grid">
		<?php if ( ! empty( $gallery_images ) ) : ?>
			<?php foreach ( $gallery_images as $index => $image ) : ?>
				<div class="mhm-gallery-item" data-image-id="<?php echo esc_attr( $image['id'] ); ?>">
					<div class="mhm-gallery-item-inner">
						<img src="<?php echo esc_url( $image['url'] ); ?>" 
							alt="<?php echo esc_attr( $image['alt'] ); ?>" 
							title="<?php echo esc_attr( $image['title'] ); ?>" />
						<div class="mhm-gallery-item-overlay">
							<div class="mhm-gallery-item-actions">
								<button type="button" 
										class="mhm-gallery-remove-btn" 
										data-image-id="<?php echo esc_attr( $image['id'] ); ?>"
										title="<?php esc_attr_e( 'Remove Image', 'mhm-rentiva' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
						<div class="mhm-gallery-item-number"><?php echo esc_html( $index + 1 ); ?></div>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Empty Gallery Message -->
	<?php if ( empty( $gallery_images ) ) : ?>
		<div class="mhm-gallery-no-images">
			<span class="dashicons dashicons-format-gallery"></span>
			<p><?php esc_html_e( 'No images added yet', 'mhm-rentiva' ); ?></p>
			<p><?php esc_html_e( 'Add images for vehicle gallery', 'mhm-rentiva' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Loading Indicator -->
	<div class="mhm-gallery-loading">
		<span class="dashicons dashicons-update"></span>
		<?php esc_html_e( 'Loading...', 'mhm-rentiva' ); ?>
	</div>

	<!-- Gallery Information -->
	<div class="mhm-gallery-info">
		<p class="description">
			<span class="dashicons dashicons-info"></span>
			<?php esc_html_e( 'You can add up to 10 images. You can drag and drop images to reorder them.', 'mhm-rentiva' ); ?>
		</p>
	</div>

	<!-- Hidden Input for Form Submission -->
	<input type="hidden" 
			name="mhm_rentiva_gallery_images" 
			id="mhm_rentiva_gallery_images" 
			value="<?php echo esc_attr( wp_json_encode( $gallery_images ) ); ?>" />
	
	<!-- Nonce for Security -->
	<?php wp_nonce_field( 'mhm_rentiva_gallery_images', 'mhm_rentiva_gallery_images_nonce' ); ?>

</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Update gallery images
	function updateGalleryHiddenInput() {
		const galleryImages = [];
		$('.mhm-gallery-item').each(function() {
			const imageId = $(this).data('image-id');
			const imageUrl = $(this).find('img').attr('src');
			const imageAlt = $(this).find('img').attr('alt');
			const imageTitle = $(this).find('img').attr('title');
			
			if (imageId) {
				galleryImages.push({
					id: parseInt(imageId),
					url: imageUrl,
					alt: imageAlt || '',
					title: imageTitle || ''
				});
			}
		});
		
		$('#mhm_rentiva_gallery_images').val(JSON.stringify(galleryImages));
	}
	
	// Listen for gallery changes
	$(document).on('galleryUpdated', function() {
		updateGalleryHiddenInput();
	});
	
	// Update hidden input when page loads
	updateGalleryHiddenInput();
});
</script>