class AppWorkspaceToolbar extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    get searchPlaceholder() {
        return this.getAttribute('search-placeholder') || 'Search inside workspace...';
    }

    get showFilters() {
        return this.getAttribute('show-filters') === 'true';
    }
    
    get primaryAction() {
        return this.getAttribute('primary-action') || '';
    }

    get secondaryActions() {
        const raw = this.getAttribute('secondary-actions');
        if (!raw) return [];
        try {
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    }

    render() {
        let filtersHtml = this.showFilters ? `
            <button class="btn btn-outline btn-sm" title="Filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                Filter
            </button>
        ` : '';

        let secondaryHtml = '';
        const secActions = this.secondaryActions;
        if (secActions && secActions.length > 0) {
            secActions.forEach(sec => {
                if (sec.menu && Array.isArray(sec.menu) && sec.menu.length > 0) {
                    let menuItems = '';
                    sec.menu.forEach(item => {
                        menuItems += `
                            <button type="button" class="toolbar-dropdown-item" data-action="${item.action}" style="width:100%; padding:10px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:14px; color:var(--c-text-primary);">${item.label}</button>
                        `;
                    });

                    secondaryHtml += `
                        <div class="toolbar-dropdown" style="position:relative; display:inline-block;">
                            <button type="button" class="btn btn-outline btn-sm toolbar-dropdown-toggle" style="display:inline-flex; align-items:center; gap:4px; font-weight:600;" title="${sec.label}">
                                ${sec.label}
                                <span style="font-size:12px;">▾</span>
                            </button>
                            <div class="toolbar-dropdown-menu" style="display:none; position:absolute; right:0; top:110%; background:white; border:1px solid rgba(15, 23, 42, 0.15); border-radius:8px; box-shadow:0 8px 24px rgba(15, 23, 42, 0.08); min-width:220px; z-index:1000; overflow:hidden;">
                                ${menuItems}
                            </div>
                        </div>
                    `;
                } else {
                    secondaryHtml += `
                        <button type="button" class="btn btn-outline btn-sm secondary-action-btn" data-action="${sec.action}" style="display:inline-flex; align-items:center; gap:4px; font-weight:600;">
                            ${sec.label}
                        </button>
                    `;
                }
            });
        }

        let primaryHtml = this.primaryAction ? `
            <button type="button" class="btn btn-primary btn-sm primary-action-btn" style="font-weight:600;">
                ${this.primaryAction}
            </button>
        ` : '';

        this.innerHTML = `
            <div class="workspace-toolbar" style="display:flex; justify-content:space-between; align-items:center; margin: var(--sp-4) var(--sp-6); padding-bottom: var(--sp-4); border-bottom: 1px solid var(--c-border-light);">
                <div class="toolbar-left" style="display:flex; gap: var(--sp-2);">
                    <div class="search-box" style="position:relative;">
                        <svg style="position:absolute; left:10px; top:10px; color:var(--c-text-muted);" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <input type="text" class="form-control toolbar-search-input" placeholder="${this.searchPlaceholder}" style="padding-left: 32px; height: 36px; min-width: 250px;">
                    </div>
                </div>
                <div class="toolbar-right" style="display:flex; gap: var(--sp-2); align-items:center;">
                    ${filtersHtml}
                    ${secondaryHtml}
                    ${primaryHtml}
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        const searchInput = this.querySelector('.toolbar-search-input');
        if (searchInput) {
            let searchDebounce;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(() => {
                    this.dispatchEvent(new CustomEvent('toolbar-search', {
                        bubbles: true,
                        detail: { query: e.target.value.trim() }
                    }));
                }, 150);
            });
        }
        const pBtn = this.querySelector('.primary-action-btn');
        if (pBtn) {
            pBtn.addEventListener('click', () => {
                this.dispatchEvent(new CustomEvent('toolbar-primary-click', { bubbles: true }));
            });
        }

        const secBtns = this.querySelectorAll('.secondary-action-btn');
        secBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-action');
                this.dispatchEvent(new CustomEvent('toolbar-secondary-click', { 
                    bubbles: true, 
                    detail: { action: action } 
                }));
            });
        });

        const dropdownToggles = this.querySelectorAll('.toolbar-dropdown-toggle');
        const closeDropdowns = () => {
            const menus = this.querySelectorAll('.toolbar-dropdown-menu');
            menus.forEach(menu => menu.style.display = 'none');
        };

        dropdownToggles.forEach(btn => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                const menu = btn.nextElementSibling;
                if (menu) {
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                }
            });
        });

        const dropdownItems = this.querySelectorAll('.toolbar-dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', (event) => {
                event.stopPropagation();
                const action = item.getAttribute('data-action');
                this.dispatchEvent(new CustomEvent('toolbar-secondary-click', { 
                    bubbles: true, 
                    detail: { action: action } 
                }));
                closeDropdowns();
            });
        });

        document.addEventListener('click', () => {
            closeDropdowns();
        });
    }
}

customElements.define('app-workspace-toolbar', AppWorkspaceToolbar);
