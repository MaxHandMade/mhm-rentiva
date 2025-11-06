/**
 * MHM Rentiva Settings Form Handler
 * 
 * Prevents null values from being submitted to WordPress Settings API
 * This fixes PHP strlen() deprecation warnings
 * 
 * @since 4.3.5
 */

jQuery(document).ready(function ($) {
    'use strict';

    /**
     * Clean all form inputs before submission
     * Convert null/undefined values to empty strings
     */
    function cleanFormInputs(form) {
        // Get all input, select, textarea elements (but NOT checkboxes)
        var $inputs = $(form).find('input:not([type="checkbox"]), select, textarea');

        $inputs.each(function () {
            var $input = $(this);
            var value = $input.val();

            // Convert null, undefined, or empty to empty string
            if (value === null || value === undefined || value === '') {
                $input.val('');
            }
        });

        if (window.mhm_rentiva_config?.debug) {
            console.log('MHM Rentiva: Form inputs cleaned before submission');
        }
        return true;
    }

    /**
     * Attach form submit handler to settings form
     */
    $('#mhm-settings-main-form').on('submit', function (e) {
        if (window.mhm_rentiva_config?.debug) {
            console.log('MHM Rentiva: Form submit detected');
        }
        cleanFormInputs(this);
    });

    /**
     * Also attach to any WordPress settings forms
     */
    $('form[action="options.php"]').on('submit', function (e) {
        if (window.mhm_rentiva_config?.debug) {
            console.log('MHM Rentiva: WordPress options form submit detected');
        }
        cleanFormInputs(this);
    });

    if (window.mhm_rentiva_config?.debug) {
        console.log('MHM Rentiva: Settings form handler initialized');
    }
});

