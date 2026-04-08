import { api } from '../services/api.js';

export async function mount(container) {
    const msg   = container.querySelector('#verify-message');
    const error = container.querySelector('#verify-error');
    const link  = container.querySelector('#verify-login');

    const params = new URLSearchParams(location.hash.split('?')[1] ?? '');
    const token  = params.get('token') ?? '';

    if (!token) {
        msg.textContent   = '';
        error.textContent = 'Lien invalide ou manquant.';
        return;
    }

    try {
        const data = await api(`/api/auth/verify?token=${encodeURIComponent(token)}`);
        msg.textContent   = data.message;
        link.style.display = '';
    } catch (err) {
        msg.textContent   = '';
        error.textContent = err.message;
    }
}
