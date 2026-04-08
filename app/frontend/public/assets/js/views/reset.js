import { api } from '../services/api.js';

export function mount(container) {
    const form    = container.querySelector('#reset-form');
    const error   = container.querySelector('#reset-error');
    const success = container.querySelector('#reset-success');

    // Parse token from hash: #/reset?token=xxx
    const params = new URLSearchParams(location.hash.split('?')[1] ?? '');
    const token  = params.get('token') ?? '';

    if (!token) {
        error.textContent = 'Lien invalide ou manquant.';
        form.style.display = 'none';
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.textContent   = '';
        success.textContent = '';

        const password  = form.password.value;
        const password2 = form.password2.value;

        if (password !== password2) {
            error.textContent = 'Les mots de passe ne correspondent pas.';
            return;
        }

        try {
            const data = await api('/api/auth/reset', {
                method: 'POST',
                body: JSON.stringify({ token, password }),
            });
            success.textContent = data.message;
            form.style.display = 'none';
            setTimeout(() => { location.hash = '#/login'; }, 2000);
        } catch (err) {
            error.textContent = err.message;
        }
    });
}
