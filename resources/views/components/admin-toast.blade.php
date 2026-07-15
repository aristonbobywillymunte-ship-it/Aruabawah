<!-- Load SweetAlert2 CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="admin-toast-root" class="pointer-events-none fixed right-5 top-5 z-[9999] flex w-[360px] max-w-[calc(100vw-2.5rem)] flex-col gap-3"></div>

@if (session()->has('success') || session()->has('error'))
    <div id="admin-flash-toast"
         data-type="{{ session()->has('error') ? 'error' : 'success' }}"
         data-message="{{ session()->has('error') ? session('error') : session('success') }}"
         class="hidden"></div>
@endif

<style>
    @keyframes adminToastEnter {
        from {
            opacity: 0;
            transform: translate3d(20px, -8px, 0) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }
    }

    @keyframes adminToastLeave {
        from {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }
        to {
            opacity: 0;
            transform: translate3d(20px, -8px, 0) scale(0.98);
        }
    }

    .admin-toast {
        pointer-events: auto;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        padding: 12px 14px;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12);
        border: 1px solid rgba(148, 163, 184, 0.18);
        overflow: hidden;
    }

    .admin-toast--success { border-left: 4px solid #1fa387; }
    .admin-toast--error { border-left: 4px solid #e5484d; }
    .admin-toast--warning { border-left: 4px solid #f59e0b; }
    .admin-toast--info { border-left: 4px solid #005bbf; }

    .admin-toast.is-entering { animation: adminToastEnter 220ms ease-out; }
    .admin-toast.is-leaving { animation: adminToastLeave 180ms ease-in forwards; }

    .admin-toast__icon {
        width: 34px;
        height: 34px;
        border-radius: 999px;
        display: grid;
        place-items: center;
        flex: 0 0 34px;
        font-size: 18px;
        line-height: 1;
        font-weight: 800;
    }

    .admin-toast--success .admin-toast__icon {
        color: #1fa387;
        background: rgba(31, 163, 135, 0.12);
    }
    .admin-toast--error .admin-toast__icon {
        color: #e5484d;
        background: rgba(229, 72, 77, 0.12);
    }
    .admin-toast--warning .admin-toast__icon {
        color: #f59e0b;
        background: rgba(245, 158, 11, 0.12);
    }
    .admin-toast--info .admin-toast__icon {
        color: #005bbf;
        background: rgba(0, 91, 191, 0.12);
    }

    .admin-toast__body {
        min-width: 0;
        flex: 1 1 auto;
    }

    .admin-toast__title {
        font-size: 13px;
        line-height: 1.35;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.01em;
        margin: 0;
    }

    .admin-toast__message {
        margin-top: 2px;
        font-size: 11px;
        line-height: 1.45;
        color: #64748b;
        word-break: break-word;
    }

    /* SweetAlert2 Solid Colored Toast Styles */
    .swal-toast-success {
        background-color: #1fa387 !important;
        color: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 25px rgba(31, 163, 135, 0.25) !important;
    }
    .swal-toast-success .swal2-title,
    .swal-toast-success .swal2-html-container {
        color: #ffffff !important;
    }
    .swal-toast-success .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.45) !important;
    }

    .swal-toast-error {
        background-color: #e5484d !important;
        color: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 25px rgba(229, 72, 77, 0.25) !important;
    }
    .swal-toast-error .swal2-title,
    .swal-toast-error .swal2-html-container {
        color: #ffffff !important;
    }
    .swal-toast-error .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.45) !important;
    }

    .swal-toast-warning {
        background-color: #f59e0b !important;
        color: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 25px rgba(245, 158, 11, 0.25) !important;
    }
    .swal-toast-warning .swal2-title,
    .swal-toast-warning .swal2-html-container {
        color: #ffffff !important;
    }
    .swal-toast-warning .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.45) !important;
    }

    .swal-toast-info {
        background-color: #005bbf !important;
        color: #ffffff !important;
        border-radius: 16px !important;
        box-shadow: 0 10px 25px rgba(0, 91, 191, 0.25) !important;
    }
    .swal-toast-info .swal2-title,
    .swal-toast-info .swal2-html-container {
        color: #ffffff !important;
    }
    .swal-toast-info .swal2-timer-progress-bar {
        background: rgba(255, 255, 255, 0.45) !important;
    }

    @keyframes swalToastEnterRight {
        from {
            opacity: 0;
            transform: translate3d(24px, 0, 0) scale(0.98);
        }
        to {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }
    }

    @keyframes swalToastLeaveRight {
        from {
            opacity: 1;
            transform: translate3d(0, 0, 0) scale(1);
        }
        to {
            opacity: 0;
            transform: translate3d(24px, 0, 0) scale(0.98);
        }
    }

    .swal-toast-enter {
        animation: swalToastEnterRight 220ms ease-out !important;
    }

    .swal-toast-leave {
        animation: swalToastLeaveRight 180ms ease-in forwards !important;
    }
</style>

