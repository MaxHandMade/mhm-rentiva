document.addEventListener("DOMContentLoaded", function () {
    const counters = document.querySelectorAll(
        "body.rentiva-panel-page .mhm-rentiva-dashboard__kpi-value"
    );

    if (!counters.length) {
        return;
    }

    counters.forEach((counter) => {
        const target = parseInt(counter.dataset.count, 10);

        if (isNaN(target) || target <= 0) {
            return;
        }

        let current = 0;
        const duration = 600;
        const frameRate = 16;
        const totalFrames = duration / frameRate;
        const increment = target / totalFrames;

        const animate = () => {
            current += increment;

            if (current >= target) {
                counter.textContent = String(target);
            } else {
                counter.textContent = String(Math.floor(current));
                requestAnimationFrame(animate);
            }
        };

        requestAnimationFrame(animate);
    });
});
