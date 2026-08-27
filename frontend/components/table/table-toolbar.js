class AppTableToolbar extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        this.ensureImportModal();
    }

    get entityType() {
        return this.getAttribute('entity-type') || 'general';
    }

    get searchPlaceholder() {
        return this.getAttribute('search-placeholder') || 'Search records...';
    }

    get showFilters() {
        return this.getAttribute('show-filters') !== 'false';
    }

    get showExport() {
        return this.getAttribute('show-export') !== 'false';
    }
    
    get showImport() {
        return this.getAttribute('show-import') !== 'false';
    }

    ensureImportModal() {
        if (!document.querySelector('app-csv-import-modal')) {
            const modalEl = document.createElement('app-csv-import-modal');
            document.body.appendChild(modalEl);
        }
    }

    render() {
        let filtersHtml = this.showFilters ? `
            <button class="btn btn-outline btn-sm" id="btnToolbarFilter" title="Filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                Filter
            </button>
        ` : '';

        let exportHtml = this.showExport ? `
            <div class="export-dropdown" style="position:relative; display:inline-block;">
                <button class="btn btn-outline btn-sm" id="btnToolbarExport" title="Export Data As..." style="display:inline-flex; align-items:center; gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                    Export Data As...
                    <span style="font-size: 12px;">▾</span>
                </button>
                <div class="export-dropdown-menu" style="display:none; position:absolute; right:0; top:110%; background:white; border:1px solid rgba(15, 23, 42, 0.15); border-radius:8px; box-shadow:0 8px 24px rgba(15, 23, 42, 0.08); min-width:220px; z-index:1000; overflow:hidden;">
                    <button type="button" class="export-option" data-format="pdf" style="width:100%; padding:10px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:14px; color:var(--c-text-primary);">PDF Document (.pdf)</button>
                    <button type="button" class="export-option" data-format="excel" style="width:100%; padding:10px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:14px; color:var(--c-text-primary);">Excel Spreadsheet (.xlsx)</button>
                    <button type="button" class="export-option" data-format="csv" style="width:100%; padding:10px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:14px; color:var(--c-text-primary);">CSV Raw File (.csv)</button>
                </div>
            </div>
        ` : '';
        
        let importHtml = this.showImport ? `
            <button class="btn btn-outline btn-sm" id="btnToolbarImport" title="Import CSV">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;"><path d="M9 16h6v-6h4l-7-7-7 7h4zm-4 2h14v2H5z"/></svg>
                Import CSV
            </button>
        ` : '';

        this.innerHTML = `
            <div class="table-toolbar" style="display:flex; justify-content:space-between; align-items:center; padding: var(--sp-4); background: var(--c-bg-surface); border-bottom: 1px solid var(--c-border-light);">
                <div class="toolbar-left" style="display:flex; gap: var(--sp-2);">
                    <div class="search-box" style="position:relative;">
                        <svg style="position:absolute; left:10px; top:10px; color:var(--c-text-muted);" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <input type="text" id="tableSearchInput" class="form-control" placeholder="${this.searchPlaceholder}" style="padding-left: 32px; height: 36px; min-width: 250px;">
                    </div>
                </div>
                <div class="toolbar-right" style="display:flex; gap: var(--sp-2);">
                    ${filtersHtml}
                    ${exportHtml}
                    ${importHtml}
                    <button class="btn btn-outline btn-sm" title="Refresh" id="refreshBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                    </button>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        const searchInput = this.querySelector('#tableSearchInput');
        let timeout = null;
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.dispatchEvent(new CustomEvent('table-search', {
                        detail: { query: e.target.value },
                        bubbles: true
                    }));
                }, 300);
            });
        }

        const exportBtn = this.querySelector('#btnToolbarExport');
        const exportDropdownMenu = this.querySelector('.export-dropdown-menu');
        if (exportBtn && exportDropdownMenu) {
            exportBtn.addEventListener('click', (event) => {
                event.stopPropagation();
                exportDropdownMenu.style.display = exportDropdownMenu.style.display === 'block' ? 'none' : 'block';
            });

            const exportOptions = this.querySelectorAll('.export-option');
            exportOptions.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const format = option.getAttribute('data-format');
                    this.exportTableData(format);
                    exportDropdownMenu.style.display = 'none';
                });
            });

            document.addEventListener('click', (e) => {
                if (!this.contains(e.target)) {
                    exportDropdownMenu.style.display = 'none';
                }
            });
        }

        const importBtn = this.querySelector('#btnToolbarImport');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                const modal = document.querySelector('app-csv-import-modal');
                if (modal) {
                    modal.open(this.entityType);
                }
            });
        }

        const refreshBtn = this.querySelector('#refreshBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.dispatchEvent(new CustomEvent('table-refresh', { bubbles: true }));
                window.location.reload();
            });
        }
    }

    exportTableData(format = 'csv') {
        const type = this.entityType;
        let exportUrl = '';
        const params = new URLSearchParams();
        params.set('format', format);

        if (type === 'schools') {
            exportUrl = '/shule-cafe/api/export/schools.php';
        } else if (type === 'users') {
            exportUrl = '/shule-cafe/api/export/users.php';
        } else if (type === 'templates') {
            exportUrl = '/shule-cafe/api/export/templates.php';
        } else if (type === 'regions') {
            exportUrl = '/shule-cafe/api/export/users.php';
            params.set('role', 'regional_officer');
        } else {
            exportUrl = '/shule-cafe/api/export/users.php';
        }

        window.location.href = `${exportUrl}?${params.toString()}`;
    }
}

customElements.define('app-table-toolbar', AppTableToolbar);
