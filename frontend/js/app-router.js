/**
 * SHULE CAFE – Instant SPA Navigation Router
 * Eliminates page blinking and white refreshes on sidebar & link navigation.
 */
class AppRouter {
    constructor() {
        this.cache = new Map();
        this.isNavigating = false;
        this.init();
    }

    init() {
        document.addEventListener('click', (e) => this.handleLinkClick(e));
        window.addEventListener('popstate', (e) => this.handlePopState(e));
    }

    async handleLinkClick(e) {
        const link = e.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }

        // Check if external link or download
        if (link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        const targetUrl = new URL(link.href, window.location.origin);
        if (targetUrl.origin !== window.location.origin) {
            return; // External origin
        }

        // Ignore same page hash navigation
        if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) {
            return;
        }

        e.preventDefault();
        this.navigateTo(targetUrl.href);
    }

    async handlePopState(e) {
        this.navigateTo(window.location.href, false);
    }

    async navigateTo(urlStr, pushState = true) {
        if (this.isNavigating) return;
        this.isNavigating = true;

        const targetUrl = new URL(urlStr, window.location.origin);
        
        // Show subtle top loading progress line
        this.showTopLoader();

        try {
            let htmlText = this.cache.get(targetUrl.href);
            if (!htmlText) {
                const res = await fetch(targetUrl.href, { credentials: 'include' });
                if (!res.ok) {
                    window.location.href = targetUrl.href; // Fallback to traditional load
                    return;
                }
                htmlText = await res.text();
                // Cache up to 20 pages in memory for instant loads
                if (this.cache.size > 20) this.cache.clear();
                this.cache.set(targetUrl.href, htmlText);
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(htmlText, 'text/html');

            const currentContent = document.querySelector('.app-content') || document.querySelector('.app-main');
            const newContent = doc.querySelector('.app-content') || doc.querySelector('.app-main');

            if (!currentContent || !newContent) {
                window.location.href = targetUrl.href; // Fallback
                return;
            }

            // Smooth transition: fade out slightly
            currentContent.style.transition = 'opacity 0.1s ease';
            currentContent.style.opacity = '0.3';

            setTimeout(() => {
                // Update URL in browser location bar BEFORE executing page scripts so window.location is correct
                if (pushState) {
                    history.pushState({ url: targetUrl.href }, '', targetUrl.href);
                }

                // Update page title
                document.title = doc.title || document.title;

                // Transfer page-specific <style> blocks from target head to current head
                const targetStyles = doc.querySelectorAll('head style');
                targetStyles.forEach(style => {
                    const styleTag = document.createElement('style');
                    styleTag.textContent = style.textContent;
                    document.head.appendChild(styleTag);
                });

                // Replace content
                currentContent.innerHTML = newContent.innerHTML;

                // Execute scripts safely without allowing document.write to wipe the DOM
                const originalWrite = document.write;
                document.write = function() {}; // Prevent document.write from wiping the document

                try {
                    const scripts = doc.querySelectorAll('script');
                    scripts.forEach(oldScript => {
                        if (oldScript.src && oldScript.src.includes('global-layout.js')) return; // skip layout
                        const newScript = document.createElement('script');
                        Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                        let scriptCode = oldScript.innerHTML.replace(/document\.write\s*\([^)]*\);?/gi, '');
                        newScript.appendChild(document.createTextNode(scriptCode));
                        document.body.appendChild(newScript);
                        document.body.removeChild(newScript); // Clean up tag after execution
                    });
                } catch(e) {
                    console.error("Script re-execution error in router:", e);
                } finally {
                    document.write = originalWrite;
                }

                // Fire DOMContentLoaded substitute event for newly loaded page logic
                document.dispatchEvent(new Event('DOMContentLoaded'));

                // Fade back in
                currentContent.style.opacity = '1';

                // Update active link on sidebar
                const sidebar = document.querySelector('app-sidebar');
                if (sidebar) {
                    sidebar.setAttribute('active-path', targetUrl.pathname);
                }

                // Scroll to top of workspace
                window.scrollTo({ top: 0, behavior: 'instant' });

                this.hideTopLoader();
                this.isNavigating = false;
            }, 80);

        } catch (err) {
            console.error("Instant Router navigation error:", err);
            window.location.href = targetUrl.href; // Fallback
            this.hideTopLoader();
            this.isNavigating = false;
        }
    }

    showTopLoader() {
        let loader = document.getElementById('appTopProgressBar');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'appTopProgressBar';
            loader.style.cssText = 'position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,#10b981,#3b82f6);z-index:99999;transition:width 0.2s ease;width:0%;';
            document.body.appendChild(loader);
        }
        loader.style.width = '70%';
        loader.style.opacity = '1';
    }

    hideTopLoader() {
        const loader = document.getElementById('appTopProgressBar');
        if (loader) {
            loader.style.width = '100%';
            setTimeout(() => {
                loader.style.opacity = '0';
                setTimeout(() => { loader.style.width = '0%'; }, 200);
            }, 150);
        }
    }
}

export const router = new AppRouter();
