class AppWorkspaceContent extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.setupTabs();
        
        // Listen for tab changes globally
        document.addEventListener('workspace-tab-change', (e) => {
            this.switchTab(e.detail.tabId);
        });

        // If active on load, dispatch tab-activated after microtask
        setTimeout(() => {
            if (this.style.display !== 'none') {
                this.dispatchEvent(new CustomEvent('tab-activated', { bubbles: true }));
            }
        }, 0);
    }

    get myTabId() {
        return this.getAttribute('tab-id') || '';
    }

    setupTabs() {
        const urlParams = new URLSearchParams(window.location.search);
        const urlTab = urlParams.get('tab');
        
        let isActive = this.getAttribute('active') === 'true';
        if (urlTab && this.myTabId) {
            isActive = (this.myTabId === urlTab);
        }

        if (this.myTabId) {
            this.style.display = isActive ? 'block' : 'none';
        }

        const defaultTab = urlTab || this.getAttribute('default-tab');
        const sections = this.querySelectorAll('[data-tab-content]');
        sections.forEach(sec => {
            if (sec.getAttribute('data-tab-content') === defaultTab) {
                sec.style.display = 'block';
            } else {
                sec.style.display = 'none';
            }
        });
    }

    switchTab(tabId) {
        if (this.myTabId) {
            if (this.myTabId === tabId) {
                this.style.display = 'block';
                this.dispatchEvent(new CustomEvent('tab-activated', { bubbles: true }));
            } else {
                this.style.display = 'none';
            }
        }

        const sections = this.querySelectorAll('[data-tab-content]');
        sections.forEach(sec => {
            if (sec.getAttribute('data-tab-content') === tabId) {
                sec.style.display = 'block';
                sec.dispatchEvent(new CustomEvent('tab-activated', { bubbles: true }));
            } else {
                sec.style.display = 'none';
            }
        });
    }
}

customElements.define('app-workspace-content', AppWorkspaceContent);
