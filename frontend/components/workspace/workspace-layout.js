class AppWorkspaceLayout extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    get backUrl() {
        return this.getAttribute('back-url') || '#';
    }
    
    get backLabel() {
        return this.getAttribute('back-label') || 'Back';
    }

    get hideBack() {
        return this.hasAttribute('hide-back');
    }

    render() {
        if (this.querySelector('.workspace-wrapper')) return;
        const originalContent = this.innerHTML;
        const backHtml = this.hideBack ? '' : `
            <div class="workspace-top-bar" style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                <a href="${this.backUrl}" onclick="if(this.getAttribute('href')==='#'){window.history.back(); return false;}" class="btn btn-outline btn-sm" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; font-weight: 700; white-space: nowrap; height: 28px; padding: 2px 10px; font-size: 12px; border-radius: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                    ${this.backLabel}
                </a>
            </div>
        `;

        this.innerHTML = `
            <div class="workspace-wrapper" style="width: 100%; max-width: 100%; margin: 0; padding: 0 24px 24px 24px; box-sizing: border-box;">
                ${backHtml}
                <div class="workspace-container" style="width: 100%;">
                    ${originalContent}
                </div>
            </div>
        `;
    }
}

customElements.define('app-workspace-layout', AppWorkspaceLayout);
