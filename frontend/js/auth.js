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
                    const role = data.role || (data.user && data.user.role) || 'tenant_admin';
                    const dashboardMap = {
                        'super_admin': 'super-admin/dashboard.html',
                        'regional_officer': 'regional/dashboard.html',
                        'tenant_admin': 'headmaster/dashboard.html',
                        'school_admin': 'headmaster/dashboard.html',
                        'headmaster': 'headmaster/dashboard.html',
                        'teacher': 'teacher/dashboard.html',
                        'student': 'student/dashboard.html',
                        'parent': 'parent/dashboard.html',
                        'guardian': 'parent/dashboard.html'
                    };
                    window.location.href = dashboardMap[role] || 'headmaster/dashboard.html';
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
