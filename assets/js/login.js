document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('loginBtn');
    const passwordToggle = document.getElementById('passwordToggle');
    const alertContainer = document.getElementById('alertContainer');

    // Auto focus to username
    usernameInput.focus();

    // Password toggle functionality
    passwordToggle.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });

    // Form validation and submission
    loginForm.addEventListener('submit', function(e) {
        // Clear previous alerts
        alertContainer.innerHTML = '';

        // Validate inputs
        const username = usernameInput.value.trim();
        const password = passwordInput.value.trim();

        if (!username || !password) {
            e.preventDefault();
            showAlert('danger', 'Username dan password harus diisi!');
            return;
        }

        // Show loading state
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Masuk...';
    });

    // Show alert function
    function showAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} py-2 small`;
        alert.innerHTML = message;
        alertContainer.appendChild(alert);

        // Auto hide after 5 seconds
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    // Check for URL parameters (error messages)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('pesan') === 'wajib_login') {
        showAlert('warning', 'Anda harus login terlebih dahulu!');
    }
});