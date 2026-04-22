document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('resetForm');
    const usernameInput = document.getElementById('username');
    const recoveryCodeInput = document.getElementById('recoveryCode');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const resetBtn = document.getElementById('resetBtn');

    // Auto focus to username
    usernameInput.focus();

    // Form validation and submission
    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Clear previous alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        // Validate inputs
        const username = usernameInput.value.trim();
        const recoveryCode = recoveryCodeInput.value.trim();
        const newPassword = newPasswordInput.value.trim();
        const confirmPassword = confirmPasswordInput.value.trim();

        if (!username || !recoveryCode || !newPassword || !confirmPassword) {
            showAlert('danger', 'Semua kolom wajib diisi.');
            return;
        }

        if (newPassword !== confirmPassword) {
            showAlert('danger', 'Password baru dan konfirmasi tidak cocok.');
            return;
        }

        if (newPassword.length < 6) {
            showAlert('warning', 'Password minimal 6 karakter.');
            return;
        }

        // Show loading state
        resetBtn.disabled = true;
        resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mereset...';

        // Submit form after short delay for UX
        setTimeout(() => {
            resetForm.submit();
        }, 500);
    });

    // Show alert function
    function showAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} py-2 small`;
        alert.innerHTML = message;

        // Insert alert before the form
        const form = document.getElementById('resetForm');
        form.parentNode.insertBefore(alert, form);

        // Auto hide after 5 seconds for non-success alerts
        if (type !== 'success') {
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    }
});