import { api } from '../services/api.js';

export async function mount(container) {
    const grid = container.querySelector('#feed-grid');

    try {
        const posts = await api('/api/post/posts');

        if (!Array.isArray(posts) || !posts.length) {
            grid.innerHTML = '<p class="feed-empty">No photos yet.</p>';
            return;
        }

        grid.innerHTML = posts.map(post => `
            <article class="feed-card">
                <div class="feed-card__img-wrap">
                    <img src="${post.image_url}" alt="Photo by ${post.username}" loading="lazy">
                </div>
                <footer class="feed-card__footer">
                    <span class="feed-card__author">${post.username}</span>
                </footer>
            </article>
        `).join('');
    } catch (err) {
        grid.innerHTML = `<p class="feed-error">Failed to load gallery.</p>`;
    }
}
