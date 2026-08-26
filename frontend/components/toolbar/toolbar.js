class AppToolbar extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    get searchPlaceholder() {
        return this.getAttribute('search-placeholder') || 'Search...';
    }

    get showFilters() {
        return this.getAttribute('show-filters') === 'true';
    }

    get showExport() {
        return this.getAttribute('show-export') === 'true';
    }

    render() {
        let filtersHtml = this.showFilters ? `
            <button class="btn btn-outline btn-sm" title="Filters">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;"><path d="M10 18h4v-2h-4v2zM3 6v2h18V6H3zm3 7h12v-2H6v2z"/></svg>
                Filter
            </button>
        ` : '';

        let exportHtml = this.showExport ? `
            <button class="btn btn-outline btn-sm" title="Export">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 4px;"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
                Export
            </button>
        ` : '';

        this.innerHTML = `
            <div class="toolbar" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: var(--sp-4); padding: var(--sp-3); background: var(--c-bg-surface); border: 1px solid var(--c-border-light); border-radius: var(--radius-md);">
                <div class="toolbar-left" style="display:flex; gap: var(--sp-2);">
                    <div class="search-box" style="position:relative;">
                        <svg style="position:absolute; left:10px; top:10px; color:var(--c-text-muted);" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                        <input type="text" class="form-control" placeholder="${this.searchPlaceholder}" style="padding-left: 32px; height: 36px; min-width: 250px;">
                    </div>
                </div>
                <div class="toolbar-right" style="display:flex; gap: var(--sp-2);">
                    ${filtersHtml}
                    ${exportHtml}
                </div>
            </div>
        `;
    }
}

customElements.define('app-toolbar', AppToolbar);
