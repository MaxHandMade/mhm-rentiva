/**
 * Testimonials JavaScript
 * 
 * Müşteri yorumları için interaktif özellikler
 */

class Testimonials {
    constructor() {
        // jQuery'nin yüklenmesini bekle
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery is not loaded, testimonials will not work');
            return;
        }

        this.container = jQuery('.rv-testimonials');
        this.currentPage = 1;
        this.isLoading = false;
        this.carouselInterval = null;

        if (this.container.length === 0) return;

        this.init();
    }

    init() {
        this.bindEvents();
        this.initCarousel();
        this.initAutoRotate();
    }

    bindEvents() {
        // Load More Button
        jQuery(document).on('click', '.rv-load-more-btn', (e) => {
            e.preventDefault();
            this.loadMoreTestimonials();
        });

        // Carousel Controls
        jQuery(document).on('click', '.rv-carousel-prev', () => {
            this.carouselPrev();
        });

        jQuery(document).on('click', '.rv-carousel-next', () => {
            this.carouselNext();
        });

        // Carousel Indicators
        jQuery(document).on('click', '.rv-carousel-indicator', (e) => {
            const slideIndex = parseInt(jQuery(e.target).data('slide'));
            this.goToSlide(slideIndex);
        });

        // Pause auto-rotate on hover
        this.container.on('mouseenter', '.rv-testimonials-carousel', () => {
            this.pauseAutoRotate();
        });

        this.container.on('mouseleave', '.rv-testimonials-carousel', () => {
            this.resumeAutoRotate();
        });

        // Keyboard navigation
        jQuery(document).on('keydown', (e) => {
            if (this.container.find('.rv-testimonials-carousel').length > 0) {
                if (e.key === 'ArrowLeft') {
                    this.carouselPrev();
                } else if (e.key === 'ArrowRight') {
                    this.carouselNext();
                }
            }
        });
    }

    initCarousel() {
        const $carousel = this.container.find('.rv-testimonials-carousel');
        if ($carousel.length === 0) return;

        this.carouselTrack = $carousel.find('.rv-carousel-track');
        this.carouselSlides = $carousel.find('.rv-carousel-slide');
        this.currentSlide = 0;
        this.totalSlides = this.carouselSlides.length;

        if (this.totalSlides <= 1) {
            $carousel.find('.rv-carousel-prev, .rv-carousel-next, .rv-carousel-indicators').hide();
            return;
        }

        this.updateCarouselPosition();
    }

    initAutoRotate() {
        const autoRotate = this.container.data('auto-rotate') === '1';
        if (autoRotate && this.totalSlides > 1) {
            this.startAutoRotate();
        }
    }

    carouselPrev() {
        if (this.totalSlides <= 1) return;

        this.currentSlide = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.updateCarouselPosition();
        this.updateIndicators();
    }

    carouselNext() {
        if (this.totalSlides <= 1) return;

        this.currentSlide = (this.currentSlide + 1) % this.totalSlides;
        this.updateCarouselPosition();
        this.updateIndicators();
    }

    goToSlide(slideIndex) {
        if (this.totalSlides <= 1) return;

        this.currentSlide = slideIndex;
        this.updateCarouselPosition();
        this.updateIndicators();
    }

    updateCarouselPosition() {
        if (!this.carouselTrack) return;

        const translateX = -this.currentSlide * 100;
        this.carouselTrack.css('transform', `translateX(${translateX}%)`);
    }

    updateIndicators() {
        this.container.find('.rv-carousel-indicator').removeClass('active');
        this.container.find(`.rv-carousel-indicator[data-slide="${this.currentSlide}"]`).addClass('active');
    }

    startAutoRotate() {
        this.carouselInterval = setInterval(() => {
            this.carouselNext();
        }, 5000); // 5 saniyede bir
    }

    pauseAutoRotate() {
        if (this.carouselInterval) {
            clearInterval(this.carouselInterval);
            this.carouselInterval = null;
        }
    }

    resumeAutoRotate() {
        const autoRotate = this.container.data('auto-rotate') === '1';
        if (autoRotate && this.totalSlides > 1) {
            this.startAutoRotate();
        }
    }

    loadMoreTestimonials() {
        if (this.isLoading) return;

        this.isLoading = true;
        const $loadMoreBtn = this.container.find('.rv-load-more-btn');
        const $spinner = $loadMoreBtn.find('.rv-loading-spinner');

        // Show loading state
        $loadMoreBtn.prop('disabled', true);
        $spinner.show();

        // Get current parameters
        const limit = this.container.data('limit') || 5;
        const rating = this.container.data('rating') || '';
        const vehicleId = this.container.data('vehicle-id') || '';

        // AJAX request
        jQuery.ajax({
            url: window.mhmRentivaTestimonials?.ajaxUrl || ajaxurl || window.location.origin + '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mhm_rentiva_load_testimonials',
                nonce: window.mhmRentivaTestimonials?.nonce || '',
                page: this.currentPage + 1,
                limit: limit,
                rating: rating,
                vehicle_id: vehicleId
            },
            success: (response) => {
                if (response.success) {
                    this.appendTestimonials(response.data.testimonials);
                    this.currentPage++;

                    // Hide load more button if no more testimonials
                    if (!response.data.has_more) {
                        $loadMoreBtn.closest('.rv-testimonials-load-more').hide();
                    }
                } else {
                    this.showError(response.data?.message || (window.mhmRentivaTestimonials?.strings?.error || 'Testimonials could not be loaded'));
                }
            },
            error: () => {
                this.showError(window.mhmRentivaTestimonials?.strings?.error || 'An error occurred while loading testimonials');
            },
            complete: () => {
                this.isLoading = false;
                $loadMoreBtn.prop('disabled', false);
                $spinner.hide();
            }
        });
    }

    appendTestimonials(testimonials) {
        if (!testimonials || testimonials.length === 0) return;

        const layout = this.container.data('layout') || 'grid';
        const $container = this.container.find(`.rv-testimonials-${layout}`);

        testimonials.forEach(testimonial => {
            const $testimonialItem = this.createTestimonialElement(testimonial);
            $container.append($testimonialItem);
        });

        // Re-initialize carousel if needed
        if (layout === 'carousel') {
            this.initCarousel();
        }

        // Animate new items
        this.animateNewItems();
    }

    createTestimonialElement(testimonial) {
        const $item = jQuery('<div class="rv-testimonial-item"></div>');

        let content = '<div class="rv-testimonial-content">';

        // Rating
        if (testimonial.rating > 0) {
            content += '<div class="rv-testimonial-rating">';
            for (let i = 1; i <= 5; i++) {
                const filled = i <= testimonial.rating ? 'filled' : 'empty';
                content += `<span class="rv-star ${filled}"><span class="dashicons dashicons-star-filled"></span></span>`;
            }
            content += `<span class="rv-rating-text">(${testimonial.rating}/5)</span></div>`;
        }

        // Review text
        content += `<div class="rv-testimonial-text"><blockquote>"${testimonial.review}"</blockquote></div>`;

        // Meta info
        content += '<div class="rv-testimonial-meta">';

        if (testimonial.customer_name) {
            content += `<div class="rv-customer-name"><strong>${testimonial.customer_name}</strong></div>`;
        }

        if (testimonial.vehicle_name) {
            content += `<div class="rv-vehicle-name"><span class="dashicons dashicons-car"></span>${testimonial.vehicle_name}</div>`;
        }

        if (testimonial.date) {
            const formattedDate = new Date(testimonial.date).toLocaleDateString(window.mhmRentivaTestimonials?.locale || 'en-US');
            content += `<div class="rv-review-date"><span class="dashicons dashicons-calendar-alt"></span>${formattedDate}</div>`;
        }

        content += '</div></div>';

        $item.html(content);
        return $item;
    }

    animateNewItems() {
        this.container.find('.rv-testimonial-item').each((index, element) => {
            if (!jQuery(element).hasClass('rv-animated')) {
                jQuery(element).addClass('rv-animated');
                jQuery(element).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).animate({
                    opacity: 1
                }, 300).css('transform', 'translateY(0)');
            }
        });
    }

    showError(message) {
        const $errorDiv = jQuery('<div class="rv-testimonials-error" style="background: #e74c3c; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;"></div>');
        $errorDiv.text(message);

        this.container.find('.rv-testimonials-load-more').before($errorDiv);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            $errorDiv.fadeOut(() => $errorDiv.remove());
        }, 5000);
    }

    // Public methods for external use
    refresh() {
        this.currentPage = 1;
        this.container.find('.rv-testimonials-load-more').show();
        this.container.find('.rv-testimonials-grid, .rv-testimonials-list').empty();
        this.loadMoreTestimonials();
    }

    filterByRating(rating) {
        this.container.data('rating', rating);
        this.refresh();
    }

    filterByVehicle(vehicleId) {
        this.container.data('vehicle-id', vehicleId);
        this.refresh();
    }
}

// Initialize when document is ready
jQuery(document).ready(function ($) {
    new Testimonials();
});

// Make Testimonials class globally available
window.Testimonials = Testimonials;
