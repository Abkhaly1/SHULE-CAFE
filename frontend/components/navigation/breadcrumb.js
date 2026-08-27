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
        
        let breadcrumbHtml = `<nav class="breadcrumb-ribbon" aria-label="Breadcrumb">`;
        
        // 1. Home Icon Item
        breadcrumbHtml += `
            <a href="#" class="breadcrumb-icon-link" title="Home">
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
                breadcrumbHtml += `
                    <a href="#" class="breadcrumb-link">${part}</a>
                    <span class="breadcrumb-sep">&raquo;&raquo;</span>
                `;
            }
        });
        
        breadcrumbHtml += `</nav>`;
        this.innerHTML = breadcrumbHtml;
    }
}

customElements.define('app-breadcrumb', AppBreadcrumb);
