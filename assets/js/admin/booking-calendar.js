/**
 * MHM Rentiva - Rezervasyon Takvim JavaScript
 * ChatGPT tabanlı takvim işlevselliği
 */

document.addEventListener('DOMContentLoaded', function () {
    const monthYear = document.getElementById("monthYear");
    const calendarDays = document.getElementById("calendarDays");
    const prevMonthBtn = document.getElementById("prevMonth");
    const nextMonthBtn = document.getElementById("nextMonth");

    if (!monthYear || !calendarDays || !prevMonthBtn || !nextMonthBtn) {
        return; // Elementler bulunamadıysa çık
    }

    // JavaScript takvim minimal mod: mevcut (PHP) hücreleri korur, sadece ay geçişini yönetir
    // Param names must match BookingColumns::PUBLIC_QUERY_VARS -- the PHP side
    // reads them through get_query_var(), which only answers for names registered
    // on WordPress's query_vars whitelist. Bare month/year cannot be registered
    // there (an unprefixed `month` would collide globally and `year` is already a
    // core query var).
    function getCurrentDateFromParams() {
        const params = new URLSearchParams(window.location.search);
        const m = parseInt(params.get('mhm_month'), 10);
        const y = parseInt(params.get('mhm_year'), 10);
        if (!isNaN(m) && !isNaN(y)) {
            return new Date(y, m - 1, 1);
        }
        return new Date();
    }
    let currentDate = getCurrentDateFromParams();

    prevMonthBtn.addEventListener("click", () => {
        if (prevMonthBtn.dataset.loading === '1') return;
        prevMonthBtn.dataset.loading = '1';
        prevMonthBtn.setAttribute('aria-disabled', 'true');
        currentDate.setMonth(currentDate.getMonth() - 1);
        navigateTo(currentDate.getMonth() + 1, currentDate.getFullYear());
    });

    nextMonthBtn.addEventListener("click", () => {
        if (nextMonthBtn.dataset.loading === '1') return;
        nextMonthBtn.dataset.loading = '1';
        nextMonthBtn.setAttribute('aria-disabled', 'true');
        currentDate.setMonth(currentDate.getMonth() + 1);
        navigateTo(currentDate.getMonth() + 1, currentDate.getFullYear());
    });

    // Klavye kısayolları: sol/sağ ok ile ay geçişi
    document.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowLeft') {
            prevMonthBtn.click();
        } else if (e.key === 'ArrowRight') {
            nextMonthBtn.click();
        }
    });

    function navigateTo(month, year) {
        const url = new URL(window.location);
        // Var olan tüm filtre parametrelerini koru
        url.searchParams.set('mhm_month', month);
        url.searchParams.set('mhm_year', year);
        window.location.href = url.toString();
    }
    // Not re-rendering the grid; we keep server-rendered colored cells intact
});
