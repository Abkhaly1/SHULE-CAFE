class AppWorkspaceTabs extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        if (this.getAttribute('tabs')) {
            this.renderFromAttribute();
        } else {
            this.setupInnerButtons();
        }
        this.setupEventListeners();
        this.checkUrlTab();
    }

    get activeTab() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('tab') || this.getAttribute('active-tab') || '';
    }

    checkUrlTab() {
        const urlTab = this.activeTab;
        if (urlTab) {
            this.activateTab(urlTab, false);
        }
    }

    renderFromAttribute() {
        let tabs = [];
        try {
            tabs = JSON.parse(this.getAttribute('tabs')) || [];
        } catch (e) {
            tabs = [];
        }
        const active = this.activeTab || (tabs.length > 0 ? tabs[0].id : '');
        let tabsHtml = '';
        tabs.forEach(tab => {
            const isActive = tab.id === active;
            tabsHtml += `
                <button type="button" class="workspace-tab-btn ${isActive ? 'active' : ''}" data-tab="${tab.id}" 
                    style="background: ${isActive ? '#ffffff' : 'transparent'}; 
                    border: 1px solid ${isActive ? 'var(--c-border-light)' : 'transparent'}; 
                    border-radius: 6px; padding: 5px 12px; font-weight: 700; font-size: 12px;
                    color: ${isActive ? 'var(--c-primary)' : 'var(--c-text-muted)'}; 
                    box-shadow: ${isActive ? '0 2px 4px rgba(0,0,0,0.05)' : 'none'};
                    cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 4px;">
                    ${tab.label}
                </button>
            `;
        });

        this.innerHTML = `
            <div class="workspace-tabs" style="display: inline-flex; gap: 4px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 3px; margin-top: 0; margin-bottom: 8px; overflow-x: auto; scrollbar-width: none;">
                ${tabsHtml}
            </div>
        `;
    }

    setupInnerButtons() {
        this.classList.add('workspace-tabs-wrapper');
        this.style.display = 'inline-flex';
        this.style.gap = '4px';
        this.style.background = '#f1f5f9';
        this.style.border = '1px solid #e2e8f0';
        this.style.borderRadius = '8px';
        this.style.padding = '3px';
        this.style.marginTop = '0';
        this.style.marginBottom = '8px';
        this.style.overflowX = 'auto';
        
        const activeTabId = this.activeTab;
        const buttons = this.querySelectorAll('.workspace-tab, button');
        
        buttons.forEach((btn, idx) => {
            const target = btn.getAttribute('data-target') || btn.getAttribute('data-tab') || '';
            const isActive = (target === activeTabId) || (!activeTabId && idx === 0);
            
            btn.classList.add('workspace-tab-btn');
            if (isActive) btn.classList.add('active');

            btn.style.background = isActive ? '#ffffff' : 'transparent';
            btn.style.border = `1px solid ${isActive ? '#cbd5e1' : 'transparent'}`;
            btn.style.borderRadius = '6px';
            btn.style.padding = '5px 12px';
            btn.style.fontWeight = '700';
            btn.style.fontSize = '12px';
            btn.style.color = isActive ? 'var(--c-primary)' : '#64748b';
            btn.style.boxShadow = isActive ? '0 2px 4px rgba(0,0,0,0.05)' : 'none';
            btn.style.cursor = 'pointer';
            btn.style.transition = 'all 0.2s';
            btn.style.display = 'inline-flex';
            btn.style.alignItems = 'center';
            btn.style.gap = '4px';
        });
    }

    activateTab(targetTab, updateUrl = true) {
        const buttons = this.querySelectorAll('.workspace-tab-btn, .workspace-tab, button');
        buttons.forEach(b => {
            const btnTarget = b.getAttribute('data-target') || b.getAttribute('data-tab');
            if (btnTarget === targetTab) {
                b.classList.add('active');
                b.style.background = '#ffffff';
                b.style.border = '1px solid #cbd5e1';
                b.style.color = 'var(--c-primary)';
                b.style.boxShadow = '0 2px 6px rgba(0,0,0,0.06)';
            } else {
                b.classList.remove('active');
                b.style.background = 'transparent';
                b.style.border = '1px solid transparent';
                b.style.color = '#64748b';
                b.style.boxShadow = 'none';
            }
        });

        if (updateUrl && history.pushState) {
            const url = new URL(window.location);
            url.searchParams.set('tab', targetTab);
            window.history.pushState({ tab: targetTab }, '', url);
        }

        document.dispatchEvent(new CustomEvent('workspace-tab-change', {
            detail: { tabId: targetTab }
        }));
    }

    setupEventListeners() {
        const buttons = this.querySelectorAll('.workspace-tab-btn, .workspace-tab, button');
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const targetTab = e.currentTarget.getAttribute('data-target') || e.currentTarget.getAttribute('data-tab');
                if (!targetTab) return;
                this.activateTab(targetTab, true);
            });
        });

        window.addEventListener('popstate', (e) => {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || this.getAttribute('active-tab') || '';
            if (tab) {
                this.activateTab(tab, false);
            }
        });
    }
}

customElements.define('app-workspace-tabs', AppWorkspaceTabs);
