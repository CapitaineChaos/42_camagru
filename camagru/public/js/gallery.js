// Infinite scroll, opt-in: the box swaps the page links for a feed that fetches
// the next page when its end comes into view. Without script the links remain.
(() => {
    const bascule = document.getElementById('scroll-toggle');
    const case_ = document.getElementById('infinite');
    const flux = document.getElementById('feed');
    const pagination = document.querySelector('.pagination');

    if (!bascule || !case_ || !flux || !('IntersectionObserver' in window)) {
        return;
    }

    const CLE = 'gallery.infinite';
    const total = Number(flux.dataset.pages);
    let derniere = Number(flux.dataset.page);
    let enCours = false;
    let observateur = null;

    // storage can be missing or throw (private window, blocked site data)
    const lire = () => {
        try {
            return window.localStorage.getItem(CLE) === '1';
        } catch {
            return false;
        }
    };
    const ecrire = (actif) => {
        try {
            window.localStorage.setItem(CLE, actif ? '1' : '0');
        } catch {
            // the choice holds for this visit only
        }
    };

    const etat = document.createElement('p');
    etat.className = 'feed-status';
    etat.setAttribute('aria-live', 'polite');

    const sentinelle = document.createElement('div');
    sentinelle.className = 'feed-sentinel';

    function dire(texte, reessayer) {
        etat.textContent = texte;
        if (reessayer) {
            const bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.className = 'button-quiet';
            bouton.textContent = 'Retry';
            bouton.addEventListener('click', suivante);
            etat.append(' ', bouton);
        }
    }

    async function suivante() {
        if (enCours || derniere >= total) {
            return;
        }
        enCours = true;
        dire('Loading…', false);

        try {
            const reponse = await fetch('/gallery?page=' + (derniere + 1), {
                credentials: 'same-origin',
            });
            if (!reponse.ok) {
                throw new Error(String(reponse.status));
            }
            const page = new DOMParser().parseFromString(await reponse.text(), 'text/html');

            // a montage posted meanwhile shifts every page by one: skip the repeat
            for (const carte of page.querySelectorAll('#feed > .montage-card')) {
                if (!document.getElementById(carte.id)) {
                    flux.append(document.adoptNode(carte));
                }
            }
            derniere += 1;
            dire(derniere >= total ? 'You have seen every montage.' : '', false);
        } catch {
            dire('Could not load more montages.', true);
        } finally {
            enCours = false;
        }

        // a tall screen may still show the end: keep going until it scrolls
        if (derniere < total && estVisible()) {
            suivante();
        }
    }

    function estVisible() {
        return sentinelle.getBoundingClientRect().top < window.innerHeight + 400;
    }

    function activer() {
        if (pagination) {
            pagination.hidden = true;
        }
        flux.after(etat, sentinelle);
        if (derniere >= total) {
            dire('You have seen every montage.', false);
            return;
        }
        observateur = new IntersectionObserver((entrees) => {
            if (entrees.some((entree) => entree.isIntersecting)) {
                suivante();
            }
        }, { rootMargin: '0px 0px 400px 0px' });
        observateur.observe(sentinelle);
    }

    bascule.hidden = false;
    case_.checked = lire();
    if (case_.checked) {
        activer();
    }

    case_.addEventListener('change', () => {
        ecrire(case_.checked);
        if (case_.checked) {
            activer();
            return;
        }
        // back to pages: the page this visit started on, links included
        window.location.assign('/gallery?page=' + flux.dataset.page);
    });
})();
