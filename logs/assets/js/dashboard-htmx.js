(function () {
    function initDashboardHtmxUi() {
        var toast = document.getElementById('dashboard-notification');

        if (!toast) {
            return;
        }

        var hideTimeoutId = null;

        var showToast = function (message) {
            toast.textContent = message;
            toast.classList.remove('d-none');

            if (hideTimeoutId !== null) {
                window.clearTimeout(hideTimeoutId);
            }

            hideTimeoutId = window.setTimeout(function () {
                toast.classList.add('d-none');
            }, 1800);
        };

        document.body.addEventListener('htmx:afterSwap', function (event) {
            if (!event.target || !(event.target instanceof Element)) {
                return;
            }

            if (event.target.id === 'logs-list-region') {
                showToast('Liste mise a jour.');
            }

            if (event.target.id === 'log-detail-region') {
                showToast('Detail charge.');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardHtmxUi, { once: true });
    } else {
        initDashboardHtmxUi();
    }
})();
