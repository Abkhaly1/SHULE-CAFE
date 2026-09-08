/**
 * SHULE CAFE – <app-topbar> Web Component
 * Fully i18n-aware. Labels pulled from i18n.js.
 * Fires 'shule-lang-changed' event on window so sidebar re-renders without full page reload.
 */
import { t, getLang } from '../../js/i18n.js';

class AppTopbar extends HTMLElement {
    constructor() {
        super();
        this._langHandler = () => this.render();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        window.addEventListener('shule-lang-changed', this._langHandler);
    }

    disconnectedCallback() {
        window.removeEventListener('shule-lang-changed', this._langHandler);
    }

    get username() {
        return this.getAttribute('username') || 'User';
    }

    render() {
        const currentPath = window.location.pathname;
        const frontendIndex = currentPath.indexOf('/frontend/');
        const loginUrl = (frontendIndex !== -1) ? currentPath.substring(0, frontendIndex + '/frontend/'.length) + 'login.html' : '/login.html';

        const currentLang = getLang();
        const tb = t().topbar;

        this.innerHTML = `
            <header class="app-topbar">
                <div class="topbar-left">
                    <button class="topbar-btn" id="toggleSidebarBtn" title="Toggle Sidebar">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                    </button>
                    <span style="font-weight: 600; font-size: 13px; display:flex; align-items:center; letter-spacing: 0.2px;">
                        <svg style="width:16px;height:16px;margin-right:6px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        ${tb.platformName}
                    </span>
                </div>
            </header>
        `;
    }

    setupEventListeners() {
        // Sidebar toggle
        const toggleBtn = this.querySelector('#toggleSidebarBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                document.dispatchEvent(new CustomEvent('toggle-sidebar'));
            });
        }
    }
}

customElements.define('app-topbar', AppTopbar);
