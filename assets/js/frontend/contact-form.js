/**
 * Contact Form JavaScript
 * 
 * JavaScript functions for contact form
 */

(function ($) {
    'use strict';

    /**
     * Contact Form Handler
     */
    class ContactFormHandler {
        constructor() {
            this.init();
        }

        init() {
            this.initRatingStars();
            this.initFileUpload();
            this.initFormSubmission();
            this.initResetButton();
        }

        /**
         * Rating stars interaction
         */
        initRatingStars() {
            const ratingStars = document.querySelectorAll('.rv-rating-star input[type="radio"]');
            ratingStars.forEach((star, index) => {
                star.addEventListener('change', function () {
                    // Remove active class from all stars
                    ratingStars.forEach(s => s.parentElement.classList.remove('active'));

                    // Add active class to selected star and previous stars
                    for (let i = 0; i <= index; i++) {
                        ratingStars[i].parentElement.classList.add('active');
                    }
                });
            });
        }

        /**
         * Reset Button Handler
         */
        initResetButton() {
            const resetButtons = document.querySelectorAll('.rv-reset-button');
            resetButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (confirm(mhmContactForm.messages.confirm_reset)) {
                        const form = btn.closest('form');
                        this.resetForm(form);
                    }
                });
            });
        }

        /**
         * Helper to reset form and UI elements
         */
        resetForm(form) {
            // Native form reset
            form.reset();

            // Clear messages
            this.clearMessages(form);

            // Reset File Upload UI
            const fileInfo = form.querySelector('.rv-file-info');
            if (fileInfo) {
                fileInfo.style.display = 'none';
                const fileInput = form.querySelector('.rv-file-input');
                if (fileInput) fileInput.value = '';
                const fileName = form.querySelector('.rv-file-name');
                if (fileName) fileName.textContent = '';
            }

            // Reset Rating Stars UI
            const ratingStars = form.querySelectorAll('.rv-rating-star');
            ratingStars.forEach(star => star.classList.remove('active'));
        }

        /**
         * File upload handling
         */
        /**
         * File upload handling
         */
        initFileUpload() {
            const forms = document.querySelectorAll('.rv-contact-form form');

            forms.forEach(form => {
                const fileInput = form.querySelector('.rv-file-input');
                const fileInfo = form.querySelector('.rv-file-info');
                const fileName = form.querySelector('.rv-file-name');
                const fileRemove = form.querySelector('.rv-file-remove');

                if (fileInput && fileInfo && fileName && fileRemove) {
                    fileInput.addEventListener('change', function () {
                        if (this.files.length > 0) {
                            fileName.textContent = this.files[0].name;
                            fileInfo.style.display = 'flex';
                        }
                    });

                    fileRemove.addEventListener('click', function () {
                        fileInput.value = '';
                        fileInfo.style.display = 'none';
                    });
                }
            });
        }

        /**
         * Form submission handling
         */
        initFormSubmission() {
            // Select the actual <form> element inside the wrapper
            const forms = document.querySelectorAll('.rv-contact-form form');

            forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleFormSubmit(form);
                });
            });
        }

        /**
         * Handle form submission
         */
        handleFormSubmit(form) {
            const submitBtn = form.querySelector('.rv-submit-button');
            const originalText = submitBtn.textContent;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.textContent = mhmContactForm.messages.submitting;

            // Collect form data
            const formData = new FormData(form);
            formData.append('action', 'mhm_rentiva_submit_contact_form');
            formData.append('nonce', mhmContactForm.nonce);

            // Submit via AJAX
            fetch(mhmContactForm.ajaxUrl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.resetForm(form);
                        this.showSuccessMessage(form, (data.data && data.data.message) || mhmContactForm.messages.success);
                    } else {
                        const errorMessage = (data.data && data.data.message) || mhmContactForm.messages.error;
                        this.showErrorMessage(form, errorMessage);
                    }
                })
                .catch(error => {
                    console.error('Form submission error:', error);
                    this.showErrorMessage(form, mhmContactForm.messages.error);
                })
                .finally(() => {
                    // Re-enable button
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
        }

        /**
         * Show success message
         */
        showSuccessMessage(form, message) {
            this.clearMessages(form);

            const successDiv = document.createElement('div');
            successDiv.className = 'rv-form-message rv-success';
            successDiv.innerHTML = '<span class="dashicons dashicons-yes"></span> <span class="rv-msg-text"></span>';
            successDiv.querySelector('.rv-msg-text').textContent = message;

            const actionsDiv = form.querySelector('.rv-form-actions');
            if (actionsDiv) {
                actionsDiv.parentNode.insertBefore(successDiv, actionsDiv);
            } else {
                form.insertBefore(successDiv, form.firstChild);
            }

            // Auto-hide after 5 seconds
            setTimeout(() => {
                successDiv.remove();
            }, 5000);
        }

        /**
         * Show error message
         */
        showErrorMessage(form, message) {
            this.clearMessages(form);

            const errorDiv = document.createElement('div');
            errorDiv.className = 'rv-form-message rv-error';
            errorDiv.innerHTML = '<span class="dashicons dashicons-warning"></span> <span class="rv-msg-text"></span>';
            errorDiv.querySelector('.rv-msg-text').textContent = message;

            const actionsDiv = form.querySelector('.rv-form-actions');
            if (actionsDiv) {
                actionsDiv.parentNode.insertBefore(errorDiv, actionsDiv);
            } else {
                form.insertBefore(errorDiv, form.firstChild);
            }
        }

        /**
         * Clear existing messages
         */
        clearMessages(form) {
            const existingMessages = form.querySelectorAll('.rv-form-message');
            existingMessages.forEach(msg => msg.remove());
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        new ContactFormHandler();
    });

})(jQuery);