<script>
    (function () {
        if (window.__adminToastBound) return;
        window.__adminToastBound = true;

        const rootId = 'admin-toast-root';
        const shownKeys = new Map();
        const dedupeMs = 1200;
        const autoCloseMs = 3000;

        const getRoot = () => document.getElementById(rootId);

        const sanitize = (value) => {
            if (!value) return '';
            return String(value)
                .replace(/<[^>]*>/g, '')
                .replace(/\s+/g, ' ')
                .trim();
        };

        const simplifyMessage = (message) => {
            let text = sanitize(message);
            text = text.replace(/^Gagal memanggil API:\s*/i, 'Gagal koneksi API: ');
            text = text.replace(/^Apify run failed:\s*/i, '');
            text = text.replace(/^Apify API Response\s+\d+:\s*/i, '');
            text = text.replace(/^Telegram request failed:\s*/i, 'Gagal kirim Telegram: ');
            text = text.replace(/^ConnectionException:\s*/i, '');
            text = text.replace(/^HTTP\s+\d+:\s*/i, '');
            text = text.replace(/ for Telegram API .*$/i, '');
            text = text.replace(/\(see https:\/\/curl\.se\/libcurl\/c\/libcurl-errors\.html.*$/i, '');
            text = text.replace(/^\{\s*"error":\s*\{[\s\S]*?"message":\s*"([^"]+)".*\}\s*\}\s*$/i, '$1');
            text = text.replace(/\s+/g, ' ').trim();
            if (text.length > 140) {
                text = text.slice(0, 137).trim() + '...';
            }
            return text;
        };

        const buildToast = (type, title, message) => {
            const el = document.createElement('div');
            el.className = `admin-toast admin-toast--${type} is-entering`;
            el.innerHTML = `
                <div class="admin-toast__icon">${type === 'success' ? '✓' : (type === 'warning' ? '!' : '×')}</div>
                <div class="admin-toast__body">
                    <div class="admin-toast__title">${title}</div>
                    ${message ? `<div class="admin-toast__message">${message}</div>` : ''}
                </div>
            `;
            return el;
        };

        const showToast = (type = 'info', title = '', message = '') => {
            const cleanTitle = simplifyMessage(title);
            const cleanMessage = simplifyMessage(message);
            if (!cleanTitle) return;

            const key = `${type}|${cleanTitle}|${cleanMessage}`;
            const now = Date.now();
            const lastShown = shownKeys.get(key) || 0;
            if (now - lastShown < dedupeMs) return;
            shownKeys.set(key, now);

            // Fallback chain: SweetAlert2 -> Custom HTML Toast -> Alert
            if (window.Swal) {
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: autoCloseMs,
                    timerProgressBar: true,
                    iconColor: '#ffffff', // Set icon color to white for contrast on solid background
                    customClass: {
                        popup: `swal-toast-${type}`,
                    },
                    showClass: {
                        popup: 'swal-toast-enter',
                    },
                    hideClass: {
                        popup: 'swal-toast-leave',
                    }
                });
                Toast.fire({
                    icon: type,
                    title: cleanTitle,
                    text: cleanMessage
                });
                return;
            }

            const root = getRoot();
            if (!root) {
                alert(`${type.toUpperCase()}: ${cleanTitle} ${cleanMessage}`);
                return;
            }

            const toast = buildToast(type, cleanTitle, cleanMessage);
            root.appendChild(toast);

            let closing = false;
            const closeToast = () => {
                if (closing) return;
                closing = true;
                toast.classList.remove('is-entering');
                toast.classList.add('is-leaving');
                window.setTimeout(() => toast.remove(), 220);
            };

            window.setTimeout(closeToast, autoCloseMs);
        };

        const normalizeType = (type) => {
            if (type === 'success' || type === 'warning' || type === 'error' || type === 'info') {
                return type;
            }
            return 'info';
        };

        const handlePayload = (payload) => {
            if (!payload) return;
            
            let data = payload;
            if (payload && typeof payload === 'object' && 'detail' in payload) {
                data = payload.detail;
            }
            if (Array.isArray(data)) {
                data = data[0] ?? {};
            }
            
            const content = data && typeof data === 'object' && 'payload' in data ? data.payload : data;
            const type = normalizeType(content?.type ?? data?.type ?? payload?.type);
            const title = content?.title ?? content?.message ?? data?.title ?? data?.message ?? payload?.title ?? '';
            const message = content?.messageDetail ?? content?.detail ?? data?.detail ?? payload?.message ?? '';
            
            showToast(type, title, message);
        };

        const registerLivewireListeners = () => {
            if (window.Livewire && window.Livewire.on && !window.__adminToastLivewireBound) {
                window.__adminToastLivewireBound = true;
                window.Livewire.on('admin-toast', (payload) => handlePayload(payload));
                window.Livewire.on('apify-toast', (payload) => handlePayload(payload));
            }
        };

        const bindDomEvent = (target) => {
            target.addEventListener('admin-toast', (event) => handlePayload(event.detail));
            target.addEventListener('apify-toast', (event) => handlePayload(event.detail));
        };

        bindDomEvent(window);
        bindDomEvent(document);
        document.addEventListener('livewire:init', registerLivewireListeners);
        document.addEventListener('livewire:navigated', registerLivewireListeners);
        window.addEventListener('pageshow', registerLivewireListeners);

        // Immediate fallback check if Livewire is already initialized
        if (window.Livewire) {
            registerLivewireListeners();
        }

        const showSessionFlash = () => {
            const flashEl = document.getElementById('admin-flash-toast');
            if (!flashEl) return;

            const type = flashEl.dataset.type === 'error' ? 'error' : 'success';
            const message = flashEl.dataset.message || '';
            showToast(type, type === 'success' ? 'Berhasil' : 'Gagal', message);
            flashEl.remove();
        };

        document.addEventListener('DOMContentLoaded', showSessionFlash);
        document.addEventListener('livewire:navigated', showSessionFlash);
    })();
</script>
