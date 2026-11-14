document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pr-filter-form');
    const period = document.getElementById('pr-period');
    function syncDates() {
        const custom = period.value === 'custom';
        form.querySelectorAll('.pr-custom').forEach(field => {
            field.hidden = !custom;
            field.querySelector('input').disabled = !custom;
            field.querySelector('input').required = custom;
        });
    }
    period.addEventListener('change', syncDates);
    syncDates();
    form.addEventListener('submit', function () {
        document.getElementById('payment-reports').setAttribute('aria-busy', 'true');
        document.getElementById('pr-loading').hidden = false;
        document.getElementById('pr-apply').disabled = true;
    });
    window.addEventListener('pageshow', function () {
        document.getElementById('payment-reports').removeAttribute('aria-busy');
        document.getElementById('pr-loading').hidden = true;
        document.getElementById('pr-apply').disabled = false;
    });

    const canvas = document.getElementById('pr-collection-chart');
    if (!canvas) return;
    if (typeof Chart === 'undefined') {
        canvas.parentElement.hidden = true;
        document.getElementById('pr-chart-fallback').hidden = false;
        document.querySelector('.pr-chart-data').open = true;
        return;
    }
    const data = JSON.parse(document.getElementById('pr-chart-values').textContent);
    const theme = typeof getDtcChartTheme === 'function' ? getDtcChartTheme() : {};
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const axisPeso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
    function tickLimit(width) {
        return Math.max(2, Math.min(12, Math.floor((width - 72) / 72)));
    }
    const chart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.points.map(point => point.label),
            datasets: [
                { label: 'Gross collected', data: data.points.map(point => point.gross / 100), backgroundColor: '#0F4CDB', borderRadius: 3 },
                { label: 'Confirmed refunds', data: data.points.map(point => point.refunds / 100), backgroundColor: '#FFC72C', borderRadius: 3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            onResize: (chart, size) => { chart.options.scales.x.ticks.maxTicksLimit = tickLimit(size.width); },
            animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 200 },
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, ticks: { color: theme.text, maxTicksLimit: 6, callback: value => axisPeso.format(value) }, grid: { color: theme.grid } },
                x: {
                    ticks: {
                        color: theme.text, autoSkip: true, maxTicksLimit: tickLimit(canvas.parentElement.clientWidth), maxRotation: 0,
                        callback: function (value) {
                            const label = this.getLabelForValue(value);
                            return data.interval === 'Daily' ? label.replace(/, \d{4}$/, '') : label.replace(/ (\d{2})(\d{2})$/, ' $2');
                        }
                    },
                    grid: { display: false }
                }
            },
            plugins: { legend: { position: 'bottom', labels: { color: theme.text, boxWidth: 12, boxHeight: 12, padding: 12 } }, tooltip: { backgroundColor: theme.tooltipBg, titleColor: theme.tooltipText, bodyColor: theme.tooltipText, callbacks: { label: context => context.dataset.label + ': ' + peso.format(context.parsed.y) } } }
        }
    });
    if (typeof registerDtcChart === 'function') registerDtcChart(chart);
    document.addEventListener('dtc:themechange', function () {
        if (typeof getDtcChartTheme === 'function') chart.options.plugins.legend.labels.color = getDtcChartTheme().text;
        chart.update();
    });
});
