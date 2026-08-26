/**
 * SHULE CAFE - Global Translation Engine (100% English System)
 * Single source of truth for ALL text in the system.
 */

export const translations = {
    en: {
        // ─── SIDEBAR MENU LABELS ─────────────────────────────────────────────
        menu: {
            dashboard:           'Dashboard',
            schools:             'Schools',
            regionalOffices:     'Regions',
            academicTemplates:   'Templates',
            platformUsers:       'Users',
            databaseBackups:     'Backups',
            reports:             'Reports',
            settings:            'Settings',
            myProfile:           'Profile',
            studentsDirectory:   'Students',
            teachersStaffs:      'Teachers',
            classGuiders:        'Guiders',
            subjectAllocations:  'Subjects',
            academics:           'Academics',
            classrooms:          'Classes',
            timetables:          'Timetables',
            myAssignedClasses:   'My Classes',
            mySchedule:          'Schedule',
            markEntry:           'Marks',
            attendanceTracker:   'Attendance',
            parentDesk:          'Parents',
            mySubjects:          'Subjects',
            grades:              'Grades',
            myChildren:          'Children',
            assessmentConfig:    'Assessments',
            comparativeAnalytics:'Analytics',
            classLedger:         'Ledger',
        },

        // ─── TOPBAR ──────────────────────────────────────────────────────────
        topbar: {
            platformName: 'Shule Cafe Platform',
            howdy:        'Howdy,',
            logout:       'Logout',
            langEn:       '🇺🇸 EN',
            langSw:       '🇹🇿 SW',
        },

        // ─── COMMON / SHARED ─────────────────────────────────────────────────
        common: {
            loading:      'Loading...',
            save:         'Save',
            cancel:       'Cancel',
            edit:         'Edit',
            delete:       'Delete',
            view:         'View',
            search:       'Search...',
            filter:       'Filter',
            submit:       'Submit',
            close:        'Close',
            yes:          'Yes',
            no:           'No',
            back:         'Back',
            next:         'Next',
            confirm:      'Confirm',
            success:      'Success',
            error:        'Error',
            warning:      'Warning',
            noData:       'No records found.',
            allYears:     'All Years',
            allMonths:    'All Months',
            allStreams:   'All Classroom Streams',
            actions:      'Actions',
            status:       'Status',
            name:         'Name',
            date:         'Date',
            year:         'Year',
            month:        'Month',
            active:       'Active',
            inactive:     'Inactive',
            present:      'Present',
            absent:       'Absent',
            excused:      'Excused',
            total:        'Total',
            january:      'January',
            february:     'February',
            march:        'March',
            april:        'April',
            may:          'May',
            june:         'June',
            july:         'July',
            august:       'August',
            september:    'September',
            october:      'October',
            november:     'November',
            december:     'December',
            remarks:      'Remarks',
            noRemarks:    'No remarks recorded',
            regId:        'Registration ID',
            fullName:     'Full Name',
        },

        // ─── ATTENDANCE PAGE ─────────────────────────────────────────────────
        attendance: {
            pageTitle:          'Daily Attendance Register',
            hubTitle:           'MASTER DAILY ATTENDANCE HUB',
            tabEntry:           '📋 Daily Roll-Call Register',
            tabHistory:         '📜 Attendance History Logs',
            selectClassroom:    '🏫 Select Classroom Stream:',
            selectDate:         '📆 Select Date:',
            modeLocked:         '🔒 Saved & Sealed',
            modeEdit:           '✏️ Edit Mode Active',
            totalRoster:        'TOTAL ROSTER',
            rosterTitle:        '👥 Student Roll-Call Roster Sheet',
            searchPlaceholder:  '🔍 Search student by name or ID...',
            colNum:             '#',
            colRegId:           'Student Reg ID',
            colName:            'Full Name',
            colStatus:          'Attendance Status (Toggle)',
            colStatusLocked:    'Attendance Status',
            remarksLabel:       '📝 Additional Remarks / Notes (Optional):',
            remarksPlaceholder: 'Enter optional remarks for this date (e.g. John had a family emergency)...',
            savedNotice:        '🔒 Attendance for this date has been cataloged & sealed. Click Edit to modify.',
            btnEdit:            '✏️ Edit Roll-Call',
            btnSave:            '💾 SAVE DAILY ATTENDANCE',
            btnCancel:          '❌ Cancel Edit',
            btnFilter:          '🔍 Filter History',
            historyTitle:       '📜 Saved Attendance Roll-Call Logs',
            histColDate:        'Date & Day',
            histColClass:       'Classroom & Year',
            histColBreakdown:   'Student Attendance Breakdown',
            histColRemarks:     'Remarks',
            histColAction:      'Action',
            noStudents:         'No active students found in this classroom roster.',
            noHistory:          'No attendance logs found for the selected filters.',
            filterYear:         '📅 Academic Year:',
            filterMonth:        '🗓️ Month:',
            filterRoom:         '🏫 Classroom Stream:',
            accessDeniedTitle:  'Access Restricted: Class Guider / Form Master Only',
            savedMsg:           'Attendance saved successfully!',
            errorSaving:        'Error saving attendance. Please try again.',
            networkError:       'Network error. Please check your connection.',
            saving:             'Saving...',
        },

        // ─── PROFILE PAGE ─────────────────────────────────────────────────────
        profile: {
            pageTitle:          'My Profile',
            fullName:           'Full Name',
            regId:              'Reg Code / Staff ID',
            role:               'Role',
            locked:             '🔒 Lock',
            editableSection:    '✏️ Editable Information',
            phone:              'Phone Number',
            email:              'Email Address',
            gender:             'Gender',
            male:               'Male',
            female:             'Female',
            btnSave:            '💾 Save Profile Changes',
            securitySection:    '🔒 Security & Password',
            currentPassword:    'Current Password',
            newPassword:        'New Password',
            confirmPassword:    'Confirm New Password',
            btnChangePassword:  '🔑 Change Password Now',
        },

        // ─── DASHBOARD SHARED ─────────────────────────────────────────────────
        dashboard: {
            welcomeBack:        'Welcome back,',
            quickStats:         'Quick Statistics',
            recentActivity:     'Recent Activity',
            noActivity:         'No recent activity.',
        },

        // ─── TEACHER DASHBOARD ────────────────────────────────────────────────
        teacherDashboard: {
            mySubjects:         'My Subjects',
            totalStudents:      'Total Students',
            formMasterClass:    'Form Master Class',
            todayPeriods:       "Today's Periods",
            myTodaySchedule:    "Today's Teaching Schedule",
            day:                'Day',
            stream:             'Stream',
            subject:            'Subject',
            period:             'Period',
            time:               'Time',
            noSchedule:         'No teaching periods scheduled for today.',
        },

        // ─── HEADMASTER DASHBOARD ──────────────────────────────────────────────
        headmasterDashboard: {
            totalStudents:      'Total Students',
            totalTeachers:      'Total Teachers',
            totalClasses:       'Total Classrooms',
            averageAttendance:  'Avg. Attendance Rate',
        },

        // ─── PROFILE ─────────────────────────────────────────────────────────
        profile: {
            breadcrumb:       'SHULE CAFE, My Profile',
            roleLabel:        'Role',
            schoolLabel:      'School',
            secLockedTitle:   'Permanent Identifiers (Cannot be changed by user)',
            secLockedDesc:    'Full Name and Registration Code are permanent identifiers. Only the Headmaster can change them if required.',
            lblFullName:      'Full Name',
            lblRegId:         'Reg Code / Staff ID',
            lblRole:          'Role',
            secEditableTitle: 'Editable Information',
            lblPhone:         'Phone Number',
            phPhone:          'e.g. +255712345678',
            lblEmail:         'Email Address',
            phEmail:          'e.g. user@school.ac.tz',
            lblGender:        'Gender',
            optMale:          'Male',
            optFemale:        'Female',
            btnSaveProfile:   'Save Profile Changes',
            secSecurityTitle: 'Security & Password',
            lblCurrPass:      'Current Password',
            phCurrPass:       'Enter your current password',
            lblNewPass:       'New Password',
            phNewPass:        'Minimum 8 characters',
            lblConfirmPass:   'Confirm New Password',
            phConfirmPass:    'Re-enter new password',
            btnSavePassword:  'Change Password Now',
            saving:           'Saving...',
            changing:         'Changing...',
            profileSaved:     'Profile updated successfully!',
            profileSaveErr:   'Error saving profile.',
            pwdSaved:         'Password changed successfully!',
            pwdErr:           'Error changing password.',
        },
    },

    sw: {}
};

