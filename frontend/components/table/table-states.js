// Table Empty State
class AppTableEmpty extends HTMLElement {
    constructor() { super(); }
    connectedCallback() {
        const title = this.getAttribute('title') || 'No Records Found';
        const message = this.getAttribute('message') || 'No records are currently available in this view.';
        
        this.innerHTML = `
            <div style="padding: var(--sp-8) var(--sp-4); text-align: center; background: #fafbfc; border: 1px dashed #e2e8f0; border-radius: var(--radius-md); margin: 8px 0;">
                <div style="color: #94a3b8; margin-bottom: var(--sp-2); display: flex; justify-content: center;">
                    <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6z"/><path d="M5.45 5.11L2 12v0h20v0l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </div>
                <h4 style="margin: 0 0 4px 0; font-weight: 700; color: var(--c-text-primary); font-size: var(--text-md);">${title}</h4>
                <p style="color: var(--c-text-muted); max-width: 380px; margin: 0 auto; font-size: var(--text-sm); line-height: 1.5;">${message}</p>
            </div>
        `;
    }
}
if (!customElements.get('app-table-empty')) {
    customElements.define('app-table-empty', AppTableEmpty);
}

// Table Skeleton Loader
class AppTableSkeleton extends HTMLElement {
    constructor() { 
        super(); 
        this._timeoutId = null;
    }
    connectedCallback() {
        const rows = parseInt(this.getAttribute('rows')) || 4;
        const cols = parseInt(this.getAttribute('cols')) || 5;

        if (rows <= 0) {
            this.outerHTML = '<app-table-empty title="No Records Available" message="No data records found."></app-table-empty>';
            return;
        }

        let rowsHtml = '';
        for(let i = 0; i < rows; i++) {
            let colsHtml = '';
            for(let j = 0; j < cols; j++) {
                const w = Math.floor(Math.random() * 30 + 55);
                colsHtml += `<td style="padding: 12px 16px; border-bottom: 1px solid var(--c-border-light, #f1f5f9);"><div class="skeleton" style="height: 14px; width: ${w}%; border-radius: 4px; background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%); background-size: 200% 100%; animation: skeleton-loading 1.2s infinite;"></div></td>`;
            }
            rowsHtml += `<tr>${colsHtml}</tr>`;
        }

        this.innerHTML = `
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <tbody>
                    ${rowsHtml}
                </tbody>
            </table>
        `;

        // Safety fallback: auto-render empty state quickly if not replaced
        this._timeoutId = setTimeout(() => {
            if (this.isConnected) {
                const parent = this.parentElement;
                if (parent && parent.contains(this)) {
                    this.outerHTML = '<app-table-empty title="No Records Available" message="No data records found in this view. Click the action button above to add new records."></app-table-empty>';
                }
            }
        }, 800);
    }

    disconnectedCallback() {
        if (this._timeoutId) {
            clearTimeout(this._timeoutId);
            this._timeoutId = null;
        }
    }
}
if (!customElements.get('app-table-skeleton')) {
    customElements.define('app-table-skeleton', AppTableSkeleton);
}
