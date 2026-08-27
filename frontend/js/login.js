// frontend/js/login.js

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const errorNotice = document.getElementById('errorNotice');
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');

    const token = getAuthToken();
    const user = getCurrentUser();
    if (token && user) {
        redirectToDashboard(user.role);
        return;
    }

    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', () => {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                togglePasswordBtn.innerHTML = '🙈';
            } else {
                passwordInput.type = 'password';
                togglePasswordBtn.innerHTML = '👁️';
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = usernameInput.value;
            const password = passwordInput.value;

            errorNotice.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Logging in...';

            try {
                const data = await login(username, password);
                redirectToDashboard(data.user.role);
            } catch (err) {
                console.error("Login failed", err);
                errorMessage.textContent = err.message || 'An unexpected error occurred. Please try again.';
                errorNotice.style.display = 'block';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Log In';
            }
        });
    }

    function redirectToDashboard(role) {
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
    }
});
