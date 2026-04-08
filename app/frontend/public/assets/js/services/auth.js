import { api } from './api.js';

const TOKEN_KEY = 'camagru_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function removeToken() {
    localStorage.removeItem(TOKEN_KEY);
}

export function isLoggedIn() {
    return !!getToken();
}

export function logout() {
    removeToken();
}

export async function login(username, password) {
    const data = await api('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
    });
    setToken(data.token);
    return data;
}

export async function register(username, email, password) {
    return api('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({ username, email, password }),
    });
}
