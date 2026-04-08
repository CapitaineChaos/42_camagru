import { getToken, removeToken } from './auth.js';

export async function api(path, options = {}) {
    const token = getToken();
    const headers = { 'Content-Type': 'application/json', ...options.headers };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    const res = await fetch(path, { ...options, headers });

    // Token expired / invalid — force logout
    if (res.status === 401) {
        removeToken();
        location.hash = '#/login';
        throw new Error('Session expirée');
    }

    return res;
}
