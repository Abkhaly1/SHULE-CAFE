import { AuthService } from './auth-service.js?v=5';

/**
 * AuthGuard
 * This script should be imported at the top of every secure page.
 * It ensures the user is authenticated before rendering the page content.
 */
class AuthGuard {
    static init() {
        // Check if user is authenticated
        if (!AuthService.isAuthenticated()) {
            // Not authenticated, redirect to login
            const currentPath = window.location.pathname;
            
            // Prevent infinite loop if already on login or password recovery/change page
            if (!currentPath.endsWith('login.html') && 
                !currentPath.endsWith('forgot-password.html') && 
                !currentPath.endsWith('reset-password.html') && 
                !currentPath.endsWith('change-password.html')) {
                
                // Store intended destination
                sessionStorage.setItem('shule_redirect_to', window.location.href);
                
                // Redirect
                const frontendIndex = currentPath.indexOf('/frontend/');
                if (frontendIndex !== -1) {
                    const basePath = currentPath.substring(0, frontendIndex + '/frontend/'.length);
                    window.location.href = basePath + 'auth/login.html';
                } else {
                    window.location.href = '/shule-cafe/frontend/auth/login.html';
                }
            }
        } else {
            // User is authenticated.
            const currentPath = window.location.pathname;
            const userRole = AuthService.getUserRole();

            // If on login page, redirect to correct role dashboard
            if (currentPath.endsWith('login.html')) {
                AuthService.redirectBasedOnRole(userRole);
                return;
            }

            // Role-Based Access Control (RBAC) - Protect Dashboards
            if (currentPath.includes('/headmaster/') && !['headmaster', 'tenant_admin', 'super_admin'].includes(userRole)) {
                console.warn('[AuthGuard] Unauthorized role path access attempt:', currentPath, 'Role:', userRole);
                AuthService.redirectBasedOnRole(userRole);
            } else if (currentPath.includes('/teacher/') && !['teacher', 'headmaster', 'tenant_admin', 'super_admin'].includes(userRole)) {
                console.warn('[AuthGuard] Unauthorized role path access attempt:', currentPath, 'Role:', userRole);
                AuthService.redirectBasedOnRole(userRole);
            } else if (currentPath.includes('/super-admin/') && userRole !== 'super_admin') {
                console.warn('[AuthGuard] Unauthorized role path access attempt:', currentPath, 'Role:', userRole);
                AuthService.redirectBasedOnRole(userRole);
            }
        }
    }
}

// Execute immediately upon import
AuthGuard.init();
