/**
 * Vehicle Gallery JavaScript
 *
 * WordPress Media Library entegrasyonu ile araç galerisi yönetimi
 */

(function ($) {
	'use strict';

	let mediaUploader;
	let currentPostId;

	$( document ).ready(
		function () {
			currentPostId = $( '#post_ID' ).val();
			initializeVehicleGallery();
		}
	);

	/**
	 * Araç galerisini başlat
	 */
	function initializeVehicleGallery() {
		// Görsel ekleme butonu
		$( document ).on(
			'click',
			'.mhm-gallery-add-btn',
			function (e) {
				e.preventDefault();
				openMediaLibrary();
			}
		);

		// Görsel kaldırma butonu
		$( document ).on(
			'click',
			'.mhm-gallery-remove-btn',
			function (e) {
				e.preventDefault();
				const imageId = $( this ).data( 'image-id' );
				removeGalleryImage( imageId );
			}
		);

		// Görsel sıralama (drag & drop)
		if ($.fn.sortable) {
			$( '.mhm-gallery-grid' ).sortable(
				{
					items: '.mhm-gallery-item',
					placeholder: 'mhm-gallery-placeholder',
					forcePlaceholderSize: true,
					cursor: 'move',
					opacity: 0.8,
					update: function (event, ui) {
						reorderGalleryImages();
					}
				}
			);
		}

		// Görsel tıklama (büyük görüntüleme)
		$( document ).on(
			'click',
			'.mhm-gallery-item img',
			function () {
				const imageUrl = $( this ).attr( 'src' );
				showImagePreview( imageUrl );
			}
		);
	}

	/**
	 * WordPress Media Library'yi aç
	 */
	function openMediaLibrary() {
		// Mevcut galeri görsellerini al
		const existingImages = getExistingGalleryImages();
		// ⭐ Get max images from localized data (configurable from settings)
		const maxImages = window.mhmVehicleGallery ? .maxImages || 50;

		if (existingImages.length >= maxImages) {
			showNotice( window.mhmVehicleGallery ? .strings ? .maxImages || `Maximum ${maxImages} images allowed`, 'warning' );
			return;
		}

		// Media Library ayarları
		const mediaOptions = {
			title: window.mhmVehicleGallery ? .strings ? .selectImages || 'Select Images',
			button: {
				text: window.mhmVehicleGallery ? .strings ? .addImages || 'Add to Gallery'
			},
			multiple: true,
			library: {
				type: 'image'
			}
		};

		// Mevcut görselleri filtrele
		if (existingImages.length > 0) {
			mediaOptions.library.exclude = existingImages.map( img => img.id );
		}

		// Media Library'yi aç
		mediaUploader = wp.media( mediaOptions );

		mediaUploader.on(
			'select',
			function () {
				const selection = mediaUploader.state().get( 'selection' );
				const imageIds  = selection.map(
					function (attachment) {
						return attachment.get( 'id' );
					}
				);

				if (imageIds.length > 0) {
					addGalleryImages( imageIds );
				}
			}
		);

		mediaUploader.open();
	}

	/**
	 * Galeri görsellerini ekle
	 */
	function addGalleryImages(imageIds) {
		const $galleryContainer = $( '.mhm-gallery-grid' );
		const $loadingIndicator = $( '.mhm-gallery-loading' );

		// Loading göster
		$loadingIndicator.show();

		$.ajax(
			{
				url: window.mhmVehicleGallery ? .ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_add_gallery_image',
					post_id: currentPostId,
					image_ids: imageIds,
					nonce: window.mhmVehicleGallery ? .nonce
				},
				success: function (response) {
					if (response.success) {
						// Galeri görsellerini güncelle
						updateGalleryDisplay( response.data.gallery_images );

						// Gallery updated event'ini tetikle
						$( document ).trigger( 'galleryUpdated' );

						// Başarı mesajı
						showNotification( response.data.message, 'success' );
					} else {
						const errorMsg = (window.mhmVehicleGallery && window.mhmVehicleGallery.strings && window.mhmVehicleGallery.strings.addError) || 'Error adding image';
						showNotification( response.data || errorMsg, 'error' );
					}
				},
				error: function () {
					showNotification( window.mhmVehicleGallery ? .strings ? .uploadError || 'Error uploading image', 'error' );
				},
				complete: function () {
					$loadingIndicator.hide();
				}
			}
		);
	}

	/**
	 * Galeri görselini kaldır
	 */
	function removeGalleryImage(imageId) {
		if ( ! confirm( window.mhmVehicleGallery ? .strings ? .confirmRemove || 'Are you sure you want to remove this image?' )) {
			return;
		}

		$.ajax(
			{
				url: window.mhmVehicleGallery ? .ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_remove_gallery_image',
					post_id: currentPostId,
					image_id: imageId,
					nonce: window.mhmVehicleGallery ? .nonce
				},
				success: function (response) {
					if (response.success) {
						// Galeri görsellerini güncelle
						updateGalleryDisplay( response.data.gallery_images );

						// Gallery updated event'ini tetikle
						$( document ).trigger( 'galleryUpdated' );

						// Başarı mesajı
						showNotification( response.data.message, 'success' );
					} else {
						const errorMsg = (window.mhmVehicleGallery && window.mhmVehicleGallery.strings && window.mhmVehicleGallery.strings.removeError) || 'Error removing image';
						showNotification( response.data || errorMsg, 'error' );
					}
				},
				error: function () {
					const errorMsg = (window.mhmVehicleGallery && window.mhmVehicleGallery.strings && window.mhmVehicleGallery.strings.removeError) || 'Error removing image';
					showNotification( errorMsg, 'error' );
				}
			}
		);
	}

	/**
	 * Galeri görsellerini yeniden sırala
	 */
	function reorderGalleryImages() {
		const imageOrder = [];
		$( '.mhm-gallery-item' ).each(
			function () {
				const imageId = $( this ).data( 'image-id' );
				if (imageId) {
					imageOrder.push( imageId );
				}
			}
		);

		if (imageOrder.length === 0) {
			return;
		}

		$.ajax(
			{
				url: window.mhmVehicleGallery ? .ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_reorder_gallery_images',
					post_id: currentPostId,
					image_order: imageOrder,
					nonce: window.mhmVehicleGallery ? .nonce
				},
				success: function (response) {
					if (response.success) {
						// Sessizce güncelle (kullanıcıya mesaj gösterme)
						updateGalleryDisplay( response.data.gallery_images );

						// Gallery updated event'ini tetikle
						$( document ).trigger( 'galleryUpdated' );
					}
				},
				error: function () {
					const errorMsg = (window.mhmVehicleGallery && window.mhmVehicleGallery.strings && window.mhmVehicleGallery.strings.reorderError) || 'Error reordering images';
					if (typeof console !== 'undefined' && console.error) {
						console.error( errorMsg );
					}
				}
			}
		);
	}

	/**
	 * Galeri görüntüsünü güncelle
	 */
	function updateGalleryDisplay(galleryImages) {
		const $galleryContainer = $( '.mhm-gallery-grid' );
		const $noImagesMessage  = $( '.mhm-gallery-no-images' );

		// Mevcut görselleri temizle
		$galleryContainer.empty();

		if (galleryImages.length === 0) {
			$noImagesMessage.show();
			return;
		}

		$noImagesMessage.hide();

		// Yeni görselleri ekle
		galleryImages.forEach(
			function (image, index) {
				const $galleryItem = createGalleryItem( image, index );
				$galleryContainer.append( $galleryItem );
			}
		);

		// Sortable'ı yeniden başlat
		if ($.fn.sortable) {
			$galleryContainer.sortable( 'destroy' ).sortable(
				{
					items: '.mhm-gallery-item',
					placeholder: 'mhm-gallery-placeholder',
					forcePlaceholderSize: true,
					cursor: 'move',
					opacity: 0.8,
					update: function (event, ui) {
						reorderGalleryImages();
					}
				}
			);
		}
	}

	/**
	 * Galeri öğesi oluştur
	 */
	function createGalleryItem(image, index) {
		const $item                          = $(
			`
			< div class                      = "mhm-gallery-item" data - image - id = "${image.id}" >
				< div class                  = "mhm-gallery-item-inner" >
					< img src                = "${image.url}" alt = "${image.alt || ''}" title = "${image.title || ''}" / >
					< div class              = "mhm-gallery-item-overlay" >
						< div class          = "mhm-gallery-item-actions" >
							< button type    = "button" class = "mhm-gallery-remove-btn" data - image - id = "${image.id}" title = "${window.mhmVehicleGallery?.strings?.removeImage || 'Remove Image'}" >
								< span class = "dashicons dashicons-trash" > < / span >
							< / button >
						< / div >
					< / div >
					< div class = "mhm-gallery-item-number" > ${index + 1} < / div >
				< / div >
			< / div >
			`
		);

		return $item;
	}

	/**
	 * Mevcut galeri görsellerini al
	 */
	function getExistingGalleryImages() {
		const existingImages = [];
		$( '.mhm-gallery-item' ).each(
			function () {
				const imageId = $( this ).data( 'image-id' );
				if (imageId) {
					existingImages.push( { id: parseInt( imageId ) } );
				}
			}
		);
		return existingImages;
	}

	/**
	 * Görsel önizleme göster
	 */
	function showImagePreview(imageUrl) {
		const $preview               = $(
			`
			< div class              = "mhm-gallery-preview-overlay" >
				< div class          = "mhm-gallery-preview-container" >
					< img src        = "${imageUrl}" alt = "Preview" / >
					< button type    = "button" class = "mhm-gallery-preview-close" >
						< span class = "dashicons dashicons-no-alt" > < / span >
					< / button >
				< / div >
			< / div >
			`
		);

		$( 'body' ).append( $preview );

		// Kapatma butonu
		$preview.on(
			'click',
			'.mhm-gallery-preview-close, .mhm-gallery-preview-overlay',
			function (e) {
				if (e.target === this) {
					$preview.remove();
				}
			}
		);

		// ESC tuşu ile kapatma
		$( document ).on(
			'keyup.gallery-preview',
			function (e) {
				if (e.keyCode === 27) { // ESC
					$preview.remove();
					$( document ).off( 'keyup.gallery-preview' );
				}
			}
		);
	}

	/**
	 * Bildirim göster
	 */
	function showNotification(message, type = 'info') {
		const $notification = $(
			`
			< div class     = "mhm-gallery-notification mhm-gallery-notification-${type}" >
				< span class     = "mhm-gallery-notification-message" > ${message} < / span >
				< button type    = "button" class = "mhm-gallery-notification-close" >
					< span class = "dashicons dashicons-no-alt" > < / span >
				< / button >
			< / div >
			`
		);

		$( 'body' ).append( $notification );

		// Otomatik kapatma
		setTimeout(
			function () {
				$notification.fadeOut(
					300,
					function () {
						$( this ).remove();
					}
				);
			},
			3000
		);

		// Manuel kapatma
		$notification.on(
			'click',
			'.mhm-gallery-notification-close',
			function () {
				$notification.fadeOut(
					300,
					function () {
						$( this ).remove();
					}
				);
			}
		);
	}

	/**
	 * Show notice message
	 */
	function showNotice(message, type) {
		type            = type || 'info';
		var noticeClass = 'notice-' + type;
		var notice      = $( '<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>' );

		// Remove any existing notices first
		$( '.notice' ).remove();

		// Add to body for better visibility
		$( 'body' ).append( notice );

		// Auto-dismiss after 5 seconds
		setTimeout(
			function () {
				notice.fadeOut(
					500,
					function () {
						notice.remove();
					}
				);
			},
			5000
		);
	}

})( jQuery );
