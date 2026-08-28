/**
 * ============================================================================
 * SHULE CAFE – Global Centered Modal Alert & Notification Engine
 * ============================================================================
 * Displays high-visibility, elegant pop-up modal screens at the EXACT CENTER
 * of the viewport for all system success, alert, warning, and failure messages.
 * Replaces browser notifications and default alert popups across all modules.
 * ============================================================================
 * English-Only Directive: 100% English titles, labels, and buttons.
 */

// Inject CSS styles for the centered modal system once
(function injectCenteredModalStyles() {
    if (typeof document === 'undefined' || document.getElementById('shuleCenteredModalStyles')) return;
    const style = document.createElement('style');
    style.id = 'shuleCenteredModalStyles';
    style.textContent = `
        .shule-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 9999999;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            padding: 16px;
        }

        .shule-modal-backdrop.shule-modal-visible {
            opacity: 1;
        }

        .shule-modal-card {
            background: #ffffff;
            width: 360px;
            max-width: 100%;
            border-radius: 12px;
            box-shadow: 0 20px 40px -8px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.06);
            padding: 22px 20px 18px 20px;
            text-align: center;
            transform: scale(0.92) translateY(8px);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: inherit;
        }

        .shule-modal-visible .shule-modal-card {
            transform: scale(1) translateY(0);
        }

        .shule-modal-icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin: 0 auto 12px auto;
        }

        .shule-modal-icon-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }

        .shule-modal-icon-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .shule-modal-icon-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }

        .shule-modal-icon-info {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .shule-modal-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px 0;
            line-height: 1.3;
        }

        .shule-modal-message {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            line-height: 1.45;
            margin: 0 0 18px 0;
            word-break: break-word;
        }

        .shule-modal-btn-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .shule-modal-btn {
            height: 32px;
            padding: 0 16px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            outline: none;
            transition: all 0.15s ease;
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .shule-modal-btn-primary-success {
            background: #059669;
            color: #ffffff;
        }
        .shule-modal-btn-primary-success:hover {
            background: #047857;
        }

        .shule-modal-btn-primary-error {
            background: #dc2626;
            color: #ffffff;
        }
        .shule-modal-btn-primary-error:hover {
            background: #b91c1c;
        }

        .shule-modal-btn-primary-warning {
            background: #d97706;
            color: #ffffff;
        }
        .shule-modal-btn-primary-warning:hover {
            background: #b45309;
        }

        .shule-modal-btn-primary-info {
            background: #2563eb;
            color: #ffffff;
        }
        .shule-modal-btn-primary-info:hover {
            background: #1d4ed8;
        }

        .shule-modal-btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .shule-modal-btn-secondary:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
    `;
    document.head.appendChild(style);
})();

/**
 * Show a Centered Modal Notification / Alert
 * @param {Object} options
 * @param {string} options.message - Message text
 * @param {'success'|'error'|'warning'|'info'} [options.type='success'] - Type of notification
 * @param {number} [options.duration=0] - Auto-close duration in ms (0 for manual OK)
 * @param {string} [options.title] - Optional custom title
 * @param {Function} [options.onClose] - Optional callback after close
 */
