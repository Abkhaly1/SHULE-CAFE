<?php
session_start();

// If user is already logged in, redirect to their role-specific dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    
    switch ($role) {
        case 'super_admin':
            header("Location: frontend/super-admin/dashboard.html");
            exit();
        case 'regional_officer':
            header("Location: frontend/regional/dashboard.html");
            exit();
        case 'tenant_admin':
            header("Location: frontend/headmaster/dashboard.html");
            exit();
        case 'teacher':
            header("Location: frontend/teacher/dashboard.html");
            exit();
        case 'student':
            header("Location: frontend/student/dashboard.html");
            exit();
        case 'parent':
            header("Location: frontend/parent/dashboard.html");
            exit();
        default:
            header("Location: frontend/auth/login.html");
            exit();
    }
}

// If unauthenticated, redirect directly to login page
header("Location: frontend/auth/login.html");
exit();
?>
