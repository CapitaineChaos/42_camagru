import { isLoggedIn } from './services/auth.js';

const routes = {
    'login':    { view: 'login',    auth: false },
    'register': { view: 'register', auth: false },
    'feed':     { view: 'feed',     auth: false },
    'editor':   { view: 'editor',   auth: true  },
    'profile':  { view: 'profile',  auth: true  },
};

const app = document.getElementById('app');
const nav = document.getElementById('nav');

function updateNav() {
    if (isLoggedIn()) {
        nav.innerHTML = `
            <a href="#/feed">Galerie</a>
            <a href="#/editor">Créer</a>
            <a href="#/profile">Profil</a>
            <a href="#/logout">Déconnexion</a>`;
    } else {
        nav.innerHTML = `
            <a href="#/feed">Galerie</a>
            <a href="#/login">Connexion</a>
            <a href="#/register">Inscription</a>`;
    }
}

async function loadView(name) {
    const resp = await fetch(`/views/${name}.html`);
    if (!resp.ok) return '<p>Page introuvable.</p>';
    return resp.text();
}

async function mountView(name) {
    try {
        const mod = await import(`./views/${name}.js`);
        if (mod.mount) mod.mount(app);
    } catch {
        // No JS module for this view — that's fine
    }
}

async function navigate() {
    const hash = location.hash.slice(2) || 'feed'; // default: feed

    // Logout is a special action, not a view
    if (hash === 'logout') {
        const { logout } = await import('./services/auth.js');
        logout();
        location.hash = '#/login';
        return;
    }

    const route = routes[hash];
    if (!route) {
        app.innerHTML = '<p>Page introuvable.</p>';
        return;
    }

    // Protected route — redirect to login
    if (route.auth && !isLoggedIn()) {
        location.hash = '#/login';
        return;
    }

    app.innerHTML = await loadView(route.view);
    updateNav();
    await mountView(route.view);
}

window.addEventListener('hashchange', navigate);
document.addEventListener('DOMContentLoaded', () => {
    navigate();
});
