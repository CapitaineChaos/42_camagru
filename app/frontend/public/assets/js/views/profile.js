import { api } from '../services/api.js';

export async function mount(container) {
    // 1. Load current user info
    let user;
    try {
        const data = await api('/api/auth/me');
        user = data.user;
    } catch {
        container.innerHTML = '<p class="profile-error">Failed to load profile.</p>';
        return;
    }

    // 2. Pre-fill fields
    container.querySelector('#p-username').value = user.username;
    container.querySelector('#p-email').value    = user.email;

    // 3. Info form
    const formInfo  = container.querySelector('#form-info');
    const infoHint  = container.querySelector('#info-hint');

    formInfo.addEventListener('submit', async (e) => {
        e.preventDefault();
        infoHint.textContent = '';
        infoHint.className   = 'form-hint';

        const username = formInfo.username.value.trim();
        const email    = formInfo.email.value.trim();

        const payload = {};
        if (username !== user.username) payload.username = username;
        if (email    !== user.email)    payload.email    = email;

        if (!Object.keys(payload).length) {
            infoHint.textContent  = 'No changes detected.';
            infoHint.classList.add('form-hint--warn');
            return;
        }

        try {
            const res = await api('/api/auth/me', {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
            user = res.user;
            infoHint.textContent = res.message ?? 'Profile updated.';
            infoHint.classList.add('form-hint--ok');
        } catch (err) {
            infoHint.textContent = err.message;
            infoHint.classList.add('form-hint--error');
        }
    });

    // 4. Password form
    const formPass = container.querySelector('#form-password');
    const passHint = container.querySelector('#pass-hint');

    formPass.addEventListener('submit', async (e) => {
        e.preventDefault();
        passHint.textContent = '';
        passHint.className   = 'form-hint';

        const current = formPass.current_password.value;
        const next    = formPass.new_password.value;
        const next2   = formPass.new_password2.value;

        if (next !== next2) {
            passHint.textContent = 'Passwords do not match.';
            passHint.classList.add('form-hint--error');
            return;
        }

        try {
            const res = await api('/api/auth/me', {
                method: 'PATCH',
                body: JSON.stringify({ current_password: current, new_password: next }),
            });
            passHint.textContent = res.message ?? 'Password updated.';
            passHint.classList.add('form-hint--ok');
            formPass.reset();
        } catch (err) {
            passHint.textContent = err.message;
            passHint.classList.add('form-hint--error');
        }
    });
}
