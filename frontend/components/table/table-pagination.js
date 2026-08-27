class AppTablePagination extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    static get observedAttributes() {
        return ['current-page', 'total-pages', 'total-records', 'page-size'];
    }

    attributeChangedCallback() {
        this.render();
        this.setupEventListeners();
    }

    get currentPage() {
        return parseInt(this.getAttribute('current-page')) || 1;
    }

    get totalPages() {
        return Math.max(1, parseInt(this.getAttribute('total-pages')) || 1);
    }

    get totalRecords() {
        return parseInt(this.getAttribute('total-records')) || 0;
    }

    get pageSize() {
        return parseInt(this.getAttribute('page-size')) || 10;
    }

    render() {
        const startRecord = this.totalRecords > 0 ? (this.currentPage - 1) * this.pageSize + 1 : 0;
        const endRecord = Math.min(this.totalRecords, this.currentPage * this.pageSize);

        this.innerHTML = `
            <div class="table-pagination" style="display:flex; justify-content:space-between; align-items:center; padding: var(--sp-4) var(--sp-6); background: var(--c-bg-surface); border-top: 1px solid var(--c-border-light);">
                <div class="pagination-info" style="color: var(--c-text-muted); font-size: var(--text-sm);">
                    Showing <strong>${startRecord}</strong> to <strong>${endRecord}</strong> of <strong>${this.totalRecords}</strong> entries
                </div>
                
                <div style="display:flex; align-items:center; gap: var(--sp-4);">
                    <div class="rows-per-page" style="display:flex; align-items:center; gap: var(--sp-2); font-size: var(--text-sm); color: var(--c-text-muted);">
                        Rows per page:
                        <select class="form-select" style="padding: 4px 12px; height: 32px; border-radius: 4px; border: 1px solid #cbd5e1;" id="rowsPerPage">
                            <option value="10" ${this.pageSize === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${this.pageSize === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${this.pageSize === 50 ? 'selected' : ''}>50</option>
                        </select>
                    </div>

                    <div class="pagination-controls" style="display:flex; align-items:center; gap: 6px;">
                        <button class="btn btn-outline btn-sm" ${this.currentPage <= 1 ? 'disabled' : ''} id="prevPage" style="padding: 4px 8px;">
                            ‹ Prev
                        </button>
                        <span style="font-size: var(--text-sm); font-weight: 600; color: #475569; padding: 0 4px;">
                            Page ${this.currentPage} of ${this.totalPages}
                        </span>
                        <button class="btn btn-outline btn-sm" ${this.currentPage >= this.totalPages ? 'disabled' : ''} id="nextPage" style="padding: 4px 8px;">
                            Next ›
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    setupEventListeners() {
        const prevBtn = this.querySelector('#prevPage');
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.dispatchEvent(new CustomEvent('page-change', {
                        detail: { page: this.currentPage - 1 },
                        bubbles: true
                    }));
                }
            });
        }

        const nextBtn = this.querySelector('#nextPage');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (this.currentPage < this.totalPages) {
                    this.dispatchEvent(new CustomEvent('page-change', {
                        detail: { page: this.currentPage + 1 },
                        bubbles: true
                    }));
                }
            });
        }

        const rowsSelect = this.querySelector('#rowsPerPage');
        if (rowsSelect) {
            rowsSelect.addEventListener('change', (e) => {
                this.dispatchEvent(new CustomEvent('page-size-change', {
                    detail: { size: parseInt(e.target.value) },
                    bubbles: true
                }));
            });
        }
    }
}

customElements.define('app-table-pagination', AppTablePagination);
