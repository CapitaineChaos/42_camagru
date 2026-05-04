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
            <a href="/feed">Gallery</a>
            <a href="/editor">Create</a>
            <a href="/profile">Profile</a>
            <a href="/logout">Sign out</a>`;
    } else {
        nav.innerHTML = `
            <a href="/feed">Gallery</a>
            <a href="/login">Sign in</a>
            <a href="/register">Sign up</a>`;
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

export function navigate(path) {
    const target = path ?? location.pathname;
    const [pathname, search] = target.split('?');
    const name = pathname.replace(/^\//, '') || 'feed';

    if (path && path !== location.pathname + (location.search || '')) {
        history.pushState(null, '', path);
    }

    // Logout is a special action, not a view
    if (name === 'logout') {
        import('./services/auth.js').then(({ logout }) => {
            logout();
            navigate('/login');
        });
        return;
    }

    const route = routes[name];
    if (!route) {
        app.innerHTML = '<p>Page not found.</p>';
        return;
    }

    // Protected route - redirect to login
    if (route.auth && !isLoggedIn()) {
        navigate('/login');
        return;
    }

    Promise.resolve()
        .then(() => loadView(route.view))
        .then(html => {
            app.innerHTML = html;
            updateNav();
            return mountView(route.view);
        });
}

// Intercept <a href="/..."> clicks for client-side navigation (skip /api/ and external links)
document.addEventListener('click', e => {
    const a = e.target.closest('a');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || !href.startsWith('/') || href.startsWith('//') || href.startsWith('/api/')) return;
    e.preventDefault();
    navigate(href);
});

window.addEventListener('popstate', () => navigate());

// On initial load, check token validity and navigate to the correct page
document.addEventListener('DOMContentLoaded', async () => {
    initTheme();

    // Handle OAuth callback: backend redirects here with ?token=JWT
    const params = new URLSearchParams(location.search);
    const oauthToken = params.get('token');
    if (oauthToken) {
        const { setToken } = await import('./services/auth.js');
        setToken(oauthToken);
        history.replaceState(null, '', '/feed');
        await checkToken();
        navigate();
        return;
    }

    await checkToken();
    navigate();
});
