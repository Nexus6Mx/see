/**
 * Login Page JavaScript
 * Handles user authentication
 */

(function() {
    const loginForm = document.getElementById('login-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const loginBtn = document.getElementById('login-btn');
    const errorMessage = document.getElementById('error-message');

    // Check if already logged in
    const token = localStorage.getItem('authToken');
    if (token) {
        window.location.href = 'dashboard.html';
        return;
    }

    // Handle form submission
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const email = emailInput.value.trim();
        const password = passwordInput.value;

        if (!email || !password) {
            showError('Por favor completa todos los campos');
            return;
        }

        setLoading(true);
        hideError();

        try {
            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Save token and user data
                localStorage.setItem('authToken', data.token);
                localStorage.setItem('userData', JSON.stringify(data.user));

                // Redirect to dashboard
                window.location.href = 'dashboard.html';
            } else {
                showError(data.error || 'Credenciales inválidas');
                setLoading(false);
            }
        } catch (error) {
            console.error('Login error:', error);
            showError('Error de conexión. Por favor intenta de nuevo.');
            setLoading(false);
        }
    });

    function setLoading(loading) {
        const btnText = loginBtn.querySelector('.btn-text');
        const btnLoader = loginBtn.querySelector('.btn-loader');

        if (loading) {
            loginBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoader.classList.remove('hidden');
        } else {
            loginBtn.disabled = false;
            btnText.classList.remove('hidden');
            btnLoader.classList.add('hidden');
        }
    }

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
    }

    function hideError() {
        errorMessage.classList.add('hidden');
        errorMessage.textContent = '';
    }
})();
