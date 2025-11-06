/**
 * Contact Form JavaScript
 * 
 * İletişim formu için JavaScript işlevleri
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
         * File upload handling
         */
        initFileUpload() {
            const fileInput = document.getElementById('rv-contact-attachment');
            const fileInfo = document.querySelector('.rv-file-info');
            const fileName = document.querySelector('.rv-file-name');
            const fileRemove = document.querySelector('.rv-file-remove');

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
        }

        /**
         * Form submission handling
         */
        initFormSubmission() {
            const forms = document.querySelectorAll('.rv-contact-form');

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
            const submitBtn = form.querySelector('.rv-form-submit');
            const originalText = submitBtn.textContent;

            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.textContent = mhmContactForm.messages.submitting || mhmContactForm.strings?.submitting || 'Submitting...';

            // Collect form data
            const formData = new FormData(form);
            formData.append('action', 'mhm_rentiva_contact_form_submit');
            formData.append('nonce', mhmContactForm.nonce);

            // Submit via AJAX
            fetch(mhmContactForm.ajaxUrl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.showSuccessMessage(form, data.data.message || (mhmContactForm.strings?.success || 'Your message has been sent successfully.'));
                        form.reset();
                    } else {
                        this.showErrorMessage(form, data.data.message || (mhmContactForm.strings?.error || 'An error occurred while sending your message.'));
                    }
                })
                .catch(error => {
                    console.error('Form submission error:', error);
                    this.showErrorMessage(form, mhmContactForm.strings?.error || 'An error occurred while sending your message.');
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
            successDiv.innerHTML = `<span class="dashicons dashicons-yes"></span> ${message}`;

            form.insertBefore(successDiv, form.firstChild);

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
            errorDiv.innerHTML = `<span class="dashicons dashicons-warning"></span> ${message}`;

            form.insertBefore(errorDiv, form.firstChild);
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