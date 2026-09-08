/**
 * SHULE CAFE – <app-sidebar> Web Component
 * Re-renders its menu whenever the 'shule_lang' changes
 * so all labels switch without a full page reload.
 */
import { getMenuConfig } from '../../js/config/menus.js';

class AppSidebar extends HTMLElement {
    constructor() {
        super();
        this._langHandler = () => this.render();
    }

    static get observedAttributes() {
        return ['role', 'active-path'];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue && this.isConnected) {
            this.render();
        }
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        // Re-render whenever the language changes
        window.addEventListener('shule-lang-changed', this._langHandler);
    }

    disconnectedCallback() {
        window.removeEventListener('shule-lang-changed', this._langHandler);
    }

    get role() {
        const attr = this.getAttribute('role');
        if (attr) return attr;
        return localStorage.getItem('shule_role') || sessionStorage.getItem('shule_role') || 'headmaster';
    }

    get activePath() {
        const pathAttr = this.getAttribute('active-path');
        if (pathAttr) return pathAttr;
        return window.location.pathname;
    }

    render() {
        const menuObj = getMenuConfig();
        const menus = menuObj[this.role] || (this.role === 'headmaster' || this.role === 'tenant_admin' ? (menuObj['headmaster'] || menuObj['tenant_admin']) : null) || menuObj['default'];
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        const basePath = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) : '/';
        const loginUrl = basePath + 'auth/login.html';

        let navHtml = `<ul>`;
        menus.forEach(item => {
            const formattedLink = item.link.startsWith('/') ? basePath + item.link.substring(1) : item.link;
            const isActive = this.activePath.includes(item.link) ? 'active' : '';
            const iconColor = item.color || '#38bdf8';
            navHtml += `
                <li>
                    <a href="${formattedLink}" class="${isActive}">
                        <svg class="nav-icon" style="fill: currentColor;" viewBox="0 0 24 24"><path d="${item.icon}"/></svg>
                        <span>${item.label}</span>
                    </a>
                </li>
            `;
        });

        // Logout button as the last item in the sidebar list
        navHtml += `
            <li class="sidebar-logout-item" style="margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 6px;">
                <a href="${loginUrl}" class="sidebar-logout-link" id="sidebarLogoutBtn" title="Logout" style="color: #f87171 !important;">
                    <svg class="nav-icon" style="fill: currentColor;" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    <span>Logout</span>
                </a>
            </li>
        `;
        navHtml += `</ul>`;

        this.innerHTML = `
            <aside class="app-sidebar sidebar-${this.role}" id="sidebar">
                <div class="sidebar-brand">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 10px; flex-shrink: 0;"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    <span style="font-weight: 700; letter-spacing: 0.5px; color: #ffffff;">SHULE CAFE</span>
                </div>
                <nav class="sidebar-nav">
                    ${navHtml}
                </nav>
            </aside>
        `;
    }

    setupEventListeners() {
        document.addEventListener('toggle-sidebar', () => {
            const sidebar = this.querySelector('#sidebar');
            if (sidebar) {
                sidebar.classList.toggle('collapsed');
            }
        });

        const logoutBtn = this.querySelector('#sidebarLogoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                localStorage.removeItem('shule_token');
                sessionStorage.removeItem('shule_token');
                localStorage.removeItem('shule_user');
                sessionStorage.removeItem('shule_user');
                localStorage.removeItem('shule_role');
                sessionStorage.removeItem('shule_role');
                const currentPath = window.location.pathname;
                const frontendIndex = currentPath.indexOf('/frontend/');
                const basePath = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) : '/frontend/';
                window.location.href = basePath + 'auth/login.html';
            });
        }
    }
}

customElements.define('app-sidebar', AppSidebar);
