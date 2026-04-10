import { register } from '../services/auth.js';

export function mount(container) {
    const form = container.querySelector('#register-form');
    const error = container.querySelector('#register-error');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.textContent = '';

        const username = form.username.value.trim();
        const email = form.email.value.trim();
        const password = form.password.value;
        const password2 = form.password2.value;

        if (password !== password2) {
            error.textContent = 'Passwords do not match.';
            return;
        }

        try {
            await register(username, email, password);
            location.hash = '#/login';
        } catch (err) {
            error.textContent = err.message;
        }
    });
}
