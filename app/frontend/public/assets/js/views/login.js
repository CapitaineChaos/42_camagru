import { login } from '../services/auth.js';

export function mount(container) {
    const form = container.querySelector('#login-form');
    const error = container.querySelector('#login-error');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.textContent = '';

        const username = form.username.value.trim();
        const password = form.password.value;

        try {
            await login(username, password);
            location.hash = '#/feed';
        } catch (err) {
            error.textContent = err.message;
        }
    });
}
