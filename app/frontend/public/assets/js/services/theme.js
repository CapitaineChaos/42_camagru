// ============================================================
// Theme Service - Dark/light theme management
// Storage: localStorage key 'camagru_theme'
// Applied: data-theme="dark"|"light" on <html>
// ============================================================

const STORAGE_KEY = 'camagru_theme';
const ICONS = { light: '☾', dark: '☀' };

function getPreferred() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'dark' || stored === 'light') return stored;
    // Respect system preference if no stored choice
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('theme-toggle');
    if (btn) btn.textContent = ICONS[theme];
}

export function initTheme() {
    const theme = getPreferred();
    apply(theme);

    const btn = document.getElementById('theme-toggle');
    if (btn) {
        btn.addEventListener('click', toggleTheme);
    }
}

export function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next    = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem(STORAGE_KEY, next);
    apply(next);
}

export function getTheme() {
    return document.documentElement.getAttribute('data-theme') || 'light';
}
