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
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
                    </button>
                    <span style="font-weight: 500; display:flex; align-items:center;">
                        <svg style="width:16px;height:16px;margin-right:4px;vertical-align:middle" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        ${tb.platformName}
                    </span>
                </div>
                <div class="topbar-right" style="display: flex; align-items: center; gap: 12px;">
                    <!-- SYSTEM LANGUAGE BADGE (ENGLISH LOCKED) -->
                    <div class="lang-badge" style="display: inline-flex; align-items: center; background: rgba(255,255,255,0.18); border: 1.5px solid rgba(255,255,255,0.3); border-radius: 20px; padding: 4px 12px; font-weight:800; font-size:12px; color:#ffffff; gap: 6px;">
                        <span>🇺🇸 English (US)</span>
                    </div>

                    <span style="font-size: var(--text-sm);">${tb.howdy} <strong>${this.username}</strong></span>
                    <a href="${loginUrl}" class="topbar-btn" id="logoutBtn" style="color: var(--c-danger-text);" title="${tb.logout}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    </a>
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

        // Language switcher
        const langBtns = this.querySelectorAll('.lang-btn');
        langBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.getAttribute('data-lang');
                const prevLang = getLang();
                if (lang === prevLang) return; // No-op if same lang

                localStorage.setItem('shule_lang', lang);

                // Fire event so all components (sidebar, topbar, page) can update
                window.dispatchEvent(new CustomEvent('shule-lang-changed', { detail: { lang } }));

                // Also broadcast old 'shule-language-changed' for any legacy listeners
                window.dispatchEvent(new CustomEvent('shule-language-changed', { detail: { lang } }));

                // Reload page so all static text (page body) re-renders correctly
                window.location.reload();
            });
        });
    }
}

customElements.define('app-topbar', AppTopbar);
