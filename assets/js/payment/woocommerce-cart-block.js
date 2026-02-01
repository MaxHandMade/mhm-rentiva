(function () {
    'use strict';

    /**
     * MHM Rentiva - Cart Block Vehicle Image Injector
     * 
     * Replaces WooCommerce placeholder images with actual vehicle images
     * in the Cart Block and Mini-Cart.
     */

    const PLACEHOLDER_PATTERNS = [
        'woocommerce-placeholder',
        'placeholder.png',
        'placeholder.webp'
    ];

    const RETRY_INTERVAL = 500;
    const MAX_RETRIES = 20;

    /**
     * Check if an image is a placeholder
     */
    function isPlaceholder(imgSrc) {
        if (!imgSrc) return true;
        return PLACEHOLDER_PATTERNS.some(pattern => imgSrc.includes(pattern));
    }

    /**
     * Get vehicle images from PHP
     */
    function getVehicleImages() {
        if (typeof mhmRentivaCartImages !== 'undefined' && mhmRentivaCartImages) {
            return mhmRentivaCartImages;
        }
        return {};
    }

    /**
     * Process main cart items
     */
    function processMainCart() {
        const cartRows = document.querySelectorAll('tr.wc-block-cart-items__row');
        if (cartRows.length === 0) return false;

        const vehicleImages = getVehicleImages();
        const imageKeys = Object.keys(vehicleImages);

        if (imageKeys.length === 0) return true;

        let processed = 0;

        cartRows.forEach(function (row, index) {
            if (row.dataset.mhmProcessed === 'true') return;

            const imgContainer = row.querySelector('.wc-block-cart-item__image');
            if (!imgContainer) return;

            const img = imgContainer.querySelector('img');
            if (!img || !isPlaceholder(img.src)) {
                row.dataset.mhmProcessed = 'true';
                return;
            }

            const imageKey = imageKeys[index];
            const vehicleImage = imageKey ? vehicleImages[imageKey] : null;

            if (vehicleImage && vehicleImage.url) {
                img.src = vehicleImage.url;
                if (vehicleImage.srcset) img.srcset = vehicleImage.srcset;
                img.alt = vehicleImage.alt || 'Vehicle';
                img.style.cssText = 'width: 80px; height: 80px; object-fit: cover; border-radius: 8px;';
                img.classList.add('mhm-vehicle-image-injected');
                processed++;
            }

            row.dataset.mhmProcessed = 'true';
        });

        return processed > 0;
    }

    /**
     * Process mini-cart items
     */
    function processMiniCart() {
        // Mini cart uses different selectors
        const miniCartItems = document.querySelectorAll('.wc-block-mini-cart-items .wc-block-cart-items__row');
        if (miniCartItems.length === 0) return false;

        const vehicleImages = getVehicleImages();
        const imageKeys = Object.keys(vehicleImages);

        if (imageKeys.length === 0) return true;

        let processed = 0;

        miniCartItems.forEach(function (item, index) {
            if (item.dataset.mhmMiniProcessed === 'true') return;

            // Mini cart image selectors
            const imgContainer = item.querySelector('.wc-block-cart-item__image, .wc-block-components-product-image');
            if (!imgContainer) return;

            const img = imgContainer.querySelector('img');
            const svgPlaceholder = imgContainer.querySelector('.mhm-vehicle-placeholder');

            // If SVG placeholder exists, replace it
            if (svgPlaceholder) {
                const imageKey = imageKeys[index];
                const vehicleImage = imageKey ? vehicleImages[imageKey] : null;

                if (vehicleImage && vehicleImage.url) {
                    const newImg = document.createElement('img');
                    newImg.src = vehicleImage.url;
                    if (vehicleImage.srcset) newImg.srcset = vehicleImage.srcset;
                    newImg.alt = vehicleImage.alt || 'Vehicle';
                    newImg.className = 'mhm-vehicle-thumbnail';
                    newImg.style.cssText = 'width: 48px; height: 48px; object-fit: cover; border-radius: 6px;';

                    svgPlaceholder.parentNode.replaceChild(newImg, svgPlaceholder);
                    processed++;
                }
            }
            // If img exists and is placeholder
            else if (img && isPlaceholder(img.src)) {
                const imageKey = imageKeys[index];
                const vehicleImage = imageKey ? vehicleImages[imageKey] : null;

                if (vehicleImage && vehicleImage.url) {
                    img.src = vehicleImage.url;
                    if (vehicleImage.srcset) img.srcset = vehicleImage.srcset;
                    img.alt = vehicleImage.alt || 'Vehicle';
                    img.style.cssText = 'width: 48px; height: 48px; object-fit: cover; border-radius: 6px;';
                    img.classList.add('mhm-vehicle-image-injected');
                    processed++;
                }
            }

            item.dataset.mhmMiniProcessed = 'true';
        });

        return processed > 0;
    }

    /**
     * Process all cart elements
     */
    function processAll() {
        processMainCart();
        processMiniCart();
    }

    /**
     * Initialize with retry
     */
    function init(retryCount) {
        retryCount = retryCount || 0;

        const hasCart = document.querySelector('.wc-block-cart, .wp-block-woocommerce-cart, .wc-block-mini-cart');
        if (!hasCart) return;

        processAll();

        if (retryCount < MAX_RETRIES) {
            setTimeout(function () {
                init(retryCount + 1);
            }, RETRY_INTERVAL);
        }
    }

    /**
     * Observe DOM changes
     */
    function observeChanges() {
        const observer = new MutationObserver(function (mutations) {
            let shouldProcess = false;

            mutations.forEach(function (mutation) {
                if (mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1) {
                            // Check for cart or mini-cart content
                            if (node.classList && (
                                node.classList.contains('wc-block-cart-items__row') ||
                                node.classList.contains('wc-block-mini-cart-items') ||
                                (node.querySelector && (
                                    node.querySelector('.wc-block-cart-items__row') ||
                                    node.querySelector('.wc-block-mini-cart-items')
                                ))
                            )) {
                                shouldProcess = true;
                            }
                        }
                    });
                }
            });

            if (shouldProcess) {
                // Small delay to let React finish rendering
                setTimeout(processAll, 100);
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(init, 500);
            observeChanges();
        });
    } else {
        setTimeout(init, 500);
        observeChanges();
    }

    // Also listen for WooCommerce cart updates
    document.addEventListener('wc-blocks_added_to_cart', function () {
        setTimeout(processAll, 500);
    });

    // Listen for mini-cart open event
    document.addEventListener('click', function (e) {
        if (e.target.closest('.wc-block-mini-cart__button')) {
            // Mini cart is being opened, process after a delay
            setTimeout(function () {
                processMiniCart();
            }, 500);
            setTimeout(function () {
                processMiniCart();
            }, 1000);
        }
    });

})();
