/**
 * Privacy Controls JavaScript
 * 
 * Handles GDPR privacy controls functionality
 */

jQuery(document).ready(function ($) {

    // Data Export
    $('#export-data').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to export your data? This may take a few minutes.')) {
            return;
        }

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('Exporting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_data_export',
                nonce: mhm_privacy.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Create download link
                    var blob = new Blob([response.data], { type: 'application/json' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'my-data-export.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);

                    alert('Data exported successfully!');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('An error occurred while exporting data.');
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Withdraw Consent
    $('#withdraw-consent').on('click', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to withdraw your consent? This will disable data processing for your account.')) {
            return;
        }

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('Processing...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_consent_withdrawal',
                nonce: mhm_privacy.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('Consent withdrawn successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('An error occurred while withdrawing consent.');
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Delete Account
    $('#delete-account').on('click', function (e) {
        e.preventDefault();

        var confirmation = prompt('This action cannot be undone. Type "DELETE" to confirm account deletion:');

        if (confirmation !== 'DELETE') {
            return;
        }

        if (!confirm('Are you absolutely sure? This will permanently delete your account and all data.')) {
            return;
        }

        var button = $(this);
        var originalText = button.text();

        button.prop('disabled', true).text('Deleting...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mhm_rentiva_data_deletion',
                nonce: mhm_privacy.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert('Account deleted successfully. You will be redirected to the homepage.');
                    window.location.href = mhm_privacy.home_url;
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                alert('An error occurred while deleting account.');
            },
            complete: function () {
                button.prop('disabled', false).text(originalText);
            }
        });
    });

});
