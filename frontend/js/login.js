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
                togglePasswordBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
            } else {
                passwordInput.type = 'password';
                togglePasswordBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
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
