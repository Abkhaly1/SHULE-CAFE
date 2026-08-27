class AppWorkspaceSummary extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    render() {
        if (this.querySelector('.workspace-summary')) return;
        const originalContent = this.innerHTML;
        this.innerHTML = `
            <div class="workspace-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--sp-4); margin-bottom: 4px;">
                ${originalContent}
            </div>
        `;
    }
}

customElements.define('app-workspace-summary', AppWorkspaceSummary);

// Helper for summary cards
class AppWorkspaceSummaryCard extends HTMLElement {
    static get observedAttributes() {
        return ['label', 'title', 'value', 'icon'];
    }

    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    attributeChangedCallback() {
        this.render();
    }

    render() {
        const title = this.getAttribute('label') || this.getAttribute('title') || 'Stat';
        const value = this.getAttribute('value') || '0';
        const icon = this.getAttribute('icon') || '';
        
        let iconHtml = icon ? `<div style="color: var(--c-primary); margin-bottom: 8px;"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="${icon}"/></svg></div>` : '';
        
        this.innerHTML = `
            <div class="card" style="padding: var(--sp-4); text-align: left;">
                ${iconHtml}
                <div style="color: var(--c-text-muted); font-size: var(--text-sm); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">${title}</div>
                <div style="font-size: 24px; font-weight: 600; margin-top: 4px;">${value}</div>
            </div>
        `;
    }
}

customElements.define('app-workspace-summary-card', AppWorkspaceSummaryCard);
