// delegated: the infinite gallery adds delete forms long after the page loaded
document.addEventListener('submit', (evenement) => {
    if (!evenement.target.matches('.delete-form')) {
        return;
    }
    if (!window.confirm('Delete this montage, its likes and its comments?')) {
        evenement.preventDefault();
    }
});
