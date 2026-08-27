class AppTable extends HTMLElement {
    static get observedAttributes() {
        return ['columns', 'data', 'actions', 'entity-url'];
    }

    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue) {
            this.render();
            this.setupEventListeners();
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

    get actions() {
        const act = this.getAttribute('actions');
        if (act === null) return ['view', 'edit', 'delete'];
        if (act === 'none' || act === '') return [];
        return act.split(',').map(s => s.trim().toLowerCase());
    }

    render() {
        const cols = this.columns;
        const rows = this.data;
        const enabledActions = this.actions;
        const hasActions = enabledActions.length > 0;
        
        if (cols.length === 0) return;

        let thHtml = '';
        cols.forEach(col => {
            thHtml += `<th style="text-align: left; padding: var(--sp-3); border-bottom: 2px solid var(--c-border-light); font-weight: 600; color: var(--c-text-muted); font-size: var(--text-sm);">${col.label}</th>`;
        });
        
        if (hasActions) {
            thHtml += `<th style="text-align: right; padding: var(--sp-3); border-bottom: 2px solid var(--c-border-light); font-weight: 600; color: var(--c-text-muted); font-size: var(--text-sm); min-width: 120px;">Actions</th>`;
        }

        let tbodyHtml = '';
        if (rows.length === 0) {
            const colspan = hasActions ? cols.length + 1 : cols.length;
            tbodyHtml = `<tr><td colspan="${colspan}" style="padding: 0;"><app-table-empty></app-table-empty></td></tr>`;
        } else {
            rows.forEach((row, index) => {
                let trHtml = '';
                cols.forEach(col => {
                    let cellData = row[col.key];
                    if (col.type === 'status') {
                        cellData = `<app-status-badge status="${cellData}"></app-status-badge>`;
                    } else if (col.type === 'avatar') {
                        cellData = `
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div style="width:32px; height:32px; border-radius:50%; background:#e2e8f0; color:#334155; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0;">${cellData.initials || ''}</div>
                                <span style="font-weight: 500; color: var(--c-text-primary);">${cellData.name}</span>
                            </div>
                        `;
                    } else if (col.type === 'badge') {
                        cellData = `<span class="badge badge-info">${cellData}</span>`;
                    }
                    trHtml += `<td style="padding: var(--sp-3); border-bottom: 1px solid var(--c-border-light);">${cellData !== undefined && cellData !== null ? cellData : ''}</td>`;
                });
                
                if (hasActions) {
                    const isLink = this.entityUrl && this.entityUrl !== '#';
                    const sep = (this.entityUrl && this.entityUrl.includes('?')) ? '&' : '?';
                    
                    const viewBtn = enabledActions.includes('view') ? (isLink 
                        ? `<a href="${this.entityUrl}${sep}id=${row.id}" class="btn btn-outline btn-sm" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px; text-decoration:none;" title="View Page">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                View
                           </a>`
                        : `<button type="button" class="btn btn-outline btn-sm app-table-view-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px;" title="View Details">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                View
                           </button>`) : '';

                    const editBtn = enabledActions.includes('edit') ? `<button type="button" class="btn btn-outline btn-sm app-table-edit-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px;" title="Edit Record">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                            Edit
                       </button>` : '';

                    const deleteBtn = enabledActions.includes('delete') ? `<button type="button" class="btn btn-outline btn-sm app-table-delete-btn" data-row-index="${index}" data-id="${row.id}" style="display:inline-flex; align-items:center; gap:4px; color: var(--c-danger); border-color: var(--c-danger);" title="Delete Record">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                            Delete
                       </button>` : '';

                    trHtml += `
                        <td style="padding: var(--sp-3); border-bottom: 1px solid var(--c-border-light); text-align: right; white-space: nowrap;">
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
            <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                <table class="table" style="width: 100%; border-collapse: collapse; background: var(--c-bg-surface);">
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
        
        const viewBtns = this.querySelectorAll('.app-table-view-btn');
        viewBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                document.dispatchEvent(new CustomEvent('table-view-action', {
                    detail: { id: rowData.id, row: rowData }
                }));
            });
        });

        const editBtns = this.querySelectorAll('.app-table-edit-btn');
        editBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                document.dispatchEvent(new CustomEvent('table-edit-action', {
                    detail: { id: rowData.id, row: rowData }
                }));
            });
        });

        const deleteBtns = this.querySelectorAll('.app-table-delete-btn');
        deleteBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.currentTarget.getAttribute('data-row-index');
                const rowData = rows[idx] || {};
                if (confirm(`Are you sure you want to delete this record (ID: ${rowData.id})?`)) {
                    document.dispatchEvent(new CustomEvent('table-delete-action', {
                        detail: { id: rowData.id, row: rowData }
                    }));
                }
            });
        });
    }
}

customElements.define('app-table', AppTable);
