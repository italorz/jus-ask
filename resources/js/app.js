import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// ── Theme ──────────────────────────────────────────────────
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('btn-theme-toggle');
    if (btn) {
        btn.innerHTML = theme === 'dark'
            ? '<i class="bi bi-sun-fill"></i>'
            : '<i class="bi bi-moon-fill"></i>';
    }
}

const savedTheme = localStorage.getItem('jus-theme') || 'dark';
applyTheme(savedTheme);

document.addEventListener('DOMContentLoaded', function () {
    applyTheme(localStorage.getItem('jus-theme') || 'dark');

    document.addEventListener('click', function (e) {
        if (e.target.closest('#btn-theme-toggle')) {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            localStorage.setItem('jus-theme', next);
        }
    });

    // ── Bootstrap Tooltips ─────────────────────────────────
    function initTooltips(root) {
        (root || document).querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            if (!el._tipInstance) {
                el._tipInstance = new bootstrap.Tooltip(el, { trigger: 'hover focus' });
            }
        });
    }
    initTooltips();

    // Re-init após Livewire re-render
    document.addEventListener('livewire:updated', () => initTooltips());
});
