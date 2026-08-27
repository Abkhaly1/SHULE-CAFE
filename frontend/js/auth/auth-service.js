/**
 * Authentication Service
 * Handles login, logout, token management, and tenant detection.
 */

export const AuthService = {
    // ------------------------------------------------------------------------
    // Token Management (JWT)
    // ------------------------------------------------------------------------
    setToken(token, rememberMe, role) {
        if (rememberMe) {
            localStorage.setItem('shule_token', token);
            if (role) localStorage.setItem('shule_role', role);
        } else {
            sessionStorage.setItem('shule_token', token);
            if (role) sessionStorage.setItem('shule_role', role);
        }
    },

    getToken() {
        return localStorage.getItem('shule_token') || sessionStorage.getItem('shule_token');
    },

    getUserRole() {
        return sessionStorage.getItem('shule_role') || localStorage.getItem('shule_role') || 'tenant_admin';
    },

    removeToken() {
        localStorage.removeItem('shule_token');
        sessionStorage.removeItem('shule_token');
        localStorage.removeItem('shule_role');
        sessionStorage.removeItem('shule_role');
        sessionStorage.removeItem('shule_user');
        sessionStorage.removeItem('shule_redirect_to');
    },

    isAuthenticated() {
        const token = this.getToken();
        if (!token) return false;
        
        // Basic JWT expiration check could go here if we decode the payload
        return true; 
    },

    // ------------------------------------------------------------------------
    // Authentication APIs (Mocked for Frontend Architecture Phase)
    // ------------------------------------------------------------------------
    async login(username, password, rememberMe) {
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        const apiPath = (frontendIndex !== -1) 
            ? currentPath.substring(0, frontendIndex) + '/api/auth/login.php' 
            : '/api/auth/login.php';

        console.log('[AuthService] Attempting login to API path:', apiPath);

        try {
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ phone: username, username: username, password: password })
            });

            const data = await response.json();
            console.log('[AuthService] API Response:', data);

            if (response.ok && data.success) {
                const role = data.role || (data.user && data.user.role) || 'tenant_admin';
                const token = data.token || 'db_session_token_' + Date.now();
                this.setToken(token, rememberMe, role);
                if (data.user) {
                    sessionStorage.setItem('shule_user', JSON.stringify(data.user));
                }
                return { 
                    requireFirstTimeSetup: !!data.require_first_time_setup, 
                    forcePasswordChange: !!data.require_password_change, 
                    role: role, 
                    user: data.user 
                };
            } else {
                throw new Error(data.message || 'Invalid phone number or password.');
            }
        } catch (err) {
            console.warn('[AuthService] API fetch failed or returned error:', err);
            throw err;
        }
    },

    logout() {
        this.removeToken();
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        if (frontendIndex !== -1) {
            const basePath = currentPath.substring(0, frontendIndex + '/frontend/'.length);
            window.location.href = basePath + 'auth/login.html';
        } else {
            window.location.href = '/shule-cafe/frontend/auth/login.html';
        }
    },

    async requestPasswordReset(identity) {
        // MOCK API CALL
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({ success: true }); // Always resolve to prevent user enumeration
            }, 1000);
        });
    },

    async resetPassword(currentPassword, newPassword) {
        // MOCK API CALL
        return new Promise((resolve, reject) => {
            setTimeout(() => {
                if (currentPassword && currentPassword !== 'temp123') {
                    reject(new Error('Current password is incorrect.'));
                } else {
                    this.setToken('mock_jwt_token_after_reset', true);
                    resolve({ success: true });
                }
            }, 1000);
        });
    },

    // ------------------------------------------------------------------------
    // Role & Tenant Routing
    // ------------------------------------------------------------------------
    redirectBasedOnRole(role) {
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        // If we're already inside /frontend/, extract the base up to and including /frontend/
        // Otherwise fall back to the known app root
        let basePath;
        if (frontendIndex !== -1) {
            basePath = currentPath.substring(0, frontendIndex + '/frontend/'.length);
        } else {
            // Detect the app subfolder from pathname (e.g. /shule-cafe/)
            const parts = currentPath.split('/');
            // parts[0]='' parts[1]='shule-cafe' or similar app folder
            const appFolder = parts[1] ? '/' + parts[1] + '/' : '/';
            basePath = appFolder + 'frontend/';
        }

        switch (role) {
            case 'super_admin':
                window.location.href = basePath + 'super-admin/dashboard.html';
                break;
            case 'regional_officer':
                window.location.href = basePath + 'regional/dashboard.html';
                break;
            case 'headmaster':
            case 'tenant_admin':
            case 'school_admin':
                window.location.href = basePath + 'headmaster/dashboard.html';
                break;
            case 'teacher':
                window.location.href = basePath + 'teacher/dashboard.html';
                break;
            case 'student':
                window.location.href = basePath + 'student/dashboard.html';
                break;
            case 'parent':
            case 'guardian':
                window.location.href = basePath + 'parent/dashboard.html';
                break;
            default:
                window.location.href = basePath + 'auth/login.html';
        }
    },

    getTenantFromSubdomain() {
        // Example: Extracts 'mdss' from 'mdss.shulecafe.com'
        const hostname = window.location.hostname;
        const parts = hostname.split('.');
        if (parts.length >= 3) {
            return parts[0]; // e.g. 'mdss'
        }
        return null; // Top-level domain (likely super admin or generic landing)
    },

    // ------------------------------------------------------------------------
    // Password Validation
    // ------------------------------------------------------------------------
    validatePasswordStrength(password) {
        let score = 0;
        const result = {
            hasLength: password.length >= 8,
            hasUpper: /[A-Z]/.test(password),
            hasLower: /[a-z]/.test(password),
            hasNumber: /[0-9]/.test(password),
            hasSpecial: /[!@#$%^&*(),.?":{}|<>]/.test(password),
            score: 0
        };

        if (result.hasLength) score++;
        if (result.hasUpper) score++;
        if (result.hasLower) score++;
        if (result.hasNumber) score++;
        if (result.hasSpecial) score++;

        // Bonus for length > 12
        if (password.length >= 12 && score >= 4) score++;

        result.score = score;
        return result;
    }
};
