/**
 * Vehicle Rating Form JavaScript
 * Real user rating system
 */

(function ($) {
    'use strict';

    class VehicleRatingForm {
        constructor() {
            this.init();
        }

        applySelectedStars($container, value) {
            const score = parseInt(value, 10) || 0;
            const $labels = $container.find('label.rv-star-input');

            $labels.removeClass('active');
            $labels.each(function () {
                const $label = $(this);
                const inputId = $label.attr('for');
                const $input = $container.find('input#' + inputId);
                const inputScore = parseInt($input.val(), 10) || 0;

                if (inputScore > 0 && inputScore <= score) {
                    $(this).addClass('active');
                }
            });
        }

        init() {
            this.bindEvents();
            this.initStarRating();
            this.initCharCounter();
            this.loadUserRatings();
            this.initModals();

            // Auto-load ratings list on page load - with slight delay
            setTimeout(() => {
                this.loadInitialRatings();
            }, 100);
        }

        bindEvents() {
            $(document).on('submit', '.rv-rating-form-content', this.handleSubmit.bind(this));
            $(document).on('change', '.rv-rating-stars-input input[type="radio"]', this.handleStarChange.bind(this));
            // Submit button click removed - only use form submit
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
            const self = this;

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
                        self.applySelectedStars($container, value);
                    }
                });

                $inputs.on('change', function () {
                    const $input = $(this);
                    const value = parseInt($input.val());
                    self.applySelectedStars($container, value);
                });

                // Preview selected score while hovering stars.
                $labels.on('mouseenter', function () {
                    const $label = $(this);
                    const inputId = $label.attr('for');
                    const $input = $container.find('input#' + inputId);
                    const value = parseInt($input.val(), 10) || 0;
                    self.applySelectedStars($container, value);
                });

                // Restore persisted value when hover ends.
                $container.on('mouseleave', function () {
                    const $checkedInput = $inputs.filter(':checked').first();
                    const value = $checkedInput.length ? parseInt($checkedInput.val(), 10) : 0;
                    self.applySelectedStars($container, value);
                });

                // Ensure pre-filled user rating is visible on initial render.
                const $checked = $inputs.filter(':checked').first();
                if ($checked.length) {
                    self.applySelectedStars($container, $checked.val());
                }
            });
        }

        initCharCounter() {
            const self = this;

            $('.rv-rating-textarea').each(function () {
                const $textarea = $(this);
                const $counter = $textarea.siblings('.rv-char-counter');
                const $current = $counter.find('.rv-char-current');
                const $form = $textarea.closest('form');
                const $submitBtn = $form.find('button[type="submit"]');

                const minLength = parseInt($textarea.data('min-length')) || 5;
                const maxLength = parseInt($textarea.data('max-length')) || 1000;

                // Update counter function
                const updateCounter = () => {
                    const length = $textarea.val().length;
                    $current.text(length);

                    // Reset classes
                    $counter.removeClass('valid error warning');

                    if (length === 0) {
                        // Empty - neutral state
                        $submitBtn.prop('disabled', false);
                    } else if (length < minLength) {
                        // Too short
                        $counter.addClass('error');
                        $submitBtn.prop('disabled', true);
                    } else if (length > maxLength) {
                        // Too long
                        $counter.addClass('error');
                        $submitBtn.prop('disabled', true);
                    } else {
                        // Valid range
                        $counter.addClass('valid');
                        $submitBtn.prop('disabled', false);
                    }
                };

                // Bind input event
                $textarea.on('input', updateCounter);

                // Initial update (for edit mode)
                updateCounter();
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

            // Double-click protection
            const $form = $(e.target).closest('.rv-rating-form-content');
            const $submitBtn = $form.find('button[type="submit"]');

            if ($submitBtn.prop('disabled')) {
                return;
            }

            // Login check is done on PHP side, we don't check in JavaScript

            const $container = $form.closest('.rv-rating-form');
            const vehicleId = $container.attr('data-vehicle-id');
            const rating = $form.find('input[name="rating"]:checked').val();
            const comment = $form.find('textarea[name="comment"]').val();


            // Name and email fields for guest users
            const guestName = $form.find('input[name="guest_name"]').val();
            const guestEmail = $form.find('input[name="guest_email"]').val();



            if (!rating) {
                MHMRentivaToast.show('Please select a rating.', { type: 'error' });
                return;
            }


            const formData = {
                action: 'mhm_rentiva_submit_rating',
                vehicle_id: vehicleId,
                rating: rating,
                comment: comment,
                nonce: $form.find('input[name="nonce"]').val()
            };


            // Add name and email fields for guest users
            if (guestName && guestEmail) {
                formData.guest_name = guestName;
                formData.guest_email = guestEmail;
            }


            $.ajax({
                url: window.mhmVehicleRating?.ajaxUrl || (window.location.pathname.split('/')[1] ? '/' + window.location.pathname.split('/')[1] + '/wp-admin/admin-ajax.php' : '/wp-admin/admin-ajax.php'),
                type: 'POST',
                data: formData,
                timeout: 10000,
                beforeSend: () => {
                    $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');
                },
                success: (response) => {
                    if (response.success) {
                        MHMRentivaToast.show(response.data.message || 'Your comment has been submitted successfully! Awaiting approval.', { type: 'success' });
                        // Refresh page
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000); // Wait 3 seconds
                    } else {
                        MHMRentivaToast.show(response.data?.message || 'An error occurred', { type: 'error' });
                    }
                },
                error: (xhr, status, error) => {
                    MHMRentivaToast.show('An error occurred while submitting the rating.', { type: 'error' });
                },
                complete: () => {
                    $form.find('button[type="submit"]').prop('disabled', false).text('Submit Rating');
                },
                timeout: () => {
                    MHMRentivaToast.show('Request timed out. Please try again.', { type: 'error' });
                }
            });
        }

        handleStarChange(e) {
            const $input = $(e.target);
            const $container = $input.closest('.rv-rating-stars-input');
            this.applySelectedStars($container, $input.val());
        }

        handleDeleteRating(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete your rating?')) {
                return;
            }

            const $button = $(e.target);
            const vehicleId = $button.attr('data-vehicle-id');

            $.ajax({
                url: window.mhmVehicleRating?.ajaxUrl || (window.location.pathname.split('/')[1] ? '/' + window.location.pathname.split('/')[1] + '/wp-admin/admin-ajax.php' : '/wp-admin/admin-ajax.php'),
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_delete_rating',
                    vehicle_id: vehicleId,
                    nonce: $('input[name="nonce"]').val() || window.mhmVehicleRating?.nonce
                },
                success: (response) => {
                    if (response.success) {
                        MHMRentivaToast.show(response.data?.message || 'Your rating has been deleted successfully!', { type: 'success' });
                        // Refresh page
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        MHMRentivaToast.show(response.data?.message || 'Error deleting rating.', { type: 'error' });
                    }
                },
                error: () => {
                    MHMRentivaToast.show('Connection error. Please try again.', { type: 'error' });
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

            // Check if AJAX call has been made
            const $container = $(`.rv-rating-form[data-vehicle-id="${vehicleId}"]`);
            if ($container.data('ajax-called')) {
                return; // AJAX call already made
            }

            const $ratingsList = $container.find('.rv-ratings-list');

            if ($ratingsList.length === 0) {
                return;
            }

            // If reviews already exist (loaded from PHP), don't make AJAX call
            if ($ratingsList.find('.rv-reviews-section').length > 0 || $ratingsList.find('.rv-review-item').length > 0) {
                return;
            }

            // If "No reviews yet" message exists, don't make AJAX call
            if ($ratingsList.find('.rv-no-reviews').length > 0) {
                return;
            }

            const ajaxUrl = window.mhmVehicleRating?.ajaxUrl || (window.location.pathname.split('/')[1] ? '/' + window.location.pathname.split('/')[1] + '/wp-admin/admin-ajax.php' : '/wp-admin/admin-ajax.php');

            if (!ajaxUrl || ajaxUrl === 'undefined') {
                return;
            }
            // Mark AJAX call as made
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
                        // Keep existing reviews if AJAX fails
                    }
                },
                error: (xhr, status, error) => {
                    // Keep existing reviews if AJAX errors
                },
                timeout: () => {
                    // Keep existing reviews if AJAX times out
                }
            });
        }

        escapeHtml(text) {
            if (!text) return '';
            return String(text)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        renderRatingsList(ratings) {
            const $container = $('.rv-ratings-list');

            // If reviews already exist (loaded from PHP), don't render
            if ($container.find('.rv-reviews-section').length > 0 || $container.find('.rv-review-item').length > 0) {
                return;
            }

            // Remove loading state
            $container.find('.rv-loading-ratings').remove();

            if (!ratings || ratings.length === 0) {
                $container.html('<p class="rv-no-ratings">' + (window.mhmVehicleRating?.no_ratings || 'No reviews yet.') + '</p>');
                return;
            }

            let html = '<div class="rv-ratings-header"><h4>' + (window.mhmVehicleRating?.reviews_title || 'Reviews') + '</h4></div>';

            ratings.forEach((rating, index) => {
                let stars = '';
                for (let i = 1; i <= 5; i++) {
                    const isFilled = i <= rating.rating;
                    const starIcon = window.mhmVehicleRating?.icons?.star || (isFilled ? '★' : '☆');
                    stars += `<span class="rv-star ${isFilled ? 'filled' : 'empty'}">${starIcon}</span>`;
                }
                // Date handling: Ensure it doesn't break if date format varies
                let dateStr = '';
                try {
                    dateStr = new Date(rating.date || rating.created_at).toLocaleDateString();
                } catch (e) {
                    dateStr = rating.date || '';
                }

                html += `
                    <div class="rv-rating-item">
                        <div class="rv-rating-user">
                            <span class="rv-rating-name">${this.escapeHtml(rating.display_name || rating.customer_name || 'Anonymous')}</span>
                            <div class="rv-rating-stars">${stars}</div>
                            <span class="rv-rating-date">${this.escapeHtml(dateStr)}</span>
                        </div>
                        ${rating.comment ? `<div class="rv-rating-comment">${this.escapeHtml(rating.comment)}</div>` : ''}
                    </div>
                `;
            });

            $container.html(html);
        }


        handleEditComment(e) {
            e.preventDefault();

            // Settings check
            if (!(window.mhmVehicleRating?.settings?.allow_editing ?? true)) {
                MHMRentivaToast.show('❌ Comment editing is disabled.', { type: 'error' });
                return;
            }

            const $btn = $(e.target).closest('.rv-edit-comment-btn');
            const commentId = $btn.data('comment-id');
            const rating = $btn.data('rating');
            const comment = $btn.data('comment');

            // Fill the form
            const $form = $('.rv-rating-form-content');
            const $starsContainer = $form.find('.rv-rating-stars-input');
            const $ratingInputs = $starsContainer.find('input[type="radio"]');
            const $commentTextarea = $form.find('textarea[name="comment"]');

            // Set rating
            $ratingInputs.prop('checked', false);
            $ratingInputs.filter(`[value="${rating}"]`).prop('checked', true);

            // Update star appearance
            this.applySelectedStars($starsContainer, rating);

            // Set comment
            $commentTextarea.val(comment);

            // Set form to update mode
            $form.data('edit-mode', true);
            $form.data('comment-id', commentId);

            // Update submit button text
            $form.find('button[type="submit"]').text('Update Rating');

            // Scroll to form
            $('html, body').animate({
                scrollTop: $form.offset().top - 100
            }, 500);

            MHMRentivaToast.show(window.mhmVehicleRating?.strings?.edit_loaded || 'Your comment has been loaded for editing.', { type: 'info' });
        }

        handleDeleteComment(e) {
            e.preventDefault();

            // Settings check
            if (!(window.mhmVehicleRating?.settings?.allow_deletion ?? true)) {
                MHMRentivaToast.show(window.mhmRentivaVars?.i18n?.error || 'Comment deletion is disabled.', { type: 'error' });
                return;
            }

            const $btn = $(e.target).closest('.rv-delete-comment-btn');
            const commentId = $btn.data('comment-id');
            const $reviewItem = $btn.closest('.rv-review-item'); // Get the review item to remove it

            if (!confirm(window.mhmVehicleRating?.strings?.delete_confirm || 'Are you sure you want to delete this comment?')) {
                return;
            }

            const ajaxUrl = window.mhmVehicleRating?.ajaxUrl || (window.location.pathname.split('/')[1] ? '/' + window.location.pathname.split('/')[1] + '/wp-admin/admin-ajax.php' : '/wp-admin/admin-ajax.php');

            // Get nonce from form
            const nonce = $('.rv-rating-form-content input[name="nonce"]').val() || window.mhmVehicleRating?.nonce;

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mhm_rentiva_delete_rating',
                    comment_id: commentId,
                    nonce: nonce
                },
                timeout: 10000,
                beforeSend: () => {
                    $btn.prop('disabled', true).text(window.mhmVehicleRating?.strings?.deleting || 'Deleting...');
                },
                success: (response) => {
                    if (response.success) {
                        MHMRentivaToast.show(window.mhmVehicleRating?.strings?.delete_success || 'Your comment has been deleted successfully!', { type: 'success' });

                        // Remove review from DOM
                        $reviewItem.slideUp(function () {
                            $(this).remove();
                        });

                        // Refresh page
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        MHMRentivaToast.show((window.mhmVehicleRating?.strings?.delete_error || 'Error deleting comment: ') + (response.data?.message || (window.mhmVehicleRating?.strings?.unknown_error || 'Unknown error')), { type: 'error' });
                    }
                },
                error: (xhr, status, error) => {
                    MHMRentivaToast.show(window.mhmVehicleRating?.strings?.delete_error_retry || 'Error deleting comment. Please try again.', { type: 'error' });
                },
                complete: () => {
                    $btn.prop('disabled', false).html((window.mhmVehicleRating?.icons?.trash || '') + ' ' + (window.mhmVehicleRating?.strings?.delete || 'Delete'));
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function () {
        new VehicleRatingForm();
    });

})(jQuery);
