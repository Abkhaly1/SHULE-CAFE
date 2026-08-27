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
        // Expected format: "Dashboard, Students, View All"
        return this.getAttribute('path') || 'Dashboard';
    }

    render() {
        const parts = this.path.split(',').map(p => p.trim());
        
        const routeMap = {
            'Headmaster': '/shule-cafe/frontend/headmaster/dashboard.html',
            'Super Admin': '/shule-cafe/frontend/super-admin/dashboard.html',
            'Teacher': '/shule-cafe/frontend/teacher/dashboard.html',
            'Regional': '/shule-cafe/frontend/regional/dashboard.html',
            'Settings': '/shule-cafe/frontend/headmaster/settings/index.html',
            'Templates': '/shule-cafe/frontend/super-admin/templates/index.html',
            'Schools': '/shule-cafe/frontend/super-admin/schools/index.html',
            'Regions': '/shule-cafe/frontend/super-admin/regions/index.html',
            'Students': '/shule-cafe/frontend/headmaster/people/students.html',
            'Students Directory': '/shule-cafe/frontend/headmaster/people/students.html',
            'Teachers': '/shule-cafe/frontend/headmaster/people/teachers.html',
            'Teachers/Staffs': '/shule-cafe/frontend/headmaster/people/teachers.html',
            'Teachers & Staffs': '/shule-cafe/frontend/headmaster/people/teachers.html',
            'Academics': '/shule-cafe/frontend/headmaster/academics/index.html',
            'Classrooms': '/shule-cafe/frontend/headmaster/classrooms/index.html',
            'Timetables': '/shule-cafe/frontend/headmaster/timetable/index.html',
            'Reports': '/shule-cafe/frontend/headmaster/reports/index.html',
            'School Reports': '/shule-cafe/frontend/headmaster/reports/index.html',
            'Class Guiders': '/shule-cafe/frontend/headmaster/allocations/class-guiders.html',
            'Subject Allocations': '/shule-cafe/frontend/headmaster/allocations/subject-allocations.html',
            'Assessment Config': '/shule-cafe/frontend/headmaster/academics/assessment-config.html'
        };

        const homeUrl = window.location.pathname.includes('/headmaster/') ? '/shule-cafe/frontend/headmaster/dashboard.html' :
                        (window.location.pathname.includes('/super-admin/') ? '/shule-cafe/frontend/super-admin/dashboard.html' : '#');
        
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
