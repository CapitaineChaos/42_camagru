import { api } from '../services/api.js';

export function mount(container) {
    const form    = container.querySelector('#forgot-form');
    const error   = container.querySelector('#forgot-error');
    const success = container.querySelector('#forgot-success');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.textContent   = '';
        success.textContent = '';

        const email = form.email.value.trim();

        try {
            const data = await api('/api/auth/forgot', {
                method: 'POST',
                body: JSON.stringify({ email }),
            });
            success.textContent = data.message;
            form.reset();
        } catch (err) {
            error.textContent = err.message;
        }
    });
}