export function showCenteredModal({ message, type = 'success', duration = 0, title = '', onClose = null }) {
    if (typeof document === 'undefined') return;

    // Remove existing active modals to prevent overlap
    const existing = document.querySelectorAll('.shule-modal-backdrop');
    existing.forEach(el => el.remove());

    const isSuccess = type === 'success';
    const isError = type === 'error' || type === 'danger';
    const isWarning = type === 'warning';
    const isInfo = type === 'info';

    const normalizedType = isSuccess ? 'success' : (isError ? 'error' : (isWarning ? 'warning' : 'info'));

    const defaultTitles = {
        success: 'Success',
        error: 'Notice',
        warning: 'Warning',
        info: 'Information'
    };

    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const modalTitle = title || defaultTitles[normalizedType];
    const modalIcon = icons[normalizedType];

    const backdrop = document.createElement('div');
    backdrop.className = 'shule-modal-backdrop';

    backdrop.innerHTML = `
        <div class="shule-modal-card" role="dialog" aria-modal="true">
            <div class="shule-modal-icon-circle shule-modal-icon-${normalizedType}">
                ${modalIcon}
            </div>
            <h3 class="shule-modal-title">${modalTitle}</h3>
            <p class="shule-modal-message">${message}</p>
            <div class="shule-modal-btn-row">
                <button type="button" class="shule-modal-btn shule-modal-btn-primary-${normalizedType}" id="shuleModalConfirmBtn">
                    OK
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(backdrop);

    // Trigger enter animation
    requestAnimationFrame(() => {
        backdrop.classList.add('shule-modal-visible');
        const btn = backdrop.querySelector('#shuleModalConfirmBtn');
        if (btn) btn.focus();
    });

    let isClosed = false;
    const closeModal = () => {
        if (isClosed) return;
        isClosed = true;
        backdrop.classList.remove('shule-modal-visible');
        setTimeout(() => {
            backdrop.remove();
            if (typeof onClose === 'function') onClose();
        }, 180);
    };

    // Close on OK button
    const confirmBtn = backdrop.querySelector('#shuleModalConfirmBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', closeModal);
    }

    // Close on backdrop click (for success/info/warning)
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) closeModal();
    });

    // Close on Escape or Enter
    const handleKeyDown = (e) => {
        if (e.key === 'Escape' || e.key === 'Enter') {
            document.removeEventListener('keydown', handleKeyDown);
            closeModal();
        }
    };
    document.addEventListener('keydown', handleKeyDown);

    // Auto dismiss if duration is provided
    if (duration > 0) {
        setTimeout(closeModal, duration);
    }
}

/**
 * Global showToast function mapped to Centered Pop-up Modal
 */
export function showToast(message, type = 'success', duration = 2400, title = '') {
    showCenteredModal({
        message: String(message || ''),
        type: type,
        duration: (type === 'success' || type === 'info') ? duration : 0,
        title: title
    });
}

/**
 * Global showAlert function
 */
export function showAlert(message, type = 'info', title = '') {
    showCenteredModal({
        message: String(message || ''),
        type: type,
        duration: 0,
        title: title
    });
}

/**
 * Global showConfirm function
 */
export function showConfirm(message, onConfirm, onCancel, title = 'Please Confirm') {
    if (typeof document === 'undefined') return;

    const existing = document.querySelectorAll('.shule-modal-backdrop');
    existing.forEach(el => el.remove());

    const backdrop = document.createElement('div');
    backdrop.className = 'shule-modal-backdrop';

    backdrop.innerHTML = `
        <div class="shule-modal-card" role="dialog" aria-modal="true">
            <div class="shule-modal-icon-circle shule-modal-icon-warning">
                ❓
            </div>
            <h3 class="shule-modal-title">${title}</h3>
            <p class="shule-modal-message">${message}</p>
            <div class="shule-modal-btn-row">
                <button type="button" class="shule-modal-btn shule-modal-btn-secondary" id="shuleModalCancelBtn">
                    Cancel
                </button>
                <button type="button" class="shule-modal-btn shule-modal-btn-primary-success" id="shuleModalOkBtn">
                    Confirm
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(backdrop);

    requestAnimationFrame(() => {
        backdrop.classList.add('shule-modal-visible');
        const okBtn = backdrop.querySelector('#shuleModalOkBtn');
        if (okBtn) okBtn.focus();
    });

    let isClosed = false;
    const closeModal = (confirmed = false) => {
        if (isClosed) return;
        isClosed = true;
        backdrop.classList.remove('shule-modal-visible');
        setTimeout(() => {
            backdrop.remove();
            if (confirmed && typeof onConfirm === 'function') onConfirm();
            else if (!confirmed && typeof onCancel === 'function') onCancel();
        }, 180);
    };

    backdrop.querySelector('#shuleModalOkBtn').addEventListener('click', () => closeModal(true));
    backdrop.querySelector('#shuleModalCancelBtn').addEventListener('click', () => closeModal(false));
}

// Bind to window for global availability everywhere across the application
if (typeof window !== 'undefined') {
    window.showCenteredModal = showCenteredModal;
    window.showToast = showToast;
    window.showAlert = showAlert;
    window.showConfirm = showConfirm;

    // Override default browser alert with centered modal
    window.alert = function(msg) {
        showAlert(msg, 'info', 'Notice');
    };
}
