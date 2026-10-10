/**
 * Testimonials JavaScript
 * 
 * Müşteri yorumları için interaktif özellikler
 */

class Testimonials {
    constructor() {
        // Wait for jQuery to load
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery is not loaded, testimonials will not work');
            return;
        }

        this.container = jQuery('.rv-testimonials');
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
            // The block the button belongs to: a page can carry several, each
            // with its own order, filters and flags.
            this.loadMoreTestimonials(jQuery(e.currentTarget).closest('.rv-testimonials'));
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
        // jQuery .data() parses attribute values: '1' becomes the number 1, not string '1'.
        // Use == (loose) or parseInt to handle both types.
        const autoRotate = parseInt(this.container.data('auto-rotate'), 10) === 1;
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
        const autoRotate = parseInt(this.container.data('auto-rotate'), 10) === 1;
        if (autoRotate && this.totalSlides > 1) {
            this.startAutoRotate();
        }
    }

    /**
     * A setting the template prints only when it differs from its default
     * (so a default render keeps today's markup). Read as a string: jQuery's
     * .data() would turn '0' into a number.
     */
    setting($root, name, fallback) {
        const value = $root.attr('data-' + name);
        return (value === undefined || value === '') ? fallback : String(value);
    }

    loadMoreTestimonials($root) {
        $root = ($root && $root.length) ? $root : this.container.first();
        if ($root.data('rvLoading')) return;

        $root.data('rvLoading', true);
        const $loadMoreBtn = $root.find('.rv-load-more-btn');
        const $spinner = $loadMoreBtn.find('.rv-loading-spinner');
        const stored = parseInt($root.data('rvPage'), 10);
        const page = Number.isNaN(stored) ? 1 : stored;

        // Show loading state
        $loadMoreBtn.prop('disabled', true);
        $spinner.show();

        // The next page in the same order, with the same filters, as the first.
        jQuery.ajax({
            url: window.mhmRentivaTestimonials?.ajaxUrl || window.ajaxurl || '',
            type: 'POST',
            data: {
                action: 'mhmrentiva_load_testimonials',
                nonce: window.mhmRentivaTestimonials?.nonce || '',
                page: page + 1,
                limit: this.setting($root, 'limit', '5'),
                rating: this.setting($root, 'rating', ''),
                vehicle_id: this.setting($root, 'vehicle-id', ''),
                orderby: this.setting($root, 'orderby', 'date'),
                order: this.setting($root, 'order', 'DESC'),
                seed: this.setting($root, 'seed', '')
            },
            success: (response) => {
                if (response.success) {
                    this.appendTestimonials($root, response.data.testimonials);
                    $root.data('rvPage', page + 1);

                    // Hide load more button if no more testimonials
                    if (!response.data.has_more) {
                        $loadMoreBtn.closest('.rv-testimonials-load-more').hide();
                    }
                } else {
                    this.showError($root, response.data?.message || (window.mhmRentivaTestimonials?.strings?.error || 'Testimonials could not be loaded'));
                }
            },
            error: () => {
                this.showError($root, window.mhmRentivaTestimonials?.strings?.error || 'An error occurred while loading testimonials');
            },
            complete: () => {
                $root.data('rvLoading', false);
                $loadMoreBtn.prop('disabled', false);
                $spinner.hide();
            }
        });
    }

    appendTestimonials($root, testimonials) {
        if (!testimonials || testimonials.length === 0) return;

        const layout = $root.attr('data-layout') || 'grid';
        const $container = $root.find(`.rv-testimonials-${layout}`);

        testimonials.forEach(testimonial => {
            $container.append(this.createTestimonialElement($root, testimonial));
        });

        // Re-initialize carousel if needed
        if (layout === 'carousel') {
            this.initCarousel();
        }

        // Animate new items
        this.animateNewItems($root);
    }

    /**
     * One card: the markup of templates/shortcodes/testimonials.php, under the
     * same display flags.
     */
    createTestimonialElement($root, testimonial) {
        const show = (name) => this.setting($root, name, '1') === '1';
        const icons = window.mhmRentivaTestimonials?.icons || {};
        const rating = parseInt(testimonial.rating, 10) || 0;
        const name = String(testimonial.customer_name || '');

        let content = '<div class="rv-testimonial-card">';

        if (show('show-quotes')) {
            content += `<div class="rv-quote-icon" aria-hidden="true">${icons.card_quote || ''}</div>`;
        }

        if (show('show-rating') && rating > 0) {
            content += '<div class="rv-testimonial-rating">';
            for (let i = 1; i <= 5; i++) {
                content += `<span class="rv-star ${i <= rating ? 'filled' : 'empty'}">${icons.card_star || ''}</span>`;
            }
            content += '</div>';
        }

        // Review text - escape to prevent XSS
        content += `<div class="rv-testimonial-text"><p>&ldquo;${this.escapeHtml(testimonial.review)}&rdquo;</p></div>`;

        content += '<div class="rv-testimonial-author">';
        if (show('show-customer') && name !== '') {
            if (show('show-author-avatar')) {
                content += `<div class="rv-author-avatar"><span class="rv-avatar-placeholder" aria-hidden="true">${this.escapeHtml(this.initialLetter(name))}</span></div>`;
            }
            content += '<div class="rv-author-info">';
            content += `<span class="rv-author-name">${this.escapeHtml(this.formatName(name))}</span>`;
            if (show('show-vehicle') && testimonial.vehicle_name) {
                content += `<span class="rv-author-title">${this.escapeHtml(testimonial.vehicle_name)}</span>`;
            }
            if (show('show-date') && testimonial.date_display) {
                content += `<span class="rv-author-date">${this.escapeHtml(testimonial.date_display)}</span>`;
            }
            content += '</div>';
        }
        content += '</div></div>';

        const $item = jQuery('<div class="rv-testimonial-item"></div>');
        $item.html(content);
        return $item;
    }

    /** "Mehmet Demir" -> "Mehmet D.", as the template's $format_name. */
    formatName(fullName) {
        const parts = String(fullName).trim().split(/\s+/).filter(Boolean);
        if (parts.length < 2) {
            return String(fullName);
        }
        return parts[0] + ' ' + Array.from(parts[parts.length - 1])[0].toLocaleUpperCase() + '.';
    }

    /** As mhmrentiva_initial_avatar_letter(). */
    initialLetter(name) {
        const first = Array.from(String(name).trim())[0] || '';
        return first.toLocaleUpperCase();
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

    animateNewItems($root) {
        ($root || this.container).find('.rv-testimonial-item').each((index, element) => {
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

    showError($root, message) {
        const $errorDiv = jQuery('<div class="rv-testimonials-error" style="background: #e74c3c; color: white; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: center;"></div>');
        $errorDiv.text(message);

        $root.find('.rv-testimonials-load-more').before($errorDiv);

        // Auto-hide after 5 seconds
        setTimeout(() => {
            $errorDiv.fadeOut(() => $errorDiv.remove());
        }, 5000);
    }

    // Public methods for external use
    refresh() {
        const $root = this.container.first();
        // Page 0, so the next request asks for page 1.
        $root.data('rvPage', 0);
        $root.find('.rv-testimonials-load-more').show();
        $root.find('.rv-testimonials-grid, .rv-testimonials-list').empty();
        this.loadMoreTestimonials($root);
    }

    filterByRating(rating) {
        this.container.first().attr('data-rating', rating);
        this.refresh();
    }

    filterByVehicle(vehicleId) {
        this.container.first().attr('data-vehicle-id', vehicleId);
        this.refresh();
    }
}

// Initialize when document is ready
jQuery(document).ready(function ($) {
    new Testimonials();
});

// Make Testimonials class globally available
window.Testimonials = Testimonials;
