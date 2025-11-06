/**
 * Vehicle Rating Form JavaScript
 * Gerçek kullanıcı rating sistemi
 */

(function ($) {
    'use strict';

    class VehicleRatingForm {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initStarRating();
            this.loadUserRatings();
            this.initModals();

            // Sayfa yüklendiğinde rating listesini otomatik yükle - biraz gecikme ile
            setTimeout(() => {
                this.loadInitialRatings();
            }, 100);
        }

        bindEvents() {
            $(document).on('submit', '.rv-rating-form-content', this.handleSubmit.bind(this));
            $(document).on('change', '.rv-rating-stars-input input[type="radio"]', this.handleStarChange.bind(this));
            // Submit button click'i kaldırıldı - sadece form submit kullan
            $(document).on('click', '.rv-delete-rating', this.handleDeleteRating.bind(this));
            $(document).on('click', '.rv-edit-comment-btn', this.handleEditComment.bind(this));
            $(document).on('click', '.rv-delete-comment-btn', this.handleDeleteComment.bind(this));

            // Modal events
            $(document).on('click', '.rv-show-login-form', this.showLoginModal.bind(this));
            $(document).on('click', '.rv-show-register-form', this.showRegisterModal.bind(this));
            $(document).on('click', '.rv-modal-close', this.hideModals.bind(this));
            $(document).on('click', '.rv-login-modal, .rv-register-modal', this.hideModalsOnBackdrop.bind(this));
        }

        initStarRating() {
            $('.rv-rating-stars-input').each(function () {
                const $container = $(this);
                const $inputs = $container.find('input[type="radio"]');
                const $labels = $container.find('label');

                $labels.on('click', function (e) {
                    e.preventDefault();
                    const $label = $(this);
                    const inputId = $label.attr('for');
                    const $input = $container.find('input#' + inputId);
                    const value = parseInt($input.val());

                    if ($input.length) {
                        $inputs.prop('checked', false);
                        $input.prop('checked', true);

                        // Tüm yıldızları temizle
                        $labels.removeClass('active');

                        // Seçili yıldızdan önceki tüm yıldızları aktif yap
                        $labels.each(function (index) {
                            if (index < value) {
                                $(this).addClass('active');
                            }
                        });
                    }
                });

                $inputs.on('change', function () {
                    const $input = $(this);
                    const value = parseInt($input.val());

                    // Tüm yıldızları temizle
                    $labels.removeClass('active');

                    // Seçili yıldızdan önceki tüm yıldızları aktif yap
                    $labels.each(function (index) {
                        if (index < value) {
                            $(this).addClass('active');
                        }
                    });
                });
            });
        }

        initModals() {
            // Modal functionality
        }

        showLoginModal(e) {
            e.preventDefault();
            $('.rv-login-modal').fadeIn(300);
        }

        showRegisterModal(e) {
            e.preventDefault();
            $('.rv-register-modal').fadeIn(300);
        }

        hideModals() {
            $('.rv-login-modal, .rv-register-modal').fadeOut(300);
        }

        hideModalsOnBackdrop(e) {
            if (e.target === e.currentTarget) {
                this.hideModals();
            }
        }

        handleSubmit(e) {
            e.preventDefault();

            // Double-click koruması
            const $form = $(e.target).closest('.rv-rating-form-content');
            const $submitBtn = $form.find('button[type="submit"]');

            if ($submitBtn.prop('disabled')) {
                return;
            }

            // Login kontrolü PHP tarafında yapılıyor, JavaScript'te kontrol etmiyoruz

            const $container = $form.closest('.rv-rating-form');
            const vehicleId = $container.attr('data-vehicle-id');
            const rating = $form.find('input[name="rating"]:checked').val();
            const comment = $form.find('textarea[name="comment"]').val();


            // Guest kullanıcılar için isim ve email alanları
            const guestName = $form.find('input[name="guest_name"]').val();
            const guestEmail = $form.find('input[name="guest_email"]').val();



            if (!rating) {
                this.showMessage('Please select a rating.', 'error');
                return;
            }


            const formData = {
                action: 'mhm_rentiva_submit_rating',
                vehicle_id: vehicleId,
                rating: rating,
                comment: comment,
                nonce: $form.find('input[name="nonce"]').val()
            };


            // Guest kullanıcılar için isim ve email alanlarını ekle
            if (guestName && guestEmail) {
                formData.guest_name = guestName;
                formData.guest_email = guestEmail;
            }


            $.ajax({
                url: window.mhmVehicleRating?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: formData,
                timeout: 10000,
                beforeSend: () => {
                    $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('✅ ' + (response.data.message || 'Your comment has been submitted successfully! Awaiting approval.'), 'success');
                        // Sayfa yenile
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000); // 3 saniye bekle
                    } else {
                        this.showMessage(response.data?.message || 'An error occurred', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('An error occurred while submitting the rating.', 'error');
                },
                complete: () => {
                    $form.find('button[type="submit"]').prop('disabled', false).text('Submit Rating');
                },
                timeout: () => {
                    this.showMessage('Request timed out. Please try again.', 'error');
                }
            });
        }

        handleStarChange(e) {
            const $input = $(e.target);
            const $label = $input.closest('label');
            const $container = $input.closest('.rv-rating-stars-input');

            $container.find('label').removeClass('active');
            $label.addClass('active');
        }

        handleDeleteRating(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete your rating?')) {
                return;
            }

            const $button = $(e.target);
            const vehicleId = $button.attr('data-vehicle-id');

            $.ajax({
                url: window.mhmVehicleRating?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_delete_rating',
                    vehicle_id: vehicleId,
                    nonce: $('input[name="rating_nonce"]').val()
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('✅ Your comment has been deleted successfully!', 'success');
                        // Sayfa yenile
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showMessage('Error deleting rating.', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Error deleting rating.', 'error');
                }
            });
        }

        loadInitialRatings() {
            const self = this;
            $('.rv-rating-form').each((index, element) => {
                const $element = $(element);
                const vehicleId = $element.attr('data-vehicle-id');

                if (vehicleId && vehicleId !== 'undefined') {
                    self.loadUserRatings(vehicleId);
                }
            });
        }

        loadUserRatings(vehicleId = null) {
            const self = this;

            if (!vehicleId) {
                $('.rv-rating-form').each((index, element) => {
                    const $element = $(element);
                    const id = $element.attr('data-vehicle-id');
                    if (id && id !== 'undefined') {
                        self.loadUserRatings(id);
                    }
                });
                return;
            }

            // AJAX çağrısı yapılmış mı kontrol et
            const $container = $(`.rv-rating-form[data-vehicle-id="${vehicleId}"]`);
            if ($container.data('ajax-called')) {
                return; // Zaten AJAX çağrısı yapılmış
            }

            const $ratingsList = $container.find('.rv-ratings-list');

            if ($ratingsList.length === 0) {
                return;
            }

            // Eğer zaten yorumlar varsa (PHP'den yüklenmiş), AJAX yapma
            if ($ratingsList.find('.rv-reviews-section').length > 0 || $ratingsList.find('.rv-review-item').length > 0) {
                return;
            }

            // Eğer "No reviews yet" mesajı varsa, AJAX yapma
            if ($ratingsList.find('.rv-no-reviews').length > 0) {
                return;
            }

            const ajaxUrl = window.mhmVehicleRating?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php';

            if (!ajaxUrl || ajaxUrl === 'undefined') {
                return;
            }
            // AJAX çağrısı yapıldığını işaretle
            $container.data('ajax-called', true);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_get_vehicle_rating_list',
                    vehicle_id: vehicleId
                },
                timeout: 10000,
                success: (response) => {
                    if (response.success && response.data) {
                        self.renderRatingsList(response.data.ratings || []);
                    } else {
                        // AJAX başarısız olursa mevcut yorumları koru
                    }
                },
                error: (xhr, status, error) => {
                    // AJAX hata verirse mevcut yorumları koru
                },
                timeout: () => {
                    // AJAX timeout olursa mevcut yorumları koru
                }
            });
        }

        renderRatingsList(ratings) {
            const $container = $('.rv-ratings-list');

            // Eğer zaten yorumlar varsa (PHP'den yüklenmiş), render etme
            if ($container.find('.rv-reviews-section').length > 0 || $container.find('.rv-review-item').length > 0) {
                return;
            }

            // Loading state'i kaldır
            $container.find('.rv-loading-ratings').remove();

            if (!ratings || ratings.length === 0) {
                $container.html('<p class="rv-no-ratings">' + (window.mhmVehicleRating?.no_ratings || 'No reviews yet.') + '</p>');
                return;
            }

            let html = '<div class="rv-ratings-header"><h4>' + (window.mhmVehicleRating?.reviews_title || 'Reviews') + '</h4></div>';

            ratings.forEach((rating, index) => {
                const stars = '★'.repeat(rating.rating) + '☆'.repeat(5 - rating.rating);
                const date = new Date(rating.date || rating.created_at).toLocaleDateString();

                html += `
                    <div class="rv-rating-item">
                        <div class="rv-rating-user">
                            <span class="rv-rating-name">${rating.display_name || rating.customer_name || 'Anonymous'}</span>
                            <div class="rv-rating-stars">${stars}</div>
                            <span class="rv-rating-date">${date}</span>
                        </div>
                        ${rating.comment ? `<div class="rv-rating-comment">${rating.comment}</div>` : ''}
                    </div>
                `;
            });

            $container.html(html);
        }

        showMessage(message, type = 'info') {
            // Önceki mesajları temizle
            $('.rv-message').remove();

            const $message = $(`<div class="rv-message rv-message-${type}">${message}</div>`);
            $('.rv-rating-form').prepend($message);

            setTimeout(() => {
                $message.fadeOut(() => $message.remove());
            }, 5000);
        }

        handleEditComment(e) {
            e.preventDefault();

            // Settings kontrolü
            if (!(window.mhmVehicleRating?.settings?.allow_editing ?? true)) {
                this.showMessage('❌ Comment editing is disabled.', 'error');
                return;
            }

            const $btn = $(e.target).closest('.rv-edit-comment-btn');
            const commentId = $btn.data('comment-id');
            const rating = $btn.data('rating');
            const comment = $btn.data('comment');

            // Form'u doldur
            const $form = $('.rv-rating-form-content');
            const $ratingInputs = $form.find('.rv-rating-stars-input input[type="radio"]');
            const $commentTextarea = $form.find('textarea[name="comment"]');

            // Rating'i set et
            $ratingInputs.prop('checked', false);
            $ratingInputs.filter(`[value="${rating}"]`).prop('checked', true);

            // Star görünümünü güncelle
            $form.find('.rv-rating-stars-input label').removeClass('active');
            $ratingInputs.each(function (index) {
                if (index < rating) {
                    $(this).siblings('label').addClass('active');
                }
            });

            // Comment'i set et
            $commentTextarea.val(comment);

            // Form'u güncelleme moduna al
            $form.data('edit-mode', true);
            $form.data('comment-id', commentId);

            // Submit button text'ini güncelle
            $form.find('button[type="submit"]').text('Update Rating');

            // Form'a scroll
            $('html, body').animate({
                scrollTop: $form.offset().top - 100
            }, 500);

            this.showMessage('✅ ' + (window.mhmVehicleRating?.strings?.edit_loaded || 'Your comment has been loaded for editing.'), 'info');
        }

        handleDeleteComment(e) {
            e.preventDefault();

            // Settings kontrolü
            if (!(window.mhmVehicleRating?.settings?.allow_deletion ?? true)) {
                this.showMessage('❌ Comment deletion is disabled.', 'error');
                return;
            }

            const $btn = $(e.target).closest('.rv-delete-comment-btn');
            const commentId = $btn.data('comment-id');

            if (!confirm(window.mhmVehicleRating?.strings?.delete_confirm || 'Are you sure you want to delete this comment?')) {
                return;
            }

            const ajaxUrl = window.mhmVehicleRating?.ajax_url || window.location.origin + '/wp-admin/admin-ajax.php';

            // Nonce'u form'dan al
            const nonce = $('.rv-rating-form-content input[name="nonce"]').val();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_delete_comment',
                    comment_id: commentId,
                    nonce: nonce
                },
                timeout: 10000,
                beforeSend: () => {
                    $btn.prop('disabled', true).text(window.mhmVehicleRating?.strings?.deleting || 'Deleting...');
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage('✅ ' + (window.mhmVehicleRating?.strings?.delete_success || 'Your comment has been deleted successfully!'), 'success');

                        // Yorumu DOM'dan kaldır
                        $(`.rv-review-item[data-comment-id="${commentId}"]`).fadeOut(300, function () {
                            $(this).remove();
                        });

                        // Sayfayı yenile
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        this.showMessage('❌ ' + (window.mhmVehicleRating?.strings?.delete_error || 'Error deleting comment: ') + (response.data?.message || (window.mhmVehicleRating?.strings?.unknown_error || 'Unknown error')), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showMessage('❌ ' + (window.mhmVehicleRating?.strings?.delete_error_retry || 'Error deleting comment. Please try again.'), 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> ' + (window.mhmVehicleRating?.strings?.delete || 'Delete'));
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function () {
        new VehicleRatingForm();
    });

})(jQuery);