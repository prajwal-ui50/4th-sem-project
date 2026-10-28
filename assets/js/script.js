// assets/js/script.js
// Ticket Management System — Interactive Client-side Script

// SVG icons — inline so no icon library is needed
const ICON_SUN = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>`;
const ICON_MOON = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
const ICON_EYE = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
const ICON_EYE_OFF = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

document.addEventListener("DOMContentLoaded", () => {
    initTheme();
    initSidebar();
    initPasswordToggle();
    initPasswordStrength();
    initPriorityPicker();
    initDropzones();
    initFormLoading();
});

/* ========== THEME MANAGEMENT ========== */
function getTheme() {
    return localStorage.getItem('theme') || 'dark';
}

function setTheme(theme) {
    localStorage.setItem('theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeIcon();
}

function initTheme() {
    updateThemeIcon();
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleTheme();
            }
        });
    }
}

function toggleTheme() {
    const current = getTheme();
    const next = current === 'dark' ? 'light' : 'dark';
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.style.transform = 'rotate(180deg) scale(0.6)';
        icon.style.opacity = '0';
        setTimeout(() => {
            setTheme(next);
            icon.style.transform = 'rotate(0deg) scale(1)';
            icon.style.opacity = '1';
        }, 180);
    } else {
        setTheme(next);
    }
}

function updateThemeIcon() {
    const icon = document.getElementById('themeIcon');
    const toggle = document.getElementById('themeToggle');
    if (!icon) return;

    const isDark = getTheme() === 'dark';
    icon.innerHTML = isDark ? ICON_SUN : ICON_MOON;
    const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    if (toggle) {
        toggle.setAttribute('title', label);
        toggle.setAttribute('aria-label', label);
    }
}

/* ========== SIDEBAR (DESKTOP COLLAPSE & MOBILE DRAWER) ========== */
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const wrapper = document.querySelector('.dashboard-wrapper');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const mobileBtn = document.getElementById('mobileMenuBtn');

    if (!sidebar) return;

    // Restore desktop collapsed state
    if (window.innerWidth > 768) {
        const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
            if (wrapper) wrapper.classList.add('sidebar-collapsed');
        }
    }

    // Desktop collapse toggle
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                // Mobile: close drawer
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
            } else {
                // Desktop: collapse/expand
                const collapsed = sidebar.classList.toggle('collapsed');
                if (wrapper) wrapper.classList.toggle('sidebar-collapsed', collapsed);
                localStorage.setItem('sidebar_collapsed', collapsed ? 'true' : 'false');
            }
        });
    }

    // Mobile hamburger button
    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.add('active');
            if (overlay) overlay.classList.add('active');
        });
    }

    // Overlay backdrop click to close mobile drawer
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }
}

/* ========== PASSWORD VISIBILITY TOGGLE ========== */
function initPasswordToggle() {
    const toggleButtons = document.querySelectorAll('.password-toggle-btn');
    toggleButtons.forEach(btn => {
        btn.innerHTML = ICON_EYE;
        btn.setAttribute('type', 'button');
        btn.setAttribute('aria-label', 'Toggle password visibility');

        btn.addEventListener('click', () => {
            let input = btn.previousElementSibling;
            if (!input || input.tagName !== 'INPUT') {
                input = btn.closest('.input-icon-group, .input-group')?.querySelector('input');
            }
            if (!input || input.tagName !== 'INPUT') return;

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.innerHTML = isPassword ? ICON_EYE_OFF : ICON_EYE;
            btn.setAttribute('title', isPassword ? 'Hide password' : 'Show password');
        });
    });
}

/* ========== PASSWORD STRENGTH METER ========== */
function initPasswordStrength() {
    const passwordInputs = document.querySelectorAll('input[data-strength-target]');
    passwordInputs.forEach(input => {
        const targetId = input.getAttribute('data-strength-target');
        const container = document.getElementById(targetId);
        if (!container) return;

        const bar = container.querySelector('.password-strength-bar');
        const text = container.querySelector('.password-strength-text');

        input.addEventListener('input', () => {
            const val = input.value;
            if (!val) {
                if (bar) bar.style.width = '0%';
                if (text) text.textContent = '';
                return;
            }

            let score = 0;
            if (val.length >= 6) score += 1;
            if (val.length >= 10) score += 1;
            if (/[A-Z]/.test(val)) score += 1;
            if (/[0-9]/.test(val)) score += 1;
            if (/[^A-Za-z0-9]/.test(val)) score += 1;

            let percent = '20%';
            let color = 'var(--danger)';
            let label = 'Weak';

            if (score <= 1) {
                percent = '20%';
                color = 'var(--danger)';
                label = 'Weak — min 6 chars recommended';
            } else if (score === 2 || score === 3) {
                percent = '60%';
                color = 'var(--warning)';
                label = 'Medium — add symbols/numbers';
            } else if (score >= 4) {
                percent = '100%';
                color = 'var(--success)';
                label = 'Strong password';
            }

            if (bar) {
                bar.style.width = percent;
                bar.style.backgroundColor = color;
            }
            if (text) {
                text.textContent = label;
                text.style.color = color;
            }
        });
    });
}

/* ========== PRIORITY PICKER (RADIO PILLS) ========== */
function initPriorityPicker() {
    const pickers = document.querySelectorAll('.priority-picker');
    pickers.forEach(picker => {
        const pills = picker.querySelectorAll('.priority-pill');
        pills.forEach(pill => {
            const radio = pill.querySelector('input[type="radio"]');
            if (radio && radio.checked) {
                pill.classList.add('active');
            }

            pill.addEventListener('click', () => {
                pills.forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                if (radio) radio.checked = true;
            });
        });
    });
}

/* ========== DRAG-AND-DROP DROPZONE ========== */
function initDropzones() {
    const dropzones = document.querySelectorAll('.dropzone');
    dropzones.forEach(zone => {
        const input = zone.querySelector('.dropzone-input');
        const preview = zone.querySelector('.dropzone-preview');
        const previewName = zone.querySelector('.dropzone-preview-name');
        const removeBtn = zone.querySelector('.dropzone-preview-remove');

        if (!input) return;

        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });

        zone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
                updateDropzonePreview(input, preview, previewName);
            }
        });

        input.addEventListener('change', () => {
            updateDropzonePreview(input, preview, previewName);
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                input.value = '';
                if (preview) preview.classList.remove('has-file');
            });
        }
    });
}

function updateDropzonePreview(input, preview, previewName) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const sizeKb = (file.size / 1024).toFixed(1);
        if (previewName) previewName.textContent = `${file.name} (${sizeKb} KB)`;
        if (preview) preview.classList.add('has-file');
    } else {
        if (preview) preview.classList.remove('has-file');
    }
}

/* ========== FORM SUBMIT LOADING STATE ========== */
function initFormLoading() {
    const forms = document.querySelectorAll('form:not([data-no-loader])');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (form.checkValidity && !form.checkValidity()) {
                return; // Let browser HTML5 validation fire
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('is-loading');
            }
        });
    });
}
