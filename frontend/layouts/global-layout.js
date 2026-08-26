// Import all layout web components
import '../components/navigation/sidebar.js';
import '../components/navigation/topbar.js';
import '../components/navigation/breadcrumb.js';
import '../components/header/page-header.js';
import '../components/toolbar/toolbar.js';
import '../components/footer/footer.js';

// The purpose of this file is to act as the single entry point for the global layout system.
// By importing this file in a <script type="module"> tag, all the layout components 
// (app-sidebar, app-topbar, app-breadcrumb, app-page-header, app-toolbar, app-footer) 
// are registered with the browser and ready to be used in HTML.

// Import workspace components
import '../components/workspace/workspace-layout.js';
import '../components/workspace/workspace-header.js';
import '../components/workspace/workspace-summary.js';
import '../components/workspace/workspace-tabs.js';
import '../components/workspace/workspace-toolbar.js';
import '../components/workspace/workspace-content.js';
import '../components/workspace/workspace-states.js';

// Import table components
import '../components/table/table-toolbar.js';
import '../components/table/status-badge.js';
import '../components/table/table-pagination.js';
import '../components/table/table-states.js';
import '../components/table/table-wrapper.js';
import '../components/table/table-import-modal.js?v=20260809';

// Import Instant SPA Router for zero-blink page navigation
import '../js/app-router.js';

// Import Global Toast Notification System
import '../js/toast.js';

// Automatically populate current year for elements with class 'current-year'
document.addEventListener('DOMContentLoaded', () => {
    const currentYear = new Date().getFullYear();
    document.querySelectorAll('.current-year').forEach(el => {
        if (!el.textContent) el.textContent = currentYear;
    });
});
