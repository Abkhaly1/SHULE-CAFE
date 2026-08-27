class AppPageHeader extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
        this.setupEventListeners();
    }

    get title() {
        return this.getAttribute('title') || 'Page Title';
    }

    get subtitle() {
        return this.getAttribute('subtitle') || '';
    }

    get primaryAction() {
        return this.getAttribute('primary-action') || '';
    }

    get primaryUrl() {
        return this.getAttribute('primary-url') || '';
    }

    get secondaryAction() {
        return this.getAttribute('secondary-action') || '';
    }

    get secondaryUrl() {
        return this.getAttribute('secondary-url') || '';
    }

    get needsConfig() {
        return this.getAttribute('needs-config') === 'true' || this.hasAttribute('needs-config');
    }

    render() {
        let subtitleHtml = this.subtitle ? `<p class="text-muted" style="margin-top:4px;">${this.subtitle}</p>` : '';
        let warnIconHtml = this.needsConfig ? `<span class="setup-warning-icon" title="Setup Required: Not configured yet">!</span>` : '';
        
        let primaryBtnHtml = '';
        if (this.primaryAction) {
            if (this.primaryUrl) {
                primaryBtnHtml = `<a href="${this.primaryUrl}" class="btn btn-primary" id="btnPageHeaderPrimary" style="text-decoration:none; display:inline-flex; align-items:center;">${this.primaryAction}</a>`;
            } else {
                primaryBtnHtml = `<button type="button" class="btn btn-primary" id="btnPageHeaderPrimary">${this.primaryAction}</button>`;
            }
        }

        let secondaryBtnHtml = '';
        if (this.secondaryAction) {
            if (this.secondaryUrl) {
                secondaryBtnHtml = `<a href="${this.secondaryUrl}" class="btn btn-outline" id="btnPageHeaderSecondary" style="text-decoration:none; display:inline-flex; align-items:center;">${this.secondaryAction}</a>`;
            } else {
                secondaryBtnHtml = `<button type="button" class="btn btn-outline" id="btnPageHeaderSecondary">${this.secondaryAction}</button>`;
            }
        }

        let actionsHtml = '';
        if (primaryBtnHtml || secondaryBtnHtml) {
            actionsHtml = `
                <div class="page-header-right" style="display:flex; gap:var(--sp-2);">
                    ${secondaryBtnHtml}
                    ${primaryBtnHtml}
                </div>
            `;
        }

        this.innerHTML = `
            <div class="page-header-wrapper" style="margin-top: var(--sp-2); display:flex; justify-content:space-between; align-items:center;">
                <div class="page-header-left">
                    <h1 style="margin:0; display:flex; align-items:center;"><span>${this.title}</span>${warnIconHtml}</h1>
                    ${subtitleHtml}
                </div>
                ${actionsHtml}
            </div>
        `;
    }

    setupEventListeners() {
        const primaryBtn = this.querySelector('#btnPageHeaderPrimary');
        if (primaryBtn) {
            primaryBtn.addEventListener('click', (e) => {
                this.dispatchEvent(new CustomEvent('page-header-primary-click', { bubbles: true }));
                if (this.primaryUrl && primaryBtn.tagName.toLowerCase() === 'button') {
                    window.location.href = this.primaryUrl;
                }
            });
        }

        const secondaryBtn = this.querySelector('#btnPageHeaderSecondary');
        if (secondaryBtn) {
            secondaryBtn.addEventListener('click', (e) => {
                this.dispatchEvent(new CustomEvent('page-header-secondary-click', { bubbles: true }));
                if (this.secondaryUrl && secondaryBtn.tagName.toLowerCase() === 'button') {
                    window.location.href = this.secondaryUrl;
                }
            });
        }
    }
}

customElements.define('app-page-header', AppPageHeader);
