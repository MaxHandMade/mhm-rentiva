/**
 * Universal success message auto-hide system
 */
document.addEventListener("DOMContentLoaded", function () {
    // Find all notices with mhm-auto-hide-notice class
    const notices = document.querySelectorAll(".mhm-auto-hide-notice");
    notices.forEach(function (notice) {
        // Auto-hide after 3 seconds
        setTimeout(function () {
            notice.style.transition = "opacity 0.5s ease";
            notice.style.opacity = "0";
            setTimeout(function () {
                notice.remove();
            }, 500);
        }, 3000);
    });
});
