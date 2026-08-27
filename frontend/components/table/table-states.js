// Table Empty State
class AppTableEmpty extends HTMLElement {
    constructor() { super(); }
    connectedCallback() {
        const title = this.getAttribute('title') || 'No Records Found';
        const message = this.getAttribute('message') || 'We couldn\'t find any records matching your criteria.';
        
        this.innerHTML = `
            <div style="padding: var(--sp-8) var(--sp-4); text-align: center; background: var(--c-bg-surface); border-radius: var(--radius-md);">
                <div style="color: #94a3b8; margin-bottom: var(--sp-3); display: flex; justify-content: center;">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6z"/><path d="M5.45 5.11L2 12v0h20v0l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                </div>
                <h4 style="margin: 0 0 var(--sp-1) 0; font-weight: 600; color: var(--c-text-primary); font-size: var(--text-md);">${title}</h4>
                <p style="color: var(--c-text-muted); max-width: 360px; margin: 0 auto; font-size: var(--text-sm); line-height: 1.5;">${message}</p>
            </div>
        `;
    }
}
customElements.define('app-table-empty', AppTableEmpty);

// Table Skeleton Loader
class AppTableSkeleton extends HTMLElement {
    constructor() { 
        super(); 
        this._timeoutId = null;
    }
    connectedCallback() {
        const rows = parseInt(this.getAttribute('rows')) || 5;
        const cols = parseInt(this.getAttribute('cols')) || 5;

        let rowsHtml = '';
        for(let i=0; i<rows; i++) {
            let colsHtml = '';
            for(let j=0; j<cols; j++) {
                const w = Math.floor(Math.random() * 40 + 50);
                colsHtml += `<td style="padding: 12px; border-bottom: 1px solid var(--c-border-light, #f1f5f9);"><div class="skeleton" style="height: 16px; width: ${w}%; border-radius: 4px;"></div></td>`;
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

        // Safety fallback: if skeleton is not cleared within 2.5 seconds, auto-render empty state
        this._timeoutId = setTimeout(() => {
            if (this.isConnected) {
                const parent = this.parentElement;
                if (parent && parent.contains(this)) {
                    this.outerHTML = '<app-table-empty title="No Records Available" message="No data records found in this view. Click the action button above to add new records."></app-table-empty>';
                }
            }
        }, 2500);
    }

    disconnectedCallback() {
        if (this._timeoutId) {
            clearTimeout(this._timeoutId);
            this._timeoutId = null;
        }
    }
}
customElements.define('app-table-skeleton', AppTableSkeleton);
