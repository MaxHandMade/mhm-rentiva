(function () {
    'use strict';

    function initDashboardCounters() {
        var counters = document.querySelectorAll(
            'body.rentiva-panel-page .mhm-rentiva-dashboard__kpi-value'
        );

        counters.forEach(function (counter) {
            var target = parseInt(counter.dataset.count, 10);
            if (isNaN(target) || target <= 0) {
                return;
            }

            var current = 0;
            var totalFrames = 600 / 16;
            var increment = target / totalFrames;

            function animate() {
                current += increment;
                if (current >= target) {
                    counter.textContent = String(target);
                    return;
                }

                counter.textContent = String(Math.floor(current));
                window.requestAnimationFrame(animate);
            }

            window.requestAnimationFrame(animate);
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initDashboardCounters);
    } else {
        initDashboardCounters();
    }
}());
