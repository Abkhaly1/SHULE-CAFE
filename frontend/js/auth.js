document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');
    const submitBtn = document.getElementById('submitBtn');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Clear previous errors
            loginError.style.display = 'none';
            loginError.querySelector('p').textContent = '';

            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;

            if (!phone || !password) {
                showError("Please enter both phone number and password.");
                return;
            }

            // Set loading state
            const originalBtnText = submitBtn.textContent;
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Authenticating...';

            try {
                const response = await fetch('/shule-cafe/api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ phone, password })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Success! Redirect based on role
                    if (data.user.role === 'super_admin') {
                        window.location.href = 'super-admin/dashboard.html';
                    } else if (data.user.role === 'tenant_admin') {
                        window.location.href = 'headmaster/dashboard.html';
                    } else if (data.user.role === 'regional_officer') {
                        window.location.href = 'regional/dashboard.html';
                    } else {
                        window.location.href = 'layout.html';
                    }
                } else {
                    // Show error from API
                    showError(data.message || "Authentication failed.");
                }
            } catch (error) {
                console.error("Login Error:", error);
                showError("Network error. Please ensure the server is running and try again.");
            } finally {
                // Remove loading state
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        });
    }

    function showError(message) {
        loginError.style.display = 'flex';
        loginError.querySelector('p').textContent = message;
    }
});
