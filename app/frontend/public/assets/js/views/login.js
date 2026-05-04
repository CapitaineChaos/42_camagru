import { login } from '../services/auth.js';

export function mount(container) {
    const form = container.querySelector('#login-form');
    const error = container.querySelector('#login-error');

    // Show OAuth error passed via query string (e.g. /login?error=oauth_failed)
    const loginParams = new URLSearchParams(location.search);
    const oauthError = loginParams.get('error');
    if (oauthError) {
        error.textContent = `42 login failed (${oauthError.replace(/_/g, ' ')}). Please try again.`;
        history.replaceState(null, '', '/login');
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.textContent = '';

        const username = form.username.value.trim();
        const password = form.password.value;

        try {
            await login(username, password);
            console.log('Just logged in, redirecting to feed...');
            history.pushState(null, '', '/feed'); window.dispatchEvent(new PopStateEvent('popstate'));
        } catch (err) {
            error.textContent = err.message;
        }
    });
}
