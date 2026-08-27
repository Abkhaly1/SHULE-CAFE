class AppStatusBadge extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    get status() {
        return (this.getAttribute('status') || '').toLowerCase();
    }

    render() {
        let text = this.getAttribute('status') || 'Unknown';
        let colorClass = 'neutral';

        // Map status strings to standardized colors
        switch(this.status) {
            case 'active':
            case 'completed':
            case 'graduated':
                colorClass = 'success';
                break;
            case 'inactive':
            case 'pending':
                colorClass = 'warning';
                break;
            case 'suspended':
            case 'archived':
            case 'transferred':
                colorClass = 'danger';
                break;
            default:
                colorClass = 'neutral';
                break;
        }

        // We use our existing design-system.css badge classes (e.g. badge-success)
        this.innerHTML = `<span class="badge badge-${colorClass}">${text}</span>`;
    }
}

customElements.define('app-status-badge', AppStatusBadge);
