function mhmDashboardInit() {
    // KPI counter animation (overview tab only).
    const counters = document.querySelectorAll(
        "body.rentiva-panel-page .mhm-rentiva-dashboard__kpi-value"
    );

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

    // Vendor analytics + payout AJAX (overview/ledger tabs).
    if (typeof mhmRentivaAnalytics !== 'undefined') {
        initAnalyticsDashboard();
        initPayoutDashboardAjax();
    }

    // Vendor listings panel toggle (listings tab).
    if (document.getElementById('mhm-toggle-add-vehicle')) {
        initVendorListingsPanel();
    }

    // Initialize selectWoo on city selects.
    if (typeof jQuery !== 'undefined' && jQuery.fn.selectWoo) {
        jQuery('.mhm-city-select').selectWoo({ width: '100%' });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", mhmDashboardInit);
} else {
    mhmDashboardInit();
}

function initVendorListingsPanel() {
    var btn   = document.getElementById('mhm-toggle-add-vehicle');
    var panel = document.getElementById('mhm-add-vehicle-panel');
    var close = document.getElementById('mhm-close-add-vehicle');

    if (!btn || !panel) return;

    btn.addEventListener('click', function () {
        var open = panel.style.display !== 'none';
        panel.style.display = open ? 'none' : 'block';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        if (!open) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    if (close) {
        close.addEventListener('click', function () {
            panel.style.display = 'none';
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    // Auto-open if URL has ?add_vehicle=1
    if (window.location.search.indexOf('add_vehicle=1') !== -1) {
        panel.style.display = 'block';
        btn.setAttribute('aria-expanded', 'true');
    }

    // After successful vehicle submit, hide the form and reload the listing
    document.addEventListener('mhm_vehicle_submitted', function () {
        panel.style.display = 'none';
        btn.setAttribute('aria-expanded', 'false');
        setTimeout(function () { window.location.reload(); }, 1500);
    });
}

function initPayoutDashboardAjax() {
    const form = document.getElementById('mhm-rentiva-ajax-payout-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = document.getElementById('mhm-rentiva-payout-btn');
        const spinner = btn.querySelector('.mhm-rentiva-spinner');
        const btnText = btn.querySelector('.mhm-rentiva-btn-text');
        const notices = document.getElementById('mhm-rentiva-payout-notices');

        // Reset notices
        notices.innerHTML = '';

        // Disable UI
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btnText.style.display = 'none';
        spinner.style.display = 'inline-block';

        const amount = document.getElementById('payout_amount').value;
        const nonce = document.getElementById('mhm_payout_request_nonce').value;

        const data = new URLSearchParams();
        data.append('action', 'mhm_request_payout');
        data.append('nonce', mhmRentivaAnalytics.nonce); // General dashboard nonce
        data.append('payout_amount', amount);
        // Note: we can also send the form nonce, but the endpoint checks 'mhm_rentiva_vendor_nonce' 
        // which is passed globally via mhmRentivaAnalytics.nonce.

        fetch(mhmRentivaAnalytics.ajaxUrl, {
            method: 'POST',
            body: data
        })
            .then(response => response.json())
            .then(res => {
                const noticeDiv = document.createElement('div');
                noticeDiv.className = 'mhm-rentiva-dashboard__notice ' + (res.success ? 'is-success' : 'is-error');
                noticeDiv.innerText = res.data.message || (res.success ? 'Success' : mhmRentivaAnalytics.i18n.error);
                notices.appendChild(noticeDiv);

                if (res.success) {
                    // Hide form as pending state is active
                    form.style.display = 'none';
                    // Update the request status text manually to pending
                    const pendingLabel = document.querySelector('.mhm-rentiva-dashboard__payout-stat--pending .mhm-rentiva-dashboard__payout-stat-value');
                    if (pendingLabel) {
                        var _pendingText = (typeof mhmRentivaAnalytics !== 'undefined'
                            && mhmRentivaAnalytics.i18n
                            && mhmRentivaAnalytics.i18n.pending)
                            ? mhmRentivaAnalytics.i18n.pending
                            : 'Pending';
                        pendingLabel.innerText = _pendingText;
                    }
                }
            })
            .catch(err => {
                console.error(err);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'mhm-rentiva-dashboard__notice is-error';
                errorDiv.innerText = mhmRentivaAnalytics.i18n.error;
                notices.appendChild(errorDiv);
            })
            .finally(() => {
                btn.disabled = false;
                btn.style.opacity = '1';
                spinner.style.display = 'none';
                if (btnText) btnText.style.display = 'inline';
            });
    });
}

function initAnalyticsDashboard() {
    const fpInput = document.getElementById('mhm-vendor-date-range');
    if (!fpInput) return;

    let debounceTimer;

    const fp = flatpickr(fpInput, {
        mode: "range",
        dateFormat: "Y-m-d",
        onChange: function (selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetchVendorStats(selectedDates[0], selectedDates[1]);
                }, 400); // Debounce limit
            }
        }
    });

    // Preset Buttons
    document.querySelectorAll('.mhm-rentiva-preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const preset = this.dataset.preset;
            const today = new Date();
            let start, end;

            if (preset === '7d') {
                end = today;
                start = new Date();
                start.setDate(today.getDate() - 7);
            } else if (preset === '30d') {
                end = today;
                start = new Date();
                start.setDate(today.getDate() - 30);
            } else if (preset === 'this_month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = new Date(today.getFullYear(), today.getMonth() + 1, 0); // last day
            } else if (preset === 'last_month') {
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
            }

            if (start && end) {
                fp.setDate([start, end], true);
            }
        });
    });

    function fetchVendorStats(start, end) {
        const container = document.getElementById('mhm-vendor-analytics-container');
        if (!container) return;

        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';

        const data = new URLSearchParams();
        data.append('action', 'mhm_fetch_vendor_stats');
        data.append('nonce', mhmRentivaAnalytics.nonce);

        const formatData = (d) => {
            let month = '' + (d.getMonth() + 1), day = '' + d.getDate(), year = d.getFullYear();
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            return [year, month, day].join('-');
        };

        data.append('start_date', formatData(start));
        data.append('end_date', formatData(end));

        fetch(mhmRentivaAnalytics.ajaxUrl, {
            method: 'POST',
            body: data
        })
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    updateDashboardDOM(res.data, start, end);
                } else {
                    console.error(res.data.message || mhmRentivaAnalytics.i18n.error);
                }
            })
            .catch(err => console.error(err))
            .finally(() => {
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
            });
    }

    function updateDashboardDOM(data, start, end) {
        const formatLabel = (d) => d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
        const rangeLabel = formatLabel(start) + ' – ' + formatLabel(end);

        // Update standard Overview KPIs
        const kpiRev = document.getElementById('kpi-revenue_7d-value');
        if (kpiRev) {
            kpiRev.innerHTML = data.revenue_formatted;
            const context = document.getElementById('kpi-revenue_7d-context');
            if (context) {
                context.innerHTML = `<span class="mhm-rentiva-dashboard__kpi-meta">Selected period trend</span>` + data.growth_html;
            }
        }

        const kpiOcc = document.getElementById('kpi-occupancy_rate-value');
        if (kpiOcc) kpiOcc.innerHTML = Number(data.occupancy_rate).toFixed(1) + '%';

        const kpiCancel = document.getElementById('kpi-cancellation_rate-value');
        if (kpiCancel) kpiCancel.innerHTML = Number(data.cancellation_rate).toFixed(1) + '%';

        // Update specialized vendor-analytics elements
        const revEl = document.getElementById('kpi-revenue-value');
        if (revEl) revEl.innerHTML = data.revenue_formatted;
        const revMeta = document.getElementById('kpi-revenue-meta');
        if (revMeta) revMeta.innerText = 'Selected period (' + Math.round((end - start) / 86400000 + 1) + ' days)';

        const growthEl = document.getElementById('kpi-growth-container');
        if (growthEl) growthEl.innerHTML = data.growth_html;
        const growthMeta = document.getElementById('kpi-growth-meta');
        if (growthMeta) growthMeta.innerText = 'vs prior period';

        const avgEl = document.getElementById('kpi-avg-booking-value');
        if (avgEl) avgEl.innerHTML = data.avg_booking_formatted;
        const avgMeta = document.getElementById('kpi-avg-booking-meta');
        if (avgMeta) avgMeta.innerText = 'Selected period average';

        const vehiclesContainer = document.getElementById('mhm-dashboard-top-vehicles');
        if (vehiclesContainer) {
            const tbody = vehiclesContainer.querySelector('tbody');
            if (tbody) tbody.innerHTML = data.top_vehicles_html;
        }

        const mainChartTitle = document.getElementById('main-trend-title');
        if (mainChartTitle) mainChartTitle.innerText = 'Custom Period Revenue Trend (' + Math.round((end - start) / 86400000 + 1) + ' days)';
        const mainChartRange = document.getElementById('main-trend-range');
        if (mainChartRange) mainChartRange.innerText = rangeLabel;
        const mainChartContainer = document.getElementById('main-trend-container');
        if (mainChartContainer) mainChartContainer.innerHTML = data.sparkline_html;

        const secondaryChart = document.getElementById('secondary-trend-section');
        if (secondaryChart) secondaryChart.style.display = 'none';
    }
}
