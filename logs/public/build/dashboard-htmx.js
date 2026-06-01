(function () {
    function initDashboardHtmxUi() {
        var toast = document.getElementById('dashboard-notification');
        var filtersToggle = document.getElementById('dashboard-filters-toggle');
        var filtersPanel = document.getElementById('dashboard-filters-panel');
        var filtersPanelStateInput = document.querySelector('#dashboard-filters-form input[name="filters_panel"]');
        var modal = document.getElementById('dashboard-log-modal');
        var modalBody = document.getElementById('dashboard-modal-body');
        var prevButton = document.getElementById('dashboard-log-prev');
        var nextButton = document.getElementById('dashboard-log-next');
        var modalCounter = document.getElementById('dashboard-log-counter');

        var rowExternalIds = [];
        var currentRowIndex = -1;

        function collectRows() {
            rowExternalIds = [];

            var rows = document.querySelectorAll('.dashboard-log-row[data-external-id]');
            rows.forEach(function (row) {
                var externalId = row.getAttribute('data-external-id');

                if (externalId) {
                    rowExternalIds.push(externalId);
                }
            });
        }

        function updateNavButtons() {
            if (!prevButton || !nextButton) {
                return;
            }

            prevButton.disabled = currentRowIndex <= 0;
            nextButton.disabled = currentRowIndex < 0 || currentRowIndex >= rowExternalIds.length - 1;

            if (modalCounter) {
                if (currentRowIndex >= 0 && rowExternalIds.length > 0) {
                    modalCounter.textContent = (currentRowIndex + 1) + '/' + rowExternalIds.length;
                } else {
                    modalCounter.textContent = '';
                }
            }
        }

        function closeModal() {
            if (!modal) {
                return;
            }

            modal.classList.add('d-none');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        }

        function openModal() {
            if (!modal) {
                return;
            }

            modal.classList.remove('d-none');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function loadDetailByExternalId(externalId) {
            if (!modalBody || !externalId) {
                return;
            }

            modalBody.innerHTML = '<div class="text-muted small">Chargement du detail...</div>';

            fetch('/dashboard/htmx/logs/' + encodeURIComponent(externalId) + '/detail', {
                headers: {
                    'HX-Request': 'true',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    return response.text();
                })
                .then(function (html) {
                    modalBody.innerHTML = html;
                })
                .catch(function () {
                    modalBody.innerHTML = '<div class="alert alert-danger mb-0">Impossible de charger le detail du log.</div>';
                });
        }

        function openRowByIndex(index) {
            if (index < 0 || index >= rowExternalIds.length) {
                return;
            }

            currentRowIndex = index;
            updateNavButtons();
            openModal();
            loadDetailByExternalId(rowExternalIds[index]);
        }

        function bindRows() {
            collectRows();

            var rows = document.querySelectorAll('.dashboard-log-row[data-external-id]');

            rows.forEach(function (row, index) {
                row.addEventListener('click', function () {
                    openRowByIndex(index);
                });

                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openRowByIndex(index);
                    }
                });
            });
        }

        function readStateFromUrl() {
            var currentUrl = new URL(window.location.href);
            var value = currentUrl.searchParams.get('filters_panel');

            return value === 'open' ? 'open' : 'closed';
        }

        function updateFiltersUrl(state) {
            var nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('filters_panel', state);

            window.history.pushState({}, '', nextUrl.toString());
        }

        function setFiltersState(state, updateUrl) {
            if (!filtersToggle || !filtersPanel) {
                return;
            }

            var isOpen = state === 'open';

            filtersPanel.classList.toggle('d-none', !isOpen);
            filtersToggle.classList.toggle('is-open', isOpen);
            filtersToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

            if (filtersPanelStateInput) {
                filtersPanelStateInput.value = isOpen ? 'open' : 'closed';
            }

            if (updateUrl) {
                updateFiltersUrl(isOpen ? 'open' : 'closed');
            }
        }

        if (filtersToggle && filtersPanel) {
            var initialState = readStateFromUrl();

            if (!window.location.search.includes('filters_panel=')) {
                initialState = filtersToggle.getAttribute('data-initial-state') === 'open' ? 'open' : 'closed';
            }

            setFiltersState(initialState, false);

            filtersToggle.addEventListener('click', function () {
                var currentlyOpen = filtersToggle.classList.contains('is-open');
                setFiltersState(currentlyOpen ? 'closed' : 'open', true);
            });

            window.addEventListener('popstate', function () {
                setFiltersState(readStateFromUrl(), false);
            });
        }

        if (modal && modalBody && prevButton && nextButton) {
            bindRows();

            prevButton.addEventListener('click', function () {
                openRowByIndex(currentRowIndex - 1);
            });

            nextButton.addEventListener('click', function () {
                openRowByIndex(currentRowIndex + 1);
            });

            modal.addEventListener('click', function (event) {
                var target = event.target;

                if (!(target instanceof Element)) {
                    return;
                }

                if (target.closest('[data-modal-close="true"]')) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (!modal.classList.contains('is-open')) {
                    return;
                }

                if (event.key === 'Escape') {
                    closeModal();
                    return;
                }

                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    openRowByIndex(currentRowIndex - 1);
                    return;
                }

                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    openRowByIndex(currentRowIndex + 1);
                }
            });
        }

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

                if (modal && modalBody && prevButton && nextButton) {
                    closeModal();
                    currentRowIndex = -1;
                    bindRows();
                    updateNavButtons();
                }
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
