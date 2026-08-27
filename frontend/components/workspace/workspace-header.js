class AppWorkspaceHeader extends HTMLElement {
    static get observedAttributes() {
        return ['title', 'subtitle', 'status', 'status-color', 'avatar-text', 'initials', 'needs-config'];
    }

    constructor() {
        super();
        this._metaContent = '';
        this._rendered = false;
    }

    connectedCallback() {
        if (!this._rendered) {
            this._metaContent = this.innerHTML;
            this.render();
            this._rendered = true;
        }
    }

    attributeChangedCallback() {
        this.updateContent();
    }

    get avatarText() {
        return this.getAttribute('initials') || this.getAttribute('avatar-text') || 'S';
    }

    get title() {
        return this.getAttribute('title') || 'Entity Name';
    }

    get subtitle() {
        return this.getAttribute('subtitle') || '';
    }

    get status() {
        return this.getAttribute('status') || '';
    }
    
    get statusColor() {
        return this.getAttribute('status-color') || 'success';
    }

    get needsConfig() {
        return this.getAttribute('needs-config') === 'true' || this.hasAttribute('needs-config');
    }

    render() {
        let badgeHtml = this.status ? `<span class="badge badge-${this.statusColor}" id="headerStatusBadge" style="margin-left: 8px; font-size: 10px; padding: 2px 6px; font-weight: 700;">${this.status}</span>` : '';
        let warnIconHtml = this.needsConfig ? `<span class="setup-warning-icon" id="headerWarnIcon" title="Setup Required: Not configured yet">!</span>` : `<span class="setup-warning-icon" id="headerWarnIcon" style="display:none;" title="Setup Required: Not configured yet">!</span>`;
        let subtitleHtml = this.subtitle ? `<div id="headerSubtitle" style="color: var(--c-text-muted); font-size: 12px; margin-top: 2px; font-weight: 600;">${this.subtitle}</div>` : '';
        const metaContent = this._metaContent || '';

        this.innerHTML = `
            <div class="workspace-header" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background: var(--c-bg-surface); border: 1px solid var(--c-border-light); border-radius: 12px; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="workspace-avatar" id="headerAvatar" style="width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: bold; flex-shrink: 0; box-shadow: 0 2px 6px rgba(2,132,199,0.25);">
                        ${this.avatarText}
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 17px; font-weight: 800; display: flex; align-items: center; color: var(--c-text-primary);" id="headerTitleContainer">
                            <span id="headerTitleText">${this.title}</span>
                            ${warnIconHtml}
                            ${badgeHtml}
                        </h2>
                        ${subtitleHtml}
                    </div>
                </div>
                ${metaContent ? `<div class="workspace-meta" style="display: flex; gap: 12px; text-align: right;">${metaContent}</div>` : ''}
            </div>
        `;
    }

    updateContent() {
        const titleEl = this.querySelector('#headerTitleText');
        const subtitleEl = this.querySelector('#headerSubtitle');
        const avatarEl = this.querySelector('#headerAvatar');
        const badgeEl = this.querySelector('#headerStatusBadge');
        const warnEl = this.querySelector('#headerWarnIcon');

        if (titleEl) titleEl.textContent = this.title;
        if (subtitleEl) subtitleEl.textContent = this.subtitle;
        if (avatarEl) avatarEl.textContent = this.avatarText;
        if (warnEl) warnEl.style.display = this.needsConfig ? 'inline-flex' : 'none';
        if (badgeEl) {
            badgeEl.textContent = this.status;
            badgeEl.className = `badge badge-${this.statusColor}`;
        }
    }
}

customElements.define('app-workspace-header', AppWorkspaceHeader);
