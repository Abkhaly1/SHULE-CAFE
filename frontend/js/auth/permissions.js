/**
 * Role-Based Permissions Map (Frontend)
 * 
 * IMPORTANT: This is for UI visibility only (hiding buttons, tabs, etc.).
 * The Backend MUST enforce these same permissions on every API request.
 */
export const Permissions = {
    // Platform Level
    SUPER_ADMIN: [
        'manage_tenants',
        'manage_global_settings',
        'view_audit_logs',
        'manage_regional_officers'
    ],
    
    // Regional Level
    REGIONAL_OFFICER: [
        'view_regional_dashboard',
        'view_schools_in_region',
        'view_regional_reports'
    ],
    
    // School Level
    HEADMASTER: [
        'manage_school_settings',
        'manage_teachers',
        'manage_students',
        'manage_classes',
        'manage_subjects',
        'view_all_reports',
        'manage_academic_years'
    ],
    
    TEACHER: [
        'view_assigned_classes',
        'manage_attendance_own',
        'manage_results_own',
        'view_student_profiles'
    ],
    
    STUDENT: [
        'view_own_results',
        'view_own_attendance',
        'view_own_profile',
        'view_announcements'
    ],
    
    PARENT: [
        'view_child_results',
        'view_child_attendance',
        'view_school_announcements'
    ]
};

// Aliases for multi-tenant and administrative roles
Permissions.TENANT_ADMIN = Permissions.HEADMASTER;
Permissions.SCHOOL_ADMIN = Permissions.HEADMASTER;
Permissions.GUARDIAN = Permissions.PARENT;

/**
 * Helper to check if a role has a specific permission
 */
export function hasPermission(role, permission) {
    const roleKey = role ? role.toUpperCase() : null;
    if (!roleKey || !Permissions[roleKey]) {
        return false;
    }
    return Permissions[roleKey].includes(permission);
}
