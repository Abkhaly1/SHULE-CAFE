class AppTable extends HTMLElement {
    static get observedAttributes() {
        return ['columns', 'data', 'actions', 'entity-url', 'selectable'];
    }

    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
        this.notifyToolbar();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.render();
            this.setupEventListeners();
            this.notifyToolbar();
        }
    }

    get columns() {
        try {
            return JSON.parse(this.getAttribute('columns')) || [];
        } catch(e) {
            return [];
        }
    }

    get data() {
        try {
            return JSON.parse(this.getAttribute('data')) || [];
        } catch(e) {
            return [];
        }
    }
    
    get entityUrl() {
        return this.getAttribute('entity-url') || '#';
    }

    get isSelectable() {
        return this.getAttribute('selectable') !== 'false';
    }

    get actions() {
        const act = this.getAttribute('actions');
        if (act === null) return ['view', 'edit', 'delete'];
        if (act === 'none' || act === '') return [];
        return act.split(',').map(s => s.trim().toLowerCase());
    }

    notifyToolbar() {
        const rows = this.data;
        const toolbar = this.closest('.card')?.querySelector('app-table-toolbar') || document.querySelector('app-table-toolbar');
        if (toolbar && typeof toolbar.setCount === 'function') {
            toolbar.setCount(rows.length);
        }
    }

    render() {
        const cols = this.columns;
        const rows = this.data;
        const enabledActions = this.actions;
        const hasActions = enabledActions.length > 0;
        const selectable = this.isSelectable;
        
        if (cols.length === 0) return;

        let thHtml = '';
        if (selectable) {
            thHtml += `
                <th class="th-checkbox">
                    <input type="checkbox" class="table-checkbox table-select-all" title="Select All">
                </th>
            `;
        }

        cols.forEach(col => {
            thHtml += `<th>${col.label}</th>`;
        });
        
        if (hasActions) {
            thHtml += `<th style="text-align: right; min-width: 120px;">Actions</th>`;
        }

        let tbodyHtml = '';
        if (rows.length === 0) {
            const colspan = cols.length + (selectable ? 1 : 0) + (hasActions ? 1 : 0);
            tbodyHtml = `<tr class="empty-row"><td colspan="${colspan}" style="padding: 0; text-align: center;"><app-table-empty></app-table-empty></td></tr>`;
        } else {
            rows.forEach((row, index) => {
                let trHtml = '';

                if (selectable) {
                    trHtml += `
                        <td class="td-checkbox">
                            <input type="checkbox" class="table-checkbox row-select-checkbox" data-id="${row.id || index}">
                        </td>
                    `;
                }

                cols.forEach(col => {
                    let cellData = row[col.key];
                    if (col.type === 'status') {
                        cellData = `<app-status-badge status="${cellData}"></app-status-badge>`;
                    } else if (col.type === 'avatar') {
                        cellData = `
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:30px; height:30px; border-radius:6px; background:#e0f2fe; color:#0369a1; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0;">${cellData.initials || ''}</div>
                                <span style="font-weight: 600; color: #1e293b;">${cellData.name}</span>
                            </div>
                        `;
                    } else if (col.type === 'badge') {
                        cellData = `<span class="badge-tag badge-tag-blue">${cellData}</span>`;
                    }
                    trHtml += `<td>${cellData !== undefined && cellData !== null ? cellData : ''}</td>`;
                });
                
                if (hasActions) {
                    const isLink = this.entityUrl && this.entityUrl !== '#';
                    const sep = (this.entityUrl && this.entityUrl.includes('?')) ? '&' : '?';
                    
                    const viewBtn = enabledActions.includes('view') ? (isLink 
                        ? `<a href="${this.entityUrl}${sep}id=${row.id}" class="btn btn-outline btn-sm" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none;" title="View Page">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                           </a>`
                        : `<button type="button" class="btn btn-outline btn-sm app-table-view-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px;" title="View Details">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                           </button>`) : '';

                    const editBtn = enabledActions.includes('edit') ? `<button type="button" class="btn btn-outline btn-sm app-table-edit-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px;" title="Edit Record">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            Edit
                       </button>` : '';

                    const deleteBtn = enabledActions.includes('delete') ? `<button type="button" class="btn btn-outline btn-sm app-table-delete-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px; color: #dc2626; border-color: #fca5a5;" title="Delete Record">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Delete
                       </button>` : '';

                    trHtml += `
                        <td style="text-align: right; white-space: nowrap;">
                            <div style="display: inline-flex; gap: 4px; justify-content: flex-end;">
                                ${viewBtn}
                                ${editBtn}
                                ${deleteBtn}
                            </div>
                        </td>
                    `;
                }

                tbodyHtml += `<tr data-id="${row.id}">${trHtml}</tr>`;
            });
        }

        this.innerHTML = `
            <div class="table-responsive app-table-container">
                <table class="table app-table-grid">
                    <thead>
                        <tr>${thHtml}</tr>
                    </thead>
                    <tbody>
                        ${tbodyHtml}
                    </tbody>
                </table>
            </div>
        `;
    }

    setupEventListeners() {
        const rows = this.data;
        
        // View Buttons
        const viewBtns = this.querySelectorAll('.app-table-view-btn');
        viewBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                this.dispatchEvent(new CustomEvent('table-view-action', {
                    detail: rowData,
                    bubbles: true
                }));
            });
        });

        // Edit Buttons
        const editBtns = this.querySelectorAll('.app-table-edit-btn');
        editBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                this.dispatchEvent(new CustomEvent('table-edit-action', {
                    detail: rowData,
                    bubbles: true
                }));
            });
        });

        // Delete Buttons
        const deleteBtns = this.querySelectorAll('.app-table-delete-btn');
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                this.dispatchEvent(new CustomEvent('table-delete-action', {
                    detail: rowData,
                    bubbles: true
                }));
            });
        });

        // Checkbox Select-All Listener
        const selectAll = this.querySelector('.table-select-all');
        const rowCheckboxes = this.querySelectorAll('.row-select-checkbox');

        if (selectAll) {
            selectAll.addEventListener('change', (e) => {
                const checked = e.target.checked;
                rowCheckboxes.forEach(cb => {
                    cb.checked = checked;
                    const tr = cb.closest('tr');
                    if (tr) {
                        if (checked) tr.classList.add('row-selected');
                        else tr.classList.remove('row-selected');
                    }
                });
            });
        }

        // Individual Row Checkbox Listener
        rowCheckboxes.forEach(cb => {
            cb.addEventListener('change', (e) => {
                const tr = cb.closest('tr');
                if (tr) {
                    if (e.target.checked) tr.classList.add('row-selected');
                    else tr.classList.remove('row-selected');
                }
                if (selectAll) {
                    const allChecked = Array.from(rowCheckboxes).every(c => c.checked);
                    const someChecked = Array.from(rowCheckboxes).some(c => c.checked);
                    selectAll.checked = allChecked;
                    selectAll.indeterminate = someChecked && !allChecked;
                }
            });
        });
    }
}

customElements.define('app-table', AppTable);