// English-Only System Directive: Alias sw to en to prevent Swahili language rendering anywhere
translations.sw = translations.en;

/**
 * Get the current active language key ('en') - Locked to 100% English
 */
export function getLang() {
    try { localStorage.setItem('shule_lang', 'en'); } catch(e) {}
    return 'en';
}

/**
 * Get translations object for current language.
 * Returns English as fallback if key missing.
 */
export function t() {
    const lang = getLang();
    return translations[lang] || translations['en'];
}

/**
 * Translate a deeply nested key using dot notation, e.g. 'menu.dashboard'
 */
export function tr(key) {
    const lang = getLang();
    const parts = key.split('.');
    let val = translations[lang];
    for (const p of parts) {
        val = val?.[p];
        if (val === undefined) break;
    }
    // Fallback to English
    if (val === undefined) {
        let fallback = translations['en'];
        for (const p of parts) {
            fallback = fallback?.[p];
            if (fallback === undefined) break;
        }
        return fallback ?? key;
    }
    return val;
}

/**
 * Apply translations to all elements with data-i18n attribute.
 * Usage in HTML: <span data-i18n="menu.dashboard">Dashboard</span>
 */
export function applyTranslations(root = document) {
    root.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        const translated = tr(key);
        if (translated) {
            if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                const attr = el.getAttribute('data-i18n-attr') || 'placeholder';
                el.setAttribute(attr, translated);
            } else {
                el.textContent = translated;
            }
        }
    });
}
