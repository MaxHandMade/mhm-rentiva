/**
 * MHM Rentiva - Toast API Contract v1.0
 * Provides a single, consistent, cross-browser toast system.
 * 
 * @package MHMRentiva
 * @since 4.9.8
 */

(function (window) {
    'use strict';

    if (window.MHMRentivaToast) {
        return;
    }

    const TOAST_CONTAINER_ID = 'mhm-toast-container';
    const DEFAULT_DURATION = 3000;
    const ERROR_DURATION = 4000;
    const DEDUPE_WINDOW = 1000;

    class ToastManager {
        constructor() {
            this.container = null;
            this.toasts = new Map(); // id -> toast object
            this.dedupeMap = new Map(); // identity -> { id, lastSeen }

            this.defaults = {
                type: 'success',
                duration: DEFAULT_DURATION,
                dismissible: true,
                ariaLive: 'polite',
                position: 'top-right'
            };
        }

        /**
         * Initialize the container if it doesn't exist.
         */
        _initContainer() {
            if (this.container) return;

            this.container = document.getElementById(TOAST_CONTAINER_ID);
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = TOAST_CONTAINER_ID;
                this.container.className = `mhm-toast-container ${this.defaults.position}`;
                document.body.appendChild(this.container);
            }
        }

        /**
         * Create a unique ID.
         */
        _generateId() {
            return 'mhm-toast-' + Math.random().toString(36).substr(2, 9);
        }

        /**
         * Escapes HTML to prevent XSS.
         */
        _escape(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Show or update a toast.
         * 
         * @param {string} message 
         * @param {object} options 
         * @returns {string} toastId
         */
        show(message, options = {}) {
            this._initContainer();

            const mergedOptions = Object.assign({}, this.defaults, options);
            if (mergedOptions.type === 'error' && !options.duration) {
                mergedOptions.duration = ERROR_DURATION;
            }

            // Deduplication logic
            const idempotencyKey = mergedOptions.idempotencyKey || `${mergedOptions.type}|${message}`;
            const now = Date.now();
            const existingDedupe = this.dedupeMap.get(idempotencyKey);

            if (existingDedupe && (now - existingDedupe.lastSeen < DEDUPE_WINDOW)) {
                const existingToast = this.toasts.get(existingDedupe.id);
                if (existingToast) {
                    this._refreshToast(existingToast, message, mergedOptions);
                    return existingDedupe.id;
                }
            }

            const id = this._generateId();
            const toastObj = {
                id,
                idempotencyKey,
                options: mergedOptions,
                element: this._createToastElement(id, message, mergedOptions),
                timer: null
            };

            this.toasts.set(id, toastObj);
            this.dedupeMap.set(idempotencyKey, { id, lastSeen: now });

            // Add to DOM
            this.container.prepend(toastObj.element);

            // Trigger show animation in next frame
            requestAnimationFrame(() => {
                toastObj.element.classList.add('is-active');
            });

            if (mergedOptions.duration > 0) {
                this._startTimer(toastObj, mergedOptions.duration);
            }

            return id;
        }

        /**
         * Create the DOM element for the toast.
         */
        _createToastElement(id, message, options) {
            const el = document.createElement('div');
            el.className = `mhm-toast is-${options.type}`;
            el.id = id;
            el.setAttribute('role', 'alert');
            el.setAttribute('aria-live', options.ariaLive);

            // Respect reduced motion
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                el.classList.add('no-motion');
            }

            // Message
            const msgEl = document.createElement('span');
            msgEl.className = 'mhm-toast__message';
            msgEl.textContent = message;
            el.appendChild(msgEl);

            // Action
            this._renderAction(el, id, options.action);

            // Close Button
            if (options.dismissible) {
                const closeBtn = document.createElement('button');
                closeBtn.className = 'mhm-toast__close';
                closeBtn.setAttribute('aria-label', 'Close notification');
                closeBtn.innerHTML = '&times;'; // Or an icon if preferred, but &times; is safe
                closeBtn.addEventListener('click', () => this.dismiss(id));
                el.appendChild(closeBtn);
            }

            return el;
        }

        /**
         * Render or update action element.
         */
        _renderAction(container, id, action) {
            // Remove existing action if any
            const existingAction = container.querySelector('.mhm-toast__action');
            if (existingAction) existingAction.remove();

            if (!action || !action.label) return;

            if (action.href && action.onClick) {
                console.warn('MHMRentivaToast: Both href and onClick provided. Prioritizing onClick.');
            }

            const actionEl = document.createElement(action.href ? 'a' : 'button');
            actionEl.className = 'mhm-toast__action';
            actionEl.textContent = action.label;

            if (action.href) {
                actionEl.href = action.href;
                if (action.target) actionEl.target = action.target;
                if (action.target === '_blank') actionEl.rel = 'noopener noreferrer';
            }

            actionEl.addEventListener('click', (e) => {
                if (action.onClick) {
                    action.onClick(e);
                }
                this.dismiss(id);
            });

            // Insert before close button if it exists
            const closeBtn = container.querySelector('.mhm-toast__close');
            if (closeBtn) {
                container.insertBefore(actionEl, closeBtn);
            } else {
                container.appendChild(actionEl);
            }
        }

        /**
         * Refresh an existing toast's timer.
         */
        _refreshToast(toastObj, message, options) {
            this._stopTimer(toastObj);

            // Update content
            const msgEl = toastObj.element.querySelector('.mhm-toast__message');
            if (msgEl) msgEl.textContent = message;

            // Update action
            this._renderAction(toastObj.element, toastObj.id, options.action);

            // Update type class
            toastObj.element.className = `mhm-toast is-${options.type} is-active`;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                toastObj.element.classList.add('no-motion');
            }

            if (options.duration > 0) {
                this._startTimer(toastObj, options.duration);
            }

            // Subtle pulse to indicate update
            toastObj.element.classList.remove('pulse');
            void toastObj.element.offsetWidth; // Force reflow
            toastObj.element.classList.add('pulse');

            this.dedupeMap.get(toastObj.idempotencyKey).lastSeen = Date.now();
        }

        _startTimer(toastObj, duration) {
            toastObj.timer = setTimeout(() => {
                this.dismiss(toastObj.id);
            }, duration);
        }

        _stopTimer(toastObj) {
            if (toastObj.timer) {
                clearTimeout(toastObj.timer);
                toastObj.timer = null;
            }
        }

        /**
         * Dismiss a toast with animation.
         */
        dismiss(id) {
            const toastObj = this.toasts.get(id);
            if (!toastObj) return;

            this._stopTimer(toastObj);

            toastObj.element.classList.remove('is-active');
            toastObj.element.classList.add('is-dismissing');

            // Remove from dedupe map if this is the active toast for that key
            if (this.dedupeMap.get(toastObj.idempotencyKey)?.id === id) {
                this.dedupeMap.delete(toastObj.idempotencyKey);
            }

            // Wait for animation to complete (matching CSS transition 220ms)
            setTimeout(() => {
                if (toastObj.element.parentNode) {
                    toastObj.element.parentNode.removeChild(toastObj.element);
                }
                this.toasts.delete(id);
            }, 250);
        }

        /**
         * Clear all toasts.
         */
        clearAll() {
            this.toasts.forEach((toast, id) => {
                this.dismiss(id);
            });
            this.dedupeMap.clear();
        }

        /**
         * Set global defaults.
         */
        setDefaults(newDefaults) {
            this.defaults = Object.assign(this.defaults, newDefaults);
        }
    }

    window.MHMRentivaToast = new ToastManager();

})(window);
