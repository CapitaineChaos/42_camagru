import { getToken, removeToken } from './auth.js';

const SERVICE_NAMES = {
    '/api/auth/':         'auth',
    '/api/media/':        'media',
    '/api/post/':         'post',
    '/api/notification/': 'notification',
};

function serviceName(path) {
    for (const [prefix, name] of Object.entries(SERVICE_NAMES)) {
        if (path.startsWith(prefix)) return name;
    }
    return 'server';
}

export async function api(path, options = {}) {
    const token = getToken();
    const headers = { 'Content-Type': 'application/json', ...options.headers };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    let res;
    try {
        res = await fetch(path, { ...options, headers });
    } catch {
        throw new Error(`${serviceName(path)} service unavailable.`);
    }

    if (res.status === 502 || res.status === 503) {
        throw new Error(`${serviceName(path)} service unavailable.`);
    }

    if (res.status === 401 && token && !path.startsWith('/api/auth/login') && !path.startsWith('/api/auth/register')) {
        removeToken();
        location.hash = '#/login';
        throw new Error('Session expired');
    }

    const data = await res.json().catch(() => null);

    if (!res.ok) {
        throw new Error(data?.error || `Erreur ${res.status}`);
    }

    return data;
}
