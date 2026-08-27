// Workspace Loading State
class AppWorkspaceLoading extends HTMLElement {
    constructor() { super(); }
    connectedCallback() {
        this.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--sp-12); color: var(--c-text-muted);">
                <div class="spinner" style="width: 40px; height: 40px; border: 3px solid var(--c-border-light); border-top-color: var(--c-primary); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: var(--sp-4);"></div>
                <div style="font-weight: 500;">Loading data...</div>
            </div>
            <style>
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
        `;
    }
}
customElements.define('app-workspace-loading', AppWorkspaceLoading);

// Workspace Empty State
class AppWorkspaceEmpty extends HTMLElement {
    constructor() { super(); }
    connectedCallback() {
        const title = this.getAttribute('title') || 'No Data Found';
        const message = this.getAttribute('message') || 'There is nothing to display here right now.';
        const action = this.getAttribute('action') || '';
        
        let actionHtml = action ? `<button class="btn btn-primary" style="margin-top: var(--sp-4);">${action}</button>` : '';
        
        this.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--sp-12); text-align: center; border: 1px dashed var(--c-border-light); border-radius: var(--radius-lg); background: var(--c-bg-surface);">
                <div style="color: var(--c-text-muted); margin-bottom: var(--sp-4);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>
                </div>
                <h3 style="margin-bottom: var(--sp-2);">${title}</h3>
                <p style="color: var(--c-text-muted); max-width: 400px;">${message}</p>
                ${actionHtml}
            </div>
        `;
    }
}
customElements.define('app-workspace-empty', AppWorkspaceEmpty);

// Workspace Error State
class AppWorkspaceError extends HTMLElement {
    constructor() { super(); }
    connectedCallback() {
        const title = this.getAttribute('title') || 'Access Denied';
        const message = this.getAttribute('message') || 'You do not have permission to view this workspace.';
        
        this.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: var(--sp-12); text-align: center; border: 1px solid var(--c-danger-border); border-radius: var(--radius-lg); background: #FFF5F5;">
                <div style="color: var(--c-danger-text); margin-bottom: var(--sp-4);">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                </div>
                <h3 style="margin-bottom: var(--sp-2); color: var(--c-danger-text);">${title}</h3>
                <p style="color: var(--c-text-muted); max-width: 400px;">${message}</p>
                <button class="btn btn-outline" style="margin-top: var(--sp-4);" onclick="window.history.back()">Go Back</button>
            </div>
        `;
    }
}
customElements.define('app-workspace-error', AppWorkspaceError);
