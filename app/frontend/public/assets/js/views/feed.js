import { api } from '../services/api.js';

export async function mount(container) {
    const grid = container.querySelector('#feed-grid');

    try {
        const res = await api('/api/post/posts');
        const posts = await res.json();

        if (!posts.length) {
            grid.innerHTML = '<p>Aucun post pour le moment.</p>';
            return;
        }

        grid.innerHTML = posts.map(post => `
            <article class="feed-card">
                <img src="${post.image}" alt="">
            </article>
        `).join('');
    } catch {
        grid.innerHTML = '<p>Impossible de charger les posts.</p>';
    }
}
