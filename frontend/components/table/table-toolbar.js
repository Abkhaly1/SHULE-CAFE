class AppTableToolbar extends HTMLElement {
    constructor() {
        super();
        this._count = 0;
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        this.ensureImportModal();
        this.syncWithTable();
    }

    get entityType() {
        return this.getAttribute('entity-type') || 'general';
    }

    get searchPlaceholder() {
        return this.getAttribute('search-placeholder') || 'Search...';
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

    setCount(count) {
        this._count = count !== undefined && count !== null ? count : 0;
        const badge = this.querySelector('#toolbarCountBadge');
        if (badge) {
            badge.textContent = this._count;
        }
    }

    syncWithTable() {
        // Automatically check if an app-table or table exists nearby and sync record count
        setTimeout(() => {
            const table = this.closest('.card')?.querySelector('app-table, table') || document.querySelector('app-table, table');
            if (table) {
                if (table.data && Array.isArray(table.data)) {
                    this.setCount(table.data.length);
                } else {
                    const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
                    if (rows.length > 0) this.setCount(rows.length);
                }
            }
        }, 100);
    }

    ensureImportModal() {
        if (!document.querySelector('app-csv-import-modal')) {
            const modalEl = document.createElement('app-csv-import-modal');
            document.body.appendChild(modalEl);
        }
    }

    render() {
        let exportHtml = this.showExport ? `
            <div class="export-dropdown" style="position:relative; display:inline-block;">
                <button class="toolbar-action-btn" id="btnToolbarExport" title="Export Data (PDF, Excel, CSV)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </button>
                <div class="export-dropdown-menu" style="display:none; position:absolute; right:0; top:110%; background:white; border:1px solid rgba(15, 23, 42, 0.15); border-radius:8px; box-shadow:0 8px 24px rgba(15, 23, 42, 0.1); min-width:200px; z-index:1000; overflow:hidden;">
                    <button type="button" class="export-option" data-format="excel" style="width:100%; padding:9px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:13px; color:#1e293b; font-weight:600; display:flex; align-items:center; gap:8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        Excel Spreadsheet (.xlsx)
                    </button>
                    <button type="button" class="export-option" data-format="pdf" style="width:100%; padding:9px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:13px; color:#1e293b; font-weight:600; display:flex; align-items:center; gap:8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        PDF Document (.pdf)
                    </button>
                    <button type="button" class="export-option" data-format="csv" style="width:100%; padding:9px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:13px; color:#1e293b; font-weight:600; display:flex; align-items:center; gap:8px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        CSV Text File (.csv)
                    </button>
                </div>
            </div>
        ` : '';

        let refreshHtml = `
            <button class="toolbar-action-btn" title="Refresh Table" id="refreshBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 4v6h-6"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
            </button>
        `;

        let filtersHtml = this.showFilters ? `
            <button class="toolbar-action-btn" id="btnToolbarFilter" title="Filter Records">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                </svg>
            </button>
        ` : '';

        let importHtml = this.showImport ? `
            <div class="toolbar-v-divider"></div>
            <button class="toolbar-action-btn" id="btnToolbarImport" title="Import CSV" style="font-size:12px; gap:4px; padding:0 10px;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                Import
            </button>
        ` : '';

        this.innerHTML = `
            <div class="table-toolbar">
                <div class="toolbar-left">
                    <div class="search-box">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="tableSearchInput" class="toolbar-search-input" placeholder="${this.searchPlaceholder}">
                    </div>
                </div>
                <div class="toolbar-right">
                    <div class="toolbar-count-badge" id="toolbarCountBadge" title="Total Records">${this._count}</div>
                    <div class="toolbar-v-divider"></div>
                    ${exportHtml}
                    <div class="toolbar-v-divider"></div>
                    ${refreshHtml}
                    <div class="toolbar-v-divider"></div>
                    ${filtersHtml}
                    ${importHtml}
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
                const query = e.target.value.trim().toLowerCase();
                timeout = setTimeout(() => {
                    this.dispatchEvent(new CustomEvent('table-search', {
                        detail: { query: query },
                        bubbles: true
                    }));

                    // Real-time client row filtering across attached table
                    const table = this.closest('.card')?.querySelector('app-table, table') || document.querySelector('app-table, table');
                    if (table) {
                        const rows = table.querySelectorAll('tbody tr:not(.empty-row)');
                        let visibleCount = 0;
                        rows.forEach(r => {
                            const text = r.textContent.toLowerCase();
                            if (!query || text.includes(query)) {
                                r.style.display = '';
                                visibleCount++;
                            } else {
                                r.style.display = 'none';
                            }
                        });
                        this.setCount(visibleCount);
                    }
                }, 200);
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
