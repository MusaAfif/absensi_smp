document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function ensureOverlay() {
        let overlay = document.getElementById('globalLoadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'globalLoadingOverlay';
            overlay.className = 'global-loading-overlay d-none';
            overlay.innerHTML = '<div class="loading-card"><div class="spinner-border text-primary" role="status"></div><span>Memproses...</span></div>';
            body.appendChild(overlay);
        }
        return overlay;
    }

    function showOverlay(message) {
        const overlay = ensureOverlay();
        const text = overlay.querySelector('span');
        if (text && message) {
            text.textContent = message;
        }
        overlay.classList.remove('d-none');
    }

    function hideOverlay() {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (overlay) {
            overlay.classList.add('d-none');
        }
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'app-toast app-toast-' + (type || 'success');
        toast.textContent = message;
        body.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 250);
        }, 2800);
    }

    window.AppUI = {
        showLoading: showOverlay,
        hideLoading: hideOverlay,
        toast: showToast
    };

    // Progressive enhancement for forms
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                if (!submitBtn.dataset.originalHtml) {
                    submitBtn.dataset.originalHtml = submitBtn.innerHTML || submitBtn.value || '';
                }

                if (submitBtn.tagName === 'BUTTON') {
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Menyimpan...';
                } else {
                    submitBtn.value = 'Menyimpan...';
                }
                submitBtn.disabled = true;
            }

            showOverlay(form.dataset.loadingMessage || 'Sedang memproses data...');
        });
    });

    // Auto-hide bootstrap alerts for cleaner UX
    document.querySelectorAll('.alert').forEach(function (alertEl) {
        setTimeout(function () {
            alertEl.classList.add('fade');
            setTimeout(function () { alertEl.remove(); }, 250);
        }, 4500);
    });

    // Optional auto refresh only for pages that explicitly request it
    const autoRefreshMinutes = parseInt(body.dataset.autoRefreshMinutes || '0', 10);
    if (autoRefreshMinutes > 0) {
        setInterval(function () {
            window.location.reload();
        }, autoRefreshMinutes * 60 * 1000);
    }
});
