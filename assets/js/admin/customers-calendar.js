/**
 * MHM Rentiva - Müşteriler Takvim JavaScript
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

    let currentDate = new Date();

    // URL'den ay ve yıl parametrelerini al
    const urlParams = new URLSearchParams(window.location.search);
    const monthParam = urlParams.get('month');
    const yearParam = urlParams.get('year');

    if (monthParam && yearParam) {
        currentDate = new Date(yearParam, monthParam - 1, 1);
    }

    function renderCalendar(date) {
        const year = date.getFullYear();
        const month = date.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);

        const prevLastDay = new Date(year, month, 0).getDate();
        const firstDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;
        const lastDayIndex = lastDay.getDay() === 0 ? 6 : lastDay.getDay() - 1;
        const nextDays = 6 - lastDayIndex;

        // Format month/year using JavaScript's locale
        const currentDate = new Date(year, month, 1);
        const locale = (window.mhmCustomersCalendar && window.mhmCustomersCalendar.locale) || 'en-US';
        const monthName = currentDate.toLocaleDateString(locale, { month: 'long' });

        monthYear.textContent = `${monthName} ${year}`;
        calendarDays.innerHTML = "";

        // Önceki ayın son günleri
        for (let x = firstDayIndex; x > 0; x--) {
            const div = document.createElement("div");
            div.classList.add("prev-date");
            div.textContent = prevLastDay - x + 1;
            calendarDays.appendChild(div);
        }

        // Bu ayın günleri
        for (let i = 1; i <= lastDay.getDate(); i++) {
            const div = document.createElement("div");
            div.textContent = i;
            const today = new Date();
            if (
                i === today.getDate() &&
                month === today.getMonth() &&
                year === today.getFullYear()
            ) {
                div.classList.add("today");
            }

            // Müşteri kayıt tarihlerini kontrol et
            const customerRegistrationData = window.mhmCustomersCalendar?.customerRegistrations || {};
            if (customerRegistrationData[i]) {
                div.classList.add("customer-registered");

                // Aynı günde birden fazla müşteri varsa
                if (Array.isArray(customerRegistrationData[i])) {
                    const customerNames = customerRegistrationData[i].map(c => c.name).join(', ');
                    const customerEmails = customerRegistrationData[i].map(c => c.email).join(', ');
                    div.title = `${customerNames} - ${customerEmails}`;

                    // Müşteri sayısını göster
                    const countIcon = document.createElement("span");
                    countIcon.classList.add("customer-count");
                    countIcon.textContent = customerRegistrationData[i].length;
                    div.appendChild(countIcon);
                } else {
                    // Tek müşteri
                    const customerInfo = customerRegistrationData[i];
                    div.title = `${customerInfo.name} - ${customerInfo.email}`;
                }

                // Müşteri ikonu ekle
                const icon = document.createElement("span");
                icon.classList.add("customer-icon");
                icon.textContent = "👤";
                div.appendChild(icon);
            }

            div.addEventListener("click", () => {
                const dateText = window.mhmCustomersCalendar?.strings?.selectedDate || 'Selected date';
                showNotice(`${dateText}: ${i}.${month + 1}.${year}`, 'info');
            });
            calendarDays.appendChild(div);
        }

        // Sonraki ayın ilk günleri
        for (let j = 1; j <= nextDays; j++) {
            const div = document.createElement("div");
            div.classList.add("next-date");
            div.textContent = j;
            calendarDays.appendChild(div);
        }
    }

    prevMonthBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        const newMonth = currentDate.getMonth() + 1;
        const newYear = currentDate.getFullYear();

        // URL'yi güncelle
        const url = new URL(window.location);
        url.searchParams.set('month', newMonth);
        url.searchParams.set('year', newYear);
        window.location.href = url.toString();
    });

    nextMonthBtn.addEventListener("click", () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        const newMonth = currentDate.getMonth() + 1;
        const newYear = currentDate.getFullYear();

        // URL'yi güncelle
        const url = new URL(window.location);
        url.searchParams.set('month', newMonth);
        url.searchParams.set('year', newYear);
        window.location.href = url.toString();
    });

    /**
     * Show notice message
     */
    function showNotice(message, type) {
        type = type || 'info';
        const noticeClass = 'notice-' + type;
        const notice = document.createElement('div');
        notice.className = 'notice ' + noticeClass + ' is-dismissible';
        notice.style.cssText = 'position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        notice.innerHTML = '<p><strong>' + message + '</strong></p>';

        // Remove any existing notices first
        const existingNotices = document.querySelectorAll('.notice');
        existingNotices.forEach(notice => notice.remove());

        // Add to body for better visibility
        document.body.appendChild(notice);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notice.style.opacity = '0';
            notice.style.transition = 'opacity 0.5s';
            setTimeout(function () {
                notice.remove();
            }, 500);
        }, 5000);
    }

    // İlk yükleme
    renderCalendar(currentDate);
});
