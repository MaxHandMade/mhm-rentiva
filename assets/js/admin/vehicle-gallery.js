/**
 * Vehicle Gallery JavaScript
 *
 * Handles vehicle gallery interactions with WordPress Media Library
 */

(function ($) {
	'use strict';

	let mediaUploader;
	let currentPostId;

	$(document).ready(
		function () {
			currentPostId = $('#post_ID').val();
			initializeVehicleGallery();
		}
	);

	/**
	 * Initialize vehicle gallery
	 */
	function initializeVehicleGallery() {
		// Add image button
		$(document).on(
			'click',
			'.mhm-gallery-add-btn',
			function (e) {
				e.preventDefault();
				openMediaLibrary();
			}
		);

		// Remove image button
		$(document).on(
			'click',
			'.mhm-gallery-remove-btn',
			function (e) {
				e.preventDefault();
				const imageId = $(this).data('image-id');
				removeGalleryImage(imageId);
			}
		);

		// Image sorting (drag & drop)
		if ($.fn.sortable) {
			$('.mhm-gallery-grid').sortable(
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

		// Image clicking (preview)
		$(document).on(
			'click',
			'.mhm-gallery-item img',
			function () {
				const imageUrl = $(this).attr('src');
				showImagePreview(imageUrl);
			}
		);
	}

	/**
	 * Open WordPress Media Library
	 */
	function openMediaLibrary() {
		// Get existing images
		const existingImages = getExistingGalleryImages();
		// Get max images from localized data
		const maxImages = window.mhmVehicleGallery?.maxImages || 50;

		if (existingImages.length >= maxImages) {
			showNotice(window.mhmVehicleGallery?.strings?.maxImages || `Maximum ${maxImages} images allowed`, 'warning');
			return;
		}

		// Media Library settings
		const mediaOptions = {
			title: window.mhmVehicleGallery?.strings?.selectImages || 'Select Images',
			button: {
				text: window.mhmVehicleGallery?.strings?.addImages || 'Add to Gallery'
			},
			multiple: true,
			library: {
				type: 'image'
			}
		};

		// Filter existing images
		if (existingImages.length > 0) {
			mediaOptions.library.exclude = existingImages.map(img => img.id);
		}

		// Create/open uploader
		mediaUploader = wp.media(mediaOptions);

		mediaUploader.on(
			'select',
			function () {
				const selection = mediaUploader.state().get('selection');
				const imageIds = selection.map(
					function (attachment) {
						return attachment.get('id');
					}
				);

				if (imageIds.length > 0) {
					addGalleryImages(imageIds);
				}
			}
		);

		mediaUploader.open();
	}

	/**
	 * Add gallery images via AJAX
	 */
	function addGalleryImages(imageIds) {
		const $loadingIndicator = $('.mhm-gallery-loading');

		$loadingIndicator.show();

		$.ajax(
			{
				url: window.mhmVehicleGallery?.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_add_gallery_image',
					post_id: currentPostId,
					image_ids: imageIds,
					nonce: window.mhmVehicleGallery?.nonce
				},
				success: function (response) {
					if (response.success) {
						updateGalleryDisplay(response.data.gallery_images);
						$(document).trigger('galleryUpdated');
						showNotification(response.data.message, 'success');
						updateBulkToolbar();
					} else {
						const errorMsg = response.data || (window.mhmVehicleGallery?.strings?.addImageError || 'Error adding image');
						showNotification(errorMsg, 'error');
					}
				},
				error: function () {
					showNotification(window.mhmVehicleGallery?.strings?.uploadError || 'Error uploading image', 'error');
				},
				complete: function () {
					$loadingIndicator.hide();
				}
			}
		);
	}

	/**
	 * Remove gallery image via AJAX
	 */
	function removeGalleryImage(imageId) {
		if (!confirm(window.mhmVehicleGallery?.strings?.confirmRemove || 'Are you sure you want to remove this image?')) {
			return;
		}

		$.ajax(
			{
				url: window.mhmVehicleGallery?.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_remove_gallery_image',
					post_id: currentPostId,
					image_id: imageId,
					nonce: window.mhmVehicleGallery?.nonce
				},
				success: function (response) {
					if (response.success) {
						updateGalleryDisplay(response.data.gallery_images);
						$(document).trigger('galleryUpdated');
						showNotification(response.data.message, 'success');
						updateBulkToolbar();
					} else {
						const errorMsg = response.data || (window.mhmVehicleGallery?.strings?.removeImageError || 'Error removing image');
						showNotification(errorMsg, 'error');
					}
				},
				error: function () {
					const errorMsg = (window.mhmVehicleGallery?.strings?.removeImageError || 'Error removing image');
					showNotification(errorMsg, 'error');
				}
			}
		);
	}

	/**
	 * Reorder gallery images via AJAX
	 */
	function reorderGalleryImages() {
		const imageOrder = [];
		$('.mhm-gallery-item').each(
			function () {
				const imageId = $(this).data('image-id');
				if (imageId) {
					imageOrder.push(imageId);
				}
			}
		);

		if (imageOrder.length === 0) {
			return;
		}

		$.ajax(
			{
				url: window.mhmVehicleGallery?.ajaxUrl || ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_reorder_gallery_images',
					post_id: currentPostId,
					image_order: imageOrder,
					nonce: window.mhmVehicleGallery?.nonce
				},
				success: function (response) {
					if (response.success) {
						updateGalleryDisplay(response.data.gallery_images);
						$(document).trigger('galleryUpdated');
					} else {
						console.error(response.data || 'Error reordering images');
					}
				},
				error: function () {
					console.error('Error reordering images');
				}
			}
		);
	}

	/**
	 * Update gallery display
	 */
	function updateGalleryDisplay(galleryImages) {
		const $galleryContainer = $('.mhm-gallery-grid');
		const $noImagesMessage = $('.mhm-gallery-no-images');

		$galleryContainer.empty();

		if (!galleryImages || galleryImages.length === 0) {
			$noImagesMessage.show();
			return;
		}

		$noImagesMessage.hide();

		galleryImages.forEach(
			function (image, index) {
				const $galleryItem = createGalleryItem(image, index);
				$galleryContainer.append($galleryItem);
			}
		);

		// Refresh sortable
		if ($.fn.sortable) {
			$galleryContainer.sortable('refresh');
		}
	}

	/**
	 * Create gallery item HTML
	 */
	function createGalleryItem(image, index) {
		const removeTitle = window.mhmVehicleGallery?.strings?.removeImage || 'Remove Image';
		return $(
			`<div class="mhm-gallery-item" data-image-id="${image.id}">
				<input type="checkbox" class="gallery-item-checkbox" value="${parseInt(image.id, 10)}">
				<div class="mhm-gallery-item-inner">
					<img src="${image.url}" alt="${image.alt || ''}" title="${image.title || ''}" />
					<div class="mhm-gallery-item-overlay">
						<div class="mhm-gallery-item-actions">
							<button type="button" class="mhm-gallery-remove-btn" data-image-id="${image.id}" title="${removeTitle}">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					</div>
					<div class="mhm-gallery-item-number">${index + 1}</div>
				</div>
			</div>`
		);
	}

	/**
	 * Get existing image IDs
	 */
	function getExistingGalleryImages() {
		const existingImages = [];
		$('.mhm-gallery-item').each(
			function () {
				const imageId = $(this).data('image-id');
				if (imageId) {
					existingImages.push({ id: parseInt(imageId) });
				}
			}
		);
		return existingImages;
	}

	/**
	 * Show image preview overlay
	 */
	function showImagePreview(imageUrl) {
		const $preview = $(
			`<div class="mhm-gallery-preview-overlay">
				<div class="mhm-gallery-preview-container">
					<img src="${imageUrl}" alt="Preview" />
					<button type="button" class="mhm-gallery-preview-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			</div>`
		);

		$('body').append($preview);

		$preview.on(
			'click',
			'.mhm-gallery-preview-close, .mhm-gallery-preview-overlay',
			function (e) {
				if (e.target === this || $(e.target).closest('.mhm-gallery-preview-close').length) {
					$preview.remove();
				}
			}
		);

		$(document).on(
			'keyup.gallery-preview',
			function (e) {
				if (e.keyCode === 27) { // ESC
					$preview.remove();
					$(document).off('keyup.gallery-preview');
				}
			}
		);
	}

	/**
	 * Show notification toast
	 */
	function showNotification(message, type = 'info') {
		const $notification = $(
			`<div class="mhm-gallery-notification mhm-gallery-notification-${type}">
				<span class="mhm-gallery-notification-message">${message}</span>
				<button type="button" class="mhm-gallery-notification-close">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>`
		);

		$('body').append($notification);

		setTimeout(
			function () {
				$notification.fadeOut(
					300,
					function () {
						$(this).remove();
					}
				);
			},
			3000
		);

		$notification.on(
			'click',
			'.mhm-gallery-notification-close',
			function () {
				$notification.remove();
			}
		);
	}

	/**
	 * Show admin notice
	 */
	function showNotice(message, type) {
		type = type || 'info';
		const noticeClass = 'notice-' + type;
		const notice = $('<div class="notice ' + noticeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><p><strong>' + message + '</strong></p></div>');

		$('.notice').remove();
		$('body').append(notice);

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

	// --- Bulk delete functionality ---
	function updateBulkToolbar() {
		var $toolbar  = $('#mhm-gallery-bulk-toolbar');
		var $items    = $('.mhm-gallery-item');
		var total     = $items.length;
		var selected  = $('.gallery-item-checkbox:checked').length;

		if (total === 0) {
			$toolbar.hide();
		} else {
			$toolbar.show();
		}

		$('#mhm-gallery-selected-count').text(selected);
		$('#mhm-gallery-bulk-delete').prop('disabled', selected === 0);
		$('#mhm-gallery-select-all')
			.prop('checked', selected > 0 && selected === total)
			.prop('indeterminate', selected > 0 && selected < total);
	}

	$(document).on('change', '#mhm-gallery-select-all', function () {
		$('.gallery-item-checkbox').prop('checked', $(this).is(':checked'));
		updateBulkToolbar();
	});

	$(document).on('change', '.gallery-item-checkbox', function () {
		updateBulkToolbar();
	});

	$(document).on('click', '#mhm-gallery-bulk-delete', function () {
		var selectedIds = $('.gallery-item-checkbox:checked').map(function () {
			return $(this).val();
		}).get();

		if (selectedIds.length === 0) { return; }

		selectedIds.forEach(function (imageId) {
			$('.mhm-gallery-item[data-image-id="' + imageId + '"]').remove();
		});

		if ($('.mhm-gallery-item').length === 0) {
			$('.mhm-gallery-no-images').show();
		}

		$(document).trigger('galleryUpdated');
		updateBulkToolbar();
	});


})(jQuery);
