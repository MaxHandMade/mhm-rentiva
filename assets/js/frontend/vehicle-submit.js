/* global mhmVehicleSubmit, jQuery */
(function ($) {
    'use strict';

    // Toggle transfer section visibility based on service type.
    $(document).on('change', '#mhm-service-type', function () {
        var val = $(this).val();
        var $section = $('#mhm-transfer-section');
        if (!$section.length) return;

        if (val === 'transfer' || val === 'both') {
            $section.slideDown(200);
            $section.find('input[name="transfer_locations[]"]').closest('.mhm-vendor-form__field').addClass('is-required');
        } else {
            $section.slideUp(200);
            $section.find('input[name="transfer_locations[]"]').closest('.mhm-vendor-form__field').removeClass('is-required');
        }
    });

    // ──────────────────────────────────────────────
    // New vehicle submission
    // ──────────────────────────────────────────────
    $(document).on('submit', '#mhm-vehicle-submit-form', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#mhm-vehicle-submit-btn');
        var $spinner = $('#mhm-vehicle-submit-spinner');
        var $msg     = $('#mhm-vehicle-submit-msg');

        var minPhotos = parseInt((typeof mhmVehicleSubmit !== 'undefined' && mhmVehicleSubmit.min_photos) ? mhmVehicleSubmit.min_photos : 4, 10);
        var maxPhotos = parseInt((typeof mhmVehicleSubmit !== 'undefined' && mhmVehicleSubmit.max_photos) ? mhmVehicleSubmit.max_photos : 8, 10);
        var photoInput = $form.find('input[type="file"]').filter(function () {
            return this.name === 'photos[]' || this.name === 'photos';
        })[0];
        var photoCount = (photoInput && photoInput.files) ? photoInput.files.length : 0;

        if (photoCount < minPhotos) {
            $msg.addClass('mhm-vendor-notice--error').text('En az ' + minPhotos + ' fotoğraf yüklemelisiniz.').show();
            $('html, body').animate({ scrollTop: $msg.offset().top - 100 }, 300);
            return false;
        }
        if (photoCount > maxPhotos) {
            $msg.addClass('mhm-vendor-notice--error').text('En fazla ' + maxPhotos + ' fotoğraf yükleyebilirsiniz.').show();
            $('html, body').animate({ scrollTop: $msg.offset().top - 100 }, 300);
            return false;
        }

        var serviceType = $form.find('#mhm-service-type').val();
        var $transferSection = $('#mhm-transfer-section');
        if ((serviceType === 'transfer' || serviceType === 'both') && $transferSection.length) {
            if ($form.find('input[name="transfer_locations[]"]:checked').length === 0) {
                $msg.addClass('mhm-vendor-notice--error').text('Transfer hizmeti için en az bir konum seçmelisiniz.').show();
                $('html, body').animate({ scrollTop: $transferSection.offset().top - 100 }, 300);
                return false;
            }
        }

        $btn.prop('disabled', true);
        $spinner.show();
        $msg.hide().removeClass('mhm-vendor-notice--success mhm-vendor-notice--error');

        $.ajax({
            url: mhmVehicleSubmit.ajaxUrl,
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function (response) {
                if (typeof response !== 'object' || response === null) {
                    $btn.prop('disabled', false);
                    $spinner.hide();
                    $msg.addClass('mhm-vendor-notice--error').text('Oturumunuz sona ermiş. Lütfen sayfayı yenileyip tekrar deneyin.').show();
                    return;
                }
                if (response.success) {
                    $form.fadeOut(300);
                    $msg.addClass('mhm-vendor-notice--success').text(mhmVehicleSubmit.successMsg).show();
                    document.dispatchEvent(new Event('mhm_vehicle_submitted'));
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : mhmVehicleSubmit.errorMsg;
                    $msg.addClass('mhm-vendor-notice--error').text(msg).show();
                    $btn.prop('disabled', false);
                    $spinner.hide();
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $spinner.hide();
                $msg.addClass('mhm-vendor-notice--error').text('Sunucu hatası. Lütfen tekrar deneyin.').show();
            }
        });
    });

    // ──────────────────────────────────────────────
    // Edit vehicle — open edit panel
    // ──────────────────────────────────────────────
    $(document).on('click', '.mhm-edit-vehicle-btn', function () {
        var vehicleId = $(this).data('vehicle-id');
        var $panel = $('#mhm-edit-vehicle-panel');
        var $body  = $('#mhm-edit-vehicle-body');

        $panel.slideDown(200);
        $body.html('<p style="padding:20px;text-align:center;color:#888">Yükleniyor...</p>');

        // Scroll to edit panel.
        $('html, body').animate({ scrollTop: $panel.offset().top - 80 }, 300);

        $.ajax({
            url: mhmVehicleSubmit.ajaxUrl,
            type: 'GET',
            data: {
                action: 'mhm_vehicle_get_edit_data',
                vehicle_id: vehicleId,
                nonce: mhmVehicleSubmit.nonce
            },
            success: function (res) {
                if (!res.success) {
                    $body.html('<p class="mhm-vendor-notice mhm-vendor-notice--error">' + (res.data && res.data.message ? res.data.message : 'Hata') + '</p>');
                    return;
                }
                buildEditForm($body, res.data);
            },
            error: function () {
                $body.html('<p class="mhm-vendor-notice mhm-vendor-notice--error">Sunucu hatası.</p>');
            }
        });
    });

    $(document).on('click', '#mhm-close-edit-vehicle', function () {
        $('#mhm-edit-vehicle-panel').slideUp(200);
    });

    // Build the edit form from vehicle data.
    function buildEditForm($container, v) {
        // Get form HTML from the existing submit form as a template reference.
        var $origForm = $('#mhm-vehicle-submit-form');
        if (!$origForm.length) return;

        // Clone the original form structure.
        var $form = $origForm.clone();
        $form.attr('id', 'mhm-vehicle-edit-form');
        $form.find('input[name="action"]').val('mhm_vehicle_update');

        // Add vehicle_id hidden field.
        $form.prepend('<input type="hidden" name="vehicle_id" value="' + v.id + '">');

        // Fill location select (if present in form).
        if (v.location_id) {
            $form.find('[name="location_id"]').val(String(v.location_id));
        }

        // Fill text/number inputs.
        $form.find('[name="description"]').val(v.description || '');
        $form.find('[name="brand"]').val(v.brand);
        $form.find('[name="model"]').val(v.model);
        $form.find('[name="year"]').val(String(v.year));
        $form.find('[name="color"]').val(v.color);
        // City is locked to vendor city — keep existing value, ensure readonly.
        var $cityInput = $form.find('[name="city"]');
        if ($cityInput.attr('readonly') === undefined && v.city) {
            $cityInput.val(v.city);
        }
        $form.find('[name="price_per_day"]').val(v.price_per_day);
        $form.find('[name="mileage"]').val(v.mileage);
        $form.find('[name="license_plate"]').val(v.license_plate);
        $form.find('[name="seats"]').val(v.seats);
        $form.find('[name="doors"]').val(v.doors);
        $form.find('[name="transmission"]').val(v.transmission);
        $form.find('[name="fuel_type"]').val(v.fuel_type);
        $form.find('[name="engine_size"]').val(v.engine_size);
        $form.find('[name="service_type"]').val(v.service_type);

        // Category.
        if (v.category_id) {
            $form.find('[name="vehicle_category"]').val(String(v.category_id));
        }

        // Features checkboxes.
        var features = Array.isArray(v.features) ? v.features : [];
        $form.find('input[name="features[]"]').each(function () {
            $(this).prop('checked', features.indexOf($(this).val()) !== -1);
        });

        // Equipment checkboxes.
        var equipment = Array.isArray(v.equipment) ? v.equipment : [];
        $form.find('input[name="equipment[]"]').each(function () {
            $(this).prop('checked', equipment.indexOf($(this).val()) !== -1);
        });

        // Transfer locations.
        var tLocs = Array.isArray(v.transfer_locations) ? v.transfer_locations.map(String) : [];
        $form.find('input[name="transfer_locations[]"]').each(function () {
            $(this).prop('checked', tLocs.indexOf($(this).val()) !== -1);
        });

        // Transfer routes — check and directly enable price inputs (cloned form has no inline listeners).
        var tRoutes = Array.isArray(v.transfer_routes) ? v.transfer_routes.map(String) : [];
        $form.find('input[name="transfer_routes[]"]').each(function () {
            var checked = tRoutes.indexOf($(this).val()) !== -1;
            $(this).prop('checked', checked);
            var routeId = $(this).data('route-id');
            var $price = $form.find('.mhm-route-price-input[data-route-id="' + routeId + '"]');
            if ($price.length) {
                $price.prop('disabled', !checked);
            }
        });

        // Transfer capacity fields
        if (v.transfer_max_pax) {
            var maxPaxEl = $form.find('#mhm-transfer-max-pax');
            if (maxPaxEl.length) maxPaxEl.val(v.transfer_max_pax);
        }
        if (v.transfer_luggage_score) {
            var luggageEl = $form.find('#mhm-transfer-luggage-score');
            if (luggageEl.length) luggageEl.val(v.transfer_luggage_score);
        }
        if (v.transfer_max_big_luggage) {
            var bigEl = $form.find('#mhm-transfer-max-big');
            if (bigEl.length) bigEl.val(v.transfer_max_big_luggage);
        }
        if (v.transfer_max_small_luggage) {
            var smallEl = $form.find('#mhm-transfer-max-small');
            if (smallEl.length) smallEl.val(v.transfer_max_small_luggage);
        }

        // Transfer route prices — check route checkboxes + fill prices
        if (v.transfer_routes && Array.isArray(v.transfer_routes)) {
            v.transfer_routes.forEach(function(routeId) {
                var cb = $form.find('.mhm-route-checkbox[data-route-id="'+routeId+'"]');
                if (cb.length) {
                    cb.prop('checked', true);
                    cb[0].dispatchEvent(new Event('change'));
                }
            });
        }
        if (v.transfer_route_prices && typeof v.transfer_route_prices === 'object') {
            Object.keys(v.transfer_route_prices).forEach(function(routeId) {
                var input = $form.find('.mhm-route-price-input[data-route-id="'+routeId+'"]');
                if (input.length) {
                    input.val(v.transfer_route_prices[routeId]);
                }
            });
        }

        // Show/hide transfer section based on service type.
        var $transferSec = $form.find('#mhm-transfer-section');
        if ($transferSec.length) {
            if (v.service_type === 'transfer' || v.service_type === 'both') {
                $transferSec.show();
            } else {
                $transferSec.hide();
            }
        }

        // Photos — make not required for edit (existing photos remain).
        $form.find('input[name="photos[]"]').removeAttr('required');

        // Vehicle registration — make not required for edit (already uploaded).
        $form.find('input[name="vehicle_registration"]').removeAttr('required');

        // Gallery preview with featured selection, reorder, and delete.
        var galleryHtml = '';
        if (v.gallery && v.gallery.length) {
            galleryHtml = '<div class="mhm-edit-gallery" id="mhm-edit-gallery" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">';
            galleryHtml += '<input type="hidden" name="gallery_order" id="mhm-gallery-order">';
            v.gallery.forEach(function (img, idx) {
                var isFeatured = img.id === v.thumbnail_id;
                galleryHtml += '<div class="mhm-edit-gallery__item" data-img-id="' + img.id + '" data-img-url="' + img.url + '" style="position:relative;width:110px;border-radius:8px;overflow:hidden;border:2px solid ' + (isFeatured ? '#3b82f6' : '#e5e7eb') + ';background:#f9fafb">';
                galleryHtml += '<img src="' + img.url + '" style="width:100%;height:80px;object-fit:cover;display:block" alt="">';
                galleryHtml += '<div style="display:flex;justify-content:space-between;padding:4px 6px;gap:4px">';
                galleryHtml += '<button type="button" class="mhm-edit-gallery__set-featured" data-img-id="' + img.id + '" style="font-size:10px;padding:2px 6px;border:1px solid ' + (isFeatured ? '#3b82f6' : '#d1d5db') + ';background:' + (isFeatured ? '#3b82f6' : '#fff') + ';color:' + (isFeatured ? '#fff' : '#374151') + ';border-radius:4px;cursor:pointer;flex:1" title="Ana görsel yap">★ ' + (isFeatured ? 'Ana' : 'Seç') + '</button>';
                galleryHtml += '<button type="button" class="mhm-edit-gallery__move-left" style="font-size:10px;padding:2px 4px;border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:4px;cursor:pointer" title="Sola taşı">◀</button>';
                galleryHtml += '<button type="button" class="mhm-edit-gallery__move-right" style="font-size:10px;padding:2px 4px;border:1px solid #d1d5db;background:#fff;color:#374151;border-radius:4px;cursor:pointer" title="Sağa taşı">▶</button>';
                galleryHtml += '<button type="button" class="mhm-edit-gallery__remove" data-img-id="' + img.id + '" style="font-size:10px;padding:2px 4px;border:1px solid #fca5a5;background:#fef2f2;color:#dc2626;border-radius:4px;cursor:pointer" title="Sil">✕</button>';
                galleryHtml += '</div>';
                galleryHtml += '</div>';
            });
            galleryHtml += '</div>';
            galleryHtml += '<p class="mhm-vendor-form__hint" style="margin-top:4px;font-size:12px">★ ile ana görsel seçin. ◀▶ ile sıralayın. ✕ ile silin. İlk sıradaki görsel ana görsel olur.</p>';
        }

        // Insert gallery preview before the photo upload input.
        var $photosSection = $form.find('input[name="photos[]"]').closest('.mhm-vendor-form__section');
        if ($photosSection.length && galleryHtml) {
            $photosSection.find('h3').after(galleryHtml);
        }

        // Change submit button text.
        $form.find('#mhm-vehicle-submit-btn').attr('id', 'mhm-vehicle-edit-btn').text('Değişiklikleri Kaydet');
        $form.find('#mhm-vehicle-submit-spinner').attr('id', 'mhm-vehicle-edit-spinner');

        // Add warning about re-review.
        var warning = '<div class="mhm-vendor-notice mhm-vendor-notice--warn" style="margin-bottom:16px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:4px;font-size:13px">'
            + '<strong>⚠ Dikkat:</strong> Fiyat, hizmet türü, şehir, yıl, marka veya model değişikliklerinde aracınız tekrar incelemeye alınacaktır.'
            + '</div>';

        // Add edit-specific message area.
        var $editMsg = $('<div id="mhm-vehicle-edit-msg" class="mhm-vendor-notice" style="display:none"></div>');

        $container.empty().append($editMsg).append(warning).append($form);

        // Reinitialize route checkbox → price input toggle for the cloned form.
        $form.find('.mhm-route-checkbox').on('change', function () {
            var routeId = $(this).data('route-id');
            var $price = $form.find('.mhm-route-price-input[data-route-id="' + routeId + '"]');
            if ($price.length) {
                $price.prop('disabled', !this.checked);
                if (!this.checked) { $price.val(''); }
            }
        });

        // Reinitialize transfer toggle for the cloned form.
        var $editServiceType = $form.find('#mhm-service-type');
        if ($editServiceType.length) {
            $editServiceType.on('change', function () {
                var val = $(this).val();
                var $sec = $form.find('#mhm-transfer-section');
                if (val === 'transfer' || val === 'both') {
                    $sec.slideDown(200);
                } else {
                    $sec.slideUp(200);
                }
            });
        }
    }

    // Gallery image removal.
    $(document).on('click', '.mhm-edit-gallery__remove', function (e) {
        e.preventDefault();
        $(this).closest('.mhm-edit-gallery__item').fadeOut(200, function () {
            $(this).remove();
            updateGalleryOrder();
            refreshFeaturedBadge();
        });
    });

    // Set featured image.
    $(document).on('click', '.mhm-edit-gallery__set-featured', function (e) {
        e.preventDefault();
        var $item = $(this).closest('.mhm-edit-gallery__item');
        var $gallery = $item.closest('.mhm-edit-gallery');
        // Move to first position.
        $gallery.prepend($item);
        updateGalleryOrder();
        refreshFeaturedBadge();
    });

    // Move image left.
    $(document).on('click', '.mhm-edit-gallery__move-left', function (e) {
        e.preventDefault();
        var $item = $(this).closest('.mhm-edit-gallery__item');
        var $prev = $item.prev('.mhm-edit-gallery__item');
        if ($prev.length) {
            $item.insertBefore($prev);
            updateGalleryOrder();
            refreshFeaturedBadge();
        }
    });

    // Move image right.
    $(document).on('click', '.mhm-edit-gallery__move-right', function (e) {
        e.preventDefault();
        var $item = $(this).closest('.mhm-edit-gallery__item');
        var $next = $item.next('.mhm-edit-gallery__item');
        if ($next.length) {
            $item.insertAfter($next);
            updateGalleryOrder();
            refreshFeaturedBadge();
        }
    });

    function updateGalleryOrder() {
        var order = [];
        $('.mhm-edit-gallery__item:visible').each(function () {
            order.push({
                id: parseInt($(this).data('img-id'), 10),
                url: $(this).data('img-url'),
                alt: '',
                title: ''
            });
        });
        $('#mhm-gallery-order').val(JSON.stringify(order));
    }

    function refreshFeaturedBadge() {
        $('.mhm-edit-gallery__item').each(function (idx) {
            var $btn = $(this).find('.mhm-edit-gallery__set-featured');
            var isFeatured = idx === 0;
            $(this).css('border-color', isFeatured ? '#3b82f6' : '#e5e7eb');
            $btn.css({ background: isFeatured ? '#3b82f6' : '#fff', color: isFeatured ? '#fff' : '#374151', 'border-color': isFeatured ? '#3b82f6' : '#d1d5db' });
            $btn.html('★ ' + (isFeatured ? 'Ana' : 'Seç'));
        });
    }

    // ──────────────────────────────────────────────
    // Edit vehicle — submit handler
    // ──────────────────────────────────────────────
    $(document).on('submit', '#mhm-vehicle-edit-form', function (e) {
        e.preventDefault();

        var $form      = $(this);
        var $btn       = $form.find('#mhm-vehicle-edit-btn');
        var $spin      = $form.find('#mhm-vehicle-edit-spinner');
        var $msg       = $('#mhm-vehicle-edit-msg');
        var vehicleId  = $form.find('input[name="vehicle_id"]').val();
        var photoInput = $form.find('input[name="photos[]"]')[0];
        var hadNewPhotos = photoInput && photoInput.files && photoInput.files.length > 0;

        // Update gallery order before submit.
        updateGalleryOrder();

        $btn.prop('disabled', true);
        $spin.show();
        $msg.hide().removeClass('mhm-vendor-notice--success mhm-vendor-notice--error mhm-vendor-notice--warn');

        $.ajax({
            url: mhmVehicleSubmit.ajaxUrl,
            type: 'POST',
            data: new FormData(this),
            processData: false,
            contentType: false,
            success: function (res) {
                $btn.prop('disabled', false);
                $spin.hide();
                if (res.success) {
                    if (hadNewPhotos) {
                        // New photos were uploaded — reload the edit form so the user
                        // can see, reorder and set the featured image before finalising.
                        var $body = $('#mhm-edit-vehicle-body');
                        $.ajax({
                            url: mhmVehicleSubmit.ajaxUrl,
                            type: 'GET',
                            data: {
                                action: 'mhm_vehicle_get_edit_data',
                                vehicle_id: vehicleId,
                                nonce: mhmVehicleSubmit.nonce
                            },
                            success: function (fetchRes) {
                                if (fetchRes.success) {
                                    buildEditForm($body, fetchRes.data);
                                    $('#mhm-vehicle-edit-msg')
                                        .addClass('mhm-vendor-notice--success')
                                        .text('Fotoğraflar yüklendi. Ana resmi ve sıralamayı ayarlayıp tekrar kaydedin.')
                                        .show();
                                    $('html, body').animate({ scrollTop: $body.offset().top - 100 }, 300);
                                } else {
                                    location.reload();
                                }
                            },
                            error: function () { location.reload(); }
                        });
                    } else {
                        var cls = res.data.rereview ? 'mhm-vendor-notice--warn' : 'mhm-vendor-notice--success';
                        $msg.addClass(cls).text(res.data.message).show();
                        $('html, body').animate({ scrollTop: $msg.offset().top - 100 }, 300);
                        setTimeout(function () { location.reload(); }, 2000);
                    }
                } else {
                    var errMsg = (res.data && res.data.message) ? res.data.message : 'Bir hata oluştu.';
                    $msg.addClass('mhm-vendor-notice--error').text(errMsg).show();
                }
            },
            error: function () {
                $btn.prop('disabled', false);
                $spin.hide();
                $msg.addClass('mhm-vendor-notice--error').text('Sunucu hatası. Lütfen tekrar deneyin.').show();
            }
        });
    });

}(jQuery));
