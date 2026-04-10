import { isLoggedIn, removeToken } from './services/auth.js';
import { api } from './services/api.js';
import { initTheme } from './services/theme.js';

const routes = {
    'login':    { view: 'login',    auth: false },
    'register': { view: 'register', auth: false },
    'forgot':   { view: 'forgot',   auth: false },
    'reset':    { view: 'reset',    auth: false },
    'verify':   { view: 'verify',   auth: false },
    'feed':     { view: 'feed',     auth: false },
    'editor':   { view: 'editor',   auth: true  },
    'profile':  { view: 'profile',  auth: true  },
};

const app = document.getElementById('app');
const nav = document.getElementById('nav');

// Verify token validity against the server once on load
let tokenChecked = false;
async function checkToken() {
    if (tokenChecked || !isLoggedIn()) return;
    tokenChecked = true;
    try {
        await api('/api/auth/me');
        // success: token is valid, nothing to do
    } catch (err) {
        // If api() already removed the token (401 path), nothing to do.
        // If auth is just down (502), keep the token - can't verify but don't wipe it.
        if (err.message === 'Session expired') return;
        // Other errors (network, 503): silently ignore
    }
}

function updateNav() {
    if (isLoggedIn()) {
        nav.innerHTML = `
            <a href="#/feed">Gallery</a>
            <a href="#/editor">Create</a>
            <a href="#/profile">Profile</a>
            <a href="#/logout">Sign out</a>`;
    } else {
        nav.innerHTML = `
            <a href="#/feed">Gallery</a>
            <a href="#/login">Sign in</a>
            <a href="#/register">Sign up</a>`;
    }
}

async function loadView(name) {
    const resp = await fetch(`/views/${name}.html`, { cache: 'no-cache' });
    if (!resp.ok) return '<p>Page not found.</p>';
    return resp.text();
}

async function mountView(name) {
    try {
        const mod = await import(`./views/${name}.js`);
        if (mod.mount) mod.mount(app);
    } catch {
        // No JS module for this view - that's fine
    }
}

async function navigate() {
    const full = location.hash.slice(2) || 'feed';
    const [hash] = full.split('?'); // ignore query params for route matching

    // Logout is a special action, not a view
    if (hash === 'logout') {
        const { logout } = await import('./services/auth.js');
        logout();
        location.hash = '#/login';
        return;
    }

    const route = routes[hash];
    if (!route) {
        app.innerHTML = '<p>Page not found.</p>';
        return;
    }

    // Protected route - redirect to login
    if (route.auth && !isLoggedIn()) {
        location.hash = '#/login';
        return;
    }

    app.innerHTML = await loadView(route.view);
    updateNav();
    await mountView(route.view);
}

window.addEventListener('hashchange', navigate);
document.addEventListener('DOMContentLoaded', async () => {
    initTheme();
    await checkToken();
    navigate();
});
