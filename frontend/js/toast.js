/**
 * SHULE CAFE – Global Floating Toast Notification System
 * Displays silent, non-intrusive floating pop-up notifications (top-right corner)
 * for success and alert messages across all portals.
 */

export function showToast(message, type = 'success', duration = 3500) {
    let container = document.getElementById('shuleToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'shuleToastContainer';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:999999;display:flex;flex-direction:column;gap:10px;pointer-events:none;max-width:380px;width:calc(100% - 40px);';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `shule-toast shule-toast-${type}`;

    const isSuccess = type === 'success';
    const isError = type === 'error' || type === 'danger';
    
    const bgColor = isSuccess ? '#f0fdf4' : (isError ? '#fef2f2' : '#eff6ff');
    const borderColor = isSuccess ? '#86efac' : (isError ? '#fca5a5' : '#93c5fd');
    const textColor = isSuccess ? '#15803d' : (isError ? '#b91c1c' : '#1d4ed8');
    const icon = isSuccess ? '✅' : (isError ? '⚠️' : 'ℹ️');

    toast.style.cssText = `
        background: ${bgColor};
        border: 1px solid ${borderColor};
        color: ${textColor};
        padding: 10px 14px;
        border-radius: 8px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 12px;
        font-weight: 700;
        pointer-events: auto;
        transform: translateX(120%);
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        opacity: 0;
    `;

    toast.innerHTML = `
        <span style="font-size: 16px; flex-shrink: 0;">${icon}</span>
        <div style="flex: 1; line-height: 1.4;">${message}</div>
        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:${textColor};opacity:0.6;font-size:14px;padding:0;margin-left:4px;">✕</button>
    `;

    container.appendChild(toast);

    // Trigger slide-in animation
    requestAnimationFrame(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    });

    // Auto-dismiss timer
    if (duration > 0) {
        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }
}

// Register globally on window for easy call anywhere
window.showToast = showToast;
