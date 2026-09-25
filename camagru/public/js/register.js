const champ = document.getElementById('username');
const etat = document.getElementById('username-state');

if (champ && etat) {
    const repos = etat.textContent;
    let dernier = '';
    let minuteur;

    function dire(message, classe) {
        etat.textContent = message;
        etat.className = classe;
    }

    async function verifier() {
        const saisi = champ.value.trim();

        if (saisi === dernier) {
            return;
        }
        dernier = saisi;

        // the browser already refuses the shape: nothing to ask the server
        if (saisi === '' || !champ.checkValidity()) {
            dire(repos, 'hint');
            return;
        }

        try {
            const reponse = await fetch('/register/available?username=' + encodeURIComponent(saisi), {
                credentials: 'same-origin',
            });
            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }
            const avis = await reponse.json();

            // a slower answer must not overwrite a newer one
            if (avis.username !== champ.value.trim()) {
                return;
            }
            if (!avis.valid) {
                dire(avis.error, 'error-inline');
            } else {
                dire(avis.taken ? 'This username is already taken.' : 'This username is free.',
                     avis.taken ? 'error-inline' : 'ok-inline');
            }
        } catch {
            // the form checks again on submit: staying silent is enough
            dire(repos, 'hint');
        }
    }

    champ.addEventListener('input', () => {
        clearTimeout(minuteur);
        minuteur = setTimeout(verifier, 400);
    });
    champ.addEventListener('blur', verifier);
}
