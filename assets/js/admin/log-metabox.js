(function ($) {
    'use strict';

    $(function () {
        var btn = $('#mhm-copy-context');
        var ta = $('#mhm-context');

        if (btn.length && ta.length) {
            btn.on('click', function () {
                ta.trigger('focus').trigger('select');
                try {
                    document.execCommand('copy');
                    var originalText = btn.text();
                    btn.text(mhmLogMetabox.copied);
                    setTimeout(function () {
                        btn.text(originalText);
                    }, 1500);
                } catch (e) {
                    // Copy command failed.
                }
            });
        }
    });

})(jQuery);
