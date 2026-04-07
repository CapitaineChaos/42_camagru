document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('app');
    app.textContent = 'Camagru is running.';

    // Quick health check on all services
    ['auth', 'media', 'post', 'notification'].forEach(service => {
        fetch('/api/' + service + '/health')
            .then(r => r.json())
            .then(data => console.log(service, data))
            .catch(() => console.warn(service + ' unreachable'));
    });
});
