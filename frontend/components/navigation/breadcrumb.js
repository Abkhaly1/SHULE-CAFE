class AppBreadcrumb extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    static get observedAttributes() {
        return ['path'];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name === 'path' && oldValue !== newValue) {
            this.render();
        }
    }

    get path() {
        // Expected format: "Headmaster, Settings, Assessment Config"
        return this.getAttribute('path') || 'Headmaster';
    }

    render() {
        const parts = this.path.split(',').map(p => p.trim());
        
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        const basePath = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) : '/frontend/';
        
        const routeMap = {
            'Headmaster': basePath + 'headmaster/dashboard.html',
            'Dashboard': basePath + 'headmaster/dashboard.html',
            'Super Admin': basePath + 'super-admin/dashboard.html',
            'Teacher': basePath + 'teacher/dashboard.html',
            'Regional': basePath + 'regional/dashboard.html',
            'Settings': basePath + 'headmaster/settings/index.html',
            'School Setup': basePath + 'headmaster/settings/index.html',
            'Setup': basePath + 'headmaster/settings/index.html',
            'School Profile': basePath + 'headmaster/settings/school-profile.html',
            'Templates': basePath + 'super-admin/templates/index.html',
            'Schools': basePath + 'super-admin/schools/index.html',
            'Regions': basePath + 'super-admin/regions/index.html',
            'Students': basePath + 'headmaster/people/students.html',
            'Students Directory': basePath + 'headmaster/people/students.html',
            'Teachers': basePath + 'headmaster/people/teachers.html',
            'Teachers/Staffs': basePath + 'headmaster/people/teachers.html',
            'Teachers & Staffs': basePath + 'headmaster/people/teachers.html',
            'Academics': basePath + 'headmaster/academics/index.html',
            'Academics Management': basePath + 'headmaster/academics/index.html',
            'Classrooms': basePath + 'headmaster/classrooms/index.html',
            'Timetables': basePath + 'headmaster/timetable/index.html',
            'Reports': basePath + 'headmaster/reports/index.html',
            'School Reports': basePath + 'headmaster/reports/index.html',
            'Class Guiders': basePath + 'headmaster/allocations/class-guiders.html',
            'Subject Allocations': basePath + 'headmaster/allocations/subject-allocations.html',
            'Assessment Config': basePath + 'headmaster/academics/assessment-config.html'
        };

        const homeUrl = currentPath.includes('/headmaster/') ? (basePath + 'headmaster/dashboard.html') :
                        (currentPath.includes('/super-admin/') ? (basePath + 'super-admin/dashboard.html') :
                        (currentPath.includes('/teacher/') ? (basePath + 'teacher/dashboard.html') :
                        (currentPath.includes('/regional/') ? (basePath + 'regional/dashboard.html') : (basePath + 'dashboard.html'))));
        
        let breadcrumbHtml = `<nav class="breadcrumb-ribbon" aria-label="Breadcrumb">`;
        
        // 1. Home Icon Item
        breadcrumbHtml += `
            <a href="${homeUrl}" class="breadcrumb-icon-link" title="Home">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </a>
            <span class="breadcrumb-sep">&raquo;&raquo;</span>
        `;
        
        // 2. Path Items
        parts.forEach((part, index) => {
            if (index === parts.length - 1) {
                // Active item styled as Chevron Arrow Badge
                breadcrumbHtml += `<span class="breadcrumb-active-ribbon">${part}</span>`;
            } else {
                const targetUrl = routeMap[part] || '#';
                breadcrumbHtml += `
                    <a href="${targetUrl}" class="breadcrumb-link">${part}</a>
                    <span class="breadcrumb-sep">&raquo;&raquo;</span>
                `;
            }
        });
        
        breadcrumbHtml += `</nav>`;
        this.innerHTML = breadcrumbHtml;
    }
}

customElements.define('app-breadcrumb', AppBreadcrumb);
