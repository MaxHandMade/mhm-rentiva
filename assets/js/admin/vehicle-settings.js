/**
 * Vehicle Settings admin page behavior.
 *
 * Consolidates the three inline blocks that VehicleSettings::render_settings_page(),
 * ::render_display_tab() and ::render_definitions_tab() used to echo. The per-request
 * nonce, the active tab and every translatable string are provided through
 * window.mhmVehicleSettings (wp_localize_script). Tab-specific handlers simply match
 * no elements when the other tab is rendered, so a single bundle is safe.
 */
jQuery(document).ready(function($) {
	var S       = window.mhmVehicleSettings || {};
	var i18n    = S.i18n || {};
	var vsNonce = S.nonce || '';

	// --- Reset settings (render_settings_page) ---------------------------------
	$('#reset-vehicle-settings').on('click', function() {
		if (confirm(i18n.confirmResetAll || '')) {
			const btn = $(this);
			btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'mhm_rentiva_reset_vehicle_settings',
				tab: S.activeTab || 'definitions',
				nonce: vsNonce
			}, function(response) {
				if (response.success) {
					window.location.reload();
				} else {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(response.data.message || 'Error resetting settings', 'error');
					}
					btn.prop('disabled', false);
				}
			});
		}
	});

	// --- Display Options tab (render_display_tab) ------------------------------
	function filterFieldList($input) {
		var targetSelector = $input.data('target');
		var query = String($input.val() || '').toLowerCase().trim();
		var $target = $(targetSelector);
		if (!$target.length) {
			return;
		}

		$target.children('li').each(function() {
			var labelText = $(this).find('.mhm-card-field-label').text().toLowerCase();
			$(this).toggle(query === '' || labelText.indexOf(query) !== -1);
		});
	}

	function applyAllFieldFilters() {
		$('.mhm-card-field-search').each(function() {
			filterFieldList($(this));
		});
	}

	$('.mhm-card-field-search').on('input', function() {
		filterFieldList($(this));
	});

	function updateDetailFieldsInput() {
		var items = [];
		$('#mhm-detail-fields-selected li').each(function() {
			items.push({
				type: $(this).data('fieldType'),
				key: $(this).data('fieldKey')
			});
		});
		$('#mhm-vehicle-detail-fields-input').val(JSON.stringify(items));
	}

	function refreshDetailEmptyState() {
		$('#mhm-detail-fields-selected, #mhm-detail-fields-available').each(function() {
			if ($(this).find('li').length === 0) {
				$(this).addClass('is-empty');
			} else {
				$(this).removeClass('is-empty');
			}
		});
	}

	if ($('#mhm-detail-fields-selected, #mhm-detail-fields-available').length) {
		$('#mhm-detail-fields-selected, #mhm-detail-fields-available').sortable({
			connectWith: '#mhm-detail-fields-selected, #mhm-detail-fields-available',
			placeholder: 'mhm-card-fields-placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			update: function() {
				updateDetailFieldsInput();
				refreshDetailEmptyState();
				applyAllFieldFilters();
			},
			receive: function() {
				updateDetailFieldsInput();
				refreshDetailEmptyState();
				applyAllFieldFilters();
			}
		}).disableSelection();
	}

	$('#mhm-detail-fields-available').on('click', 'li', function() {
		$(this).appendTo('#mhm-detail-fields-selected');
		updateDetailFieldsInput();
		refreshDetailEmptyState();
		applyAllFieldFilters();
	});

	$('#mhm-detail-fields-selected').on('click', '.remove-field', function(event) {
		event.preventDefault();
		event.stopPropagation();
		$(this).closest('li').appendTo('#mhm-detail-fields-available');
		updateDetailFieldsInput();
		refreshDetailEmptyState();
		applyAllFieldFilters();
	});

	refreshDetailEmptyState();
	updateDetailFieldsInput();
	applyAllFieldFilters();

	// Select all / Deselect all for comparison fields
	$('.mhm-select-all-btn').on('click', function() {
		var category = $(this).data('category');
		$('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', true);
	});

	$('.mhm-deselect-all-btn').on('click', function() {
		var category = $(this).data('category');
		$('.mhm-field-category[data-category="' + category + '"] input[type="checkbox"]').prop('checked', false);
	});

	// Display Settings Form Submit
	$('#vehicle-display-settings-form').on('submit', function(e) {
		e.preventDefault();

		var formData = $(this).serialize();

		$.post(ajaxurl, formData, function(response) {
			if (response.success) {
				if (typeof window.mhmShowNotice === 'function') {
					window.mhmShowNotice(i18n.saved || '', 'success');
				}
				window.location.reload();
			} else {
				if (typeof window.mhmShowNotice === 'function') {
					window.mhmShowNotice(i18n.errorSaving || '', 'error');
				}
			}
		});
	});

	// Clear All Card Fields
	$('#clear-card-fields').on('click', function() {
		var selectedList = $('#mhm-card-fields-selected');
		var availableList = $('#mhm-card-fields-available');

		selectedList.children('li').each(function() {
			var item = $(this);
			item.find('.remove-field').remove();
			availableList.append(item);
		});

		$('#mhm-vehicle-card-fields-input').val('[]');
		applyAllFieldFilters();
	});

	$('#clear-detail-fields').on('click', function() {
		var selectedList = $('#mhm-detail-fields-selected');
		var availableList = $('#mhm-detail-fields-available');

		selectedList.children('li').each(function() {
			var item = $(this);
			item.find('.remove-field').remove();
			availableList.append(item);
		});

		$('#mhm-vehicle-detail-fields-input').val('[]');
		refreshDetailEmptyState();
		applyAllFieldFilters();
	});

	// --- Field Definitions tab (render_definitions_tab) ------------------------
	// Show/Hide Options based on Type
	$('#new-custom-detail-type').on('change', function() {
		if ($(this).val() === 'select') {
			$('#new-custom-detail-options-wrapper').show();
		} else {
			$('#new-custom-detail-options-wrapper').hide();
		}
	});

	// Custom Detail Addition
	$('#add-custom-detail').on('click', function() {
		const name = $('#new-custom-detail-name').val().trim();
		const type = $('#new-custom-detail-type').val();
		const options = $('#new-custom-detail-options').val().trim();

		if (name) {
			const key = 'custom_' + Date.now();
			const label = name;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_add_custom_field',
					field_key: key,
					field_label: label,
					field_type: 'details',
					type: type,
					options: options,
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						const serverKey = response.data.key;
						let typeLabel = '';
						if (type === 'select') typeLabel = ' (' + (i18n.select || '') + ')';
						else if (type === 'number') typeLabel = ' (' + (i18n.number || '') + ')';

						$('#custom-details-list').append(`
						<div class="mhm-custom-item" data-key="${serverKey}">
							<label class="mhm-checkbox-item">
								<input type="checkbox" name="selected_details[]" value="${serverKey}" checked>
								<span>${label}${typeLabel}</span>
							</label>
							<button type="button" class="button-link remove-custom-detail" data-key="${serverKey}">&times;</button>
						</div>
						`);

						$('#new-custom-detail-name').val('');
						$('#new-custom-detail-options').val('');
						$('#new-custom-detail-type').val('text').trigger('change');

						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.detailAdded || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// Custom Feature Addition
	$('#add-custom-feature').on('click', function() {
		const name = $('#new-custom-feature-name').val().trim();

		if (name) {
			const key = 'custom_' + Date.now();
			const label = name;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_add_custom_field',
					field_key: key,
					field_label: label,
					field_type: 'features',
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						const serverKey = response.data.key;
						$('#custom-features-list').append(`
						<div class="mhm-custom-item" data-key="${serverKey}">
							<span>${label}</span>
							<button type="button" class="button button-small remove-custom-feature" data-key="${serverKey}">${i18n.remove || ''}</button>
						</div>
						`);

						$('#new-custom-feature-name').val('');
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.featureAdded || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// Custom Equipment Addition
	$('#add-custom-equipment').on('click', function() {
		const name = $('#new-custom-equipment-name').val().trim();

		if (name) {
			const key = 'custom_' + Date.now();
			const label = name;

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_add_custom_field',
					field_key: key,
					field_label: label,
					field_type: 'equipment',
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						const serverKey = response.data.key;
						$('#custom-equipment-list').append(`
						<div class="mhm-custom-item" data-key="${serverKey}">
							<span>${label}</span>
							<button type="button" class="button button-small remove-custom-equipment" data-key="${serverKey}">${i18n.remove || ''}</button>
						</div>
						`);

						$('#new-custom-equipment-name').val('');
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.equipmentAdded || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// Custom Detail Removal
	$(document).on('click', '.remove-custom-detail', function() {
		if (confirm(i18n.confirmRemoveDetail || '')) {
			const fieldKey = $(this).data('key');
			const item = $(this).closest('.mhm-custom-item');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_remove_custom_field',
					field_key: fieldKey,
					field_type: 'details',
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						item.fadeOut(300, function() {
							$(this).remove();
						});
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.detailRemoved || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// Custom Feature Removal
	$(document).on('click', '.remove-custom-feature', function() {
		if (confirm(i18n.confirmRemoveFeature || '')) {
			const fieldKey = $(this).data('key');
			const item = $(this).closest('.mhm-custom-item');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_remove_custom_field',
					field_key: fieldKey,
					field_type: 'features',
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						item.fadeOut(300, function() {
							$(this).remove();
						});
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.featureRemoved || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// Custom Equipment Removal
	$(document).on('click', '.remove-custom-equipment', function() {
		if (confirm(i18n.confirmRemoveEquipment || '')) {
			const fieldKey = $(this).data('key');
			const item = $(this).closest('.mhm-custom-item');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'mhm_rentiva_remove_custom_field',
					field_key: fieldKey,
					field_type: 'equipment',
					nonce: vsNonce
				},
				success: function(response) {
					if (response.success) {
						item.fadeOut(300, function() {
							$(this).remove();
						});
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice(i18n.equipmentRemoved || '', 'success');
						}
					} else {
						if (typeof window.mhmShowNotice === 'function') {
							window.mhmShowNotice((i18n.errPrefix || '') + ' ' + response.data, 'error');
						}
					}
				},
				error: function() {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.genericError || '', 'error');
					}
				}
			});
		}
	});

	// BULK OPERATIONS - Details
	$('#select-all-details').on('click', function() {
		$('input[name="selected_details[]"]').prop('checked', true);
	});

	$('#select-none-details').on('click', function() {
		$('input[name="selected_details[]"]:not([disabled])').prop('checked', false);
	});

	$('#rename-details').on('click', function() {
		showRenameModal('details');
	});

	// BULK OPERATIONS - Features
	$('#select-all-features').on('click', function() {
		$('input[name="selected_features[]"]').prop('checked', true);
	});

	$('#select-none-features').on('click', function() {
		$('input[name="selected_features[]"]').prop('checked', false);
	});

	$('#rename-features').on('click', function() {
		showRenameModal('features');
	});

	// BULK OPERATIONS - Equipment
	$('#select-all-equipment').on('click', function() {
		$('input[name="selected_equipment[]"]').prop('checked', true);
	});

	$('#select-none-equipment').on('click', function() {
		$('input[name="selected_equipment[]"]').prop('checked', false);
	});

	$('#rename-equipment').on('click', function() {
		showRenameModal('equipment');
	});

	// Form Submit (Save Settings)
	$('#vehicle-settings-form').on('submit', function(e) {
		e.preventDefault();

		const selectedDetails = [];
		const selectedFeatures = [];
		const selectedEquipment = [];

		$('input[name="selected_details[]"]:checked').each(function() {
			selectedDetails.push($(this).val());
		});

		$('input[name="selected_features[]"]:checked').each(function() {
			selectedFeatures.push($(this).val());
		});

		$('input[name="selected_equipment[]"]:checked').each(function() {
			selectedEquipment.push($(this).val());
		});

		const customDetails = {};
		const customFeatures = {};
		const customEquipment = {};

		$('#custom-details-list .mhm-custom-item').each(function() {
			const key = $(this).data('key');
			const label = $(this).find('span').text();
			customDetails[key] = label;
		});

		$('#custom-features-list .mhm-custom-item').each(function() {
			const key = $(this).data('key');
			const label = $(this).find('span').text();
			customFeatures[key] = label;
		});

		$('#custom-equipment-list .mhm-custom-item').each(function() {
			const key = $(this).data('key');
			const label = $(this).find('span').text();
			customEquipment[key] = label;
		});

		const updatedLabels = {
			details: {},
			features: {},
			equipment: {}
		};

		['details', 'features', 'equipment'].forEach(type => {
			$(`.mhm-checkbox-list input[name="selected_${type}[]"]`).each(function() {
				const key = $(this).val();
				const label = $(this).siblings('span').text();
				updatedLabels[type][key] = label;
			});
		});

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mhm_rentiva_save_vehicle_settings',
				selected_details: selectedDetails,
				selected_features: selectedFeatures,
				selected_equipment: selectedEquipment,
				custom_details: customDetails,
				custom_features: customFeatures,
				custom_equipment: customEquipment,
				updated_labels: updatedLabels,
				nonce: vsNonce
			},
			success: function(response) {
				if (response && response.success) {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.saved || '', 'success');
					}
					location.reload();
				} else {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice((i18n.errPrefix || '') + ' ' + (response && response.data ? response.data : 'Unknown error'), 'error');
					}
				}
			},
			error: function(xhr, status, error) {
				if (typeof window.mhmShowNotice === 'function') {
					window.mhmShowNotice((i18n.genericError || '') + ': ' + error, 'error');
				}
			}
		});
	});
});

// RENAME MODAL FUNCTION (defined globally; invoked by the definitions tab handlers).
window.showRenameModal = function(type) {
	var S       = window.mhmVehicleSettings || {};
	var i18n    = S.i18n || {};
	var vsNonce = S.nonce || '';

	if (jQuery('#mhm-rename-modal').length > 0) {
		return;
	}
	// Helper: escape special characters for safe use in HTML attributes and text
	function escAttr(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
	const fields = {};

	jQuery(`.mhm-checkbox-list input[name="selected_${type}[]"]`).each(function() {
		const key = jQuery(this).val();
		const label = jQuery(this).siblings('span').text();
		fields[key] = label;
	});

	let modalHtml = `
	<div id="mhm-rename-modal">
		<div class="mhm-rename-modal-card">
			<h3>${i18n.editFieldNames || ''}</h3>
			<div id="rename-fields-container">
	`;

	for (const [key, label] of Object.entries(fields)) {
		modalHtml += `
		<div class="mhm-rename-field-row">
			<label>${escAttr(label)}:</label>
			<input type="text" data-key="${escAttr(key)}" value="${escAttr(label)}">
		</div>
	`;
	}

	modalHtml += `
			</div>
			<div class="mhm-rename-modal-actions">
				<button type="button" id="cancel-rename" class="button">${i18n.cancel || ''}</button>
				<button type="button" id="save-rename" class="button button-primary">${i18n.save || ''}</button>
			</div>
		</div>
	</div>
	`;

	jQuery('body').append(modalHtml);
	jQuery('#mhm-rename-modal').addClass('is-open');
	jQuery('#mhm-rename-modal').on('click', function(e) {
		if (!jQuery(e.target).closest('.mhm-rename-modal-card').length) {
			jQuery('#mhm-rename-modal').remove();
		}
	});

	jQuery('#cancel-rename').on('click', function() {
		jQuery('#mhm-rename-modal').remove();
	});

	jQuery('#save-rename').on('click', function() {
		const newLabels = {};
		jQuery('#rename-fields-container input').each(function() {
			const key = jQuery(this).data('key');
			const newLabel = jQuery(this).val();
			newLabels[key] = newLabel;
		});

		jQuery('#rename-fields-container input').each(function() {
			const key = jQuery(this).data('key');
			const newLabel = newLabels[key];

			jQuery(`input[name="selected_${type}[]"][value="${key}"]`).siblings('span').text(newLabel);
		});

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'mhm_rentiva_update_field_labels',
				type: type,
				labels: newLabels,
				nonce: vsNonce
			},
			success: function(response) {
				if (response && response.success) {
					jQuery('#mhm-rename-modal').remove();

					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.fieldNamesSaved || '', 'success');
					}
				} else {
					if (typeof window.mhmShowNotice === 'function') {
						window.mhmShowNotice(i18n.fieldNamesError || '', 'error');
					}
				}
			},
			error: function() {
				if (typeof window.mhmShowNotice === 'function') {
					window.mhmShowNotice(i18n.genericError || '', 'error');
				}
			}
		});
	});
};
