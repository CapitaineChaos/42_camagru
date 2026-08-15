document.querySelectorAll('.delete-form').forEach((formulaire) => {
    formulaire.addEventListener('submit', (evenement) => {
        if (!window.confirm('Delete this montage, its likes and its comments?')) {
            evenement.preventDefault();
        }
    });
});
