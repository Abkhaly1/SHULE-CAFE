class AppFooter extends HTMLElement {
    constructor() {
        super();
    }

    connectedCallback() {
        this.render();
    }

    render() {
        this.innerHTML = `
            <footer class="app-footer">
                <div>Thank you for creating with <a href="#">Shule Cafe</a>.</div>
                <div>Version 2.0.0 &copy; ${new Date().getFullYear()}</div>
            </footer>
        `;
    }
}

customElements.define('app-footer', AppFooter);
