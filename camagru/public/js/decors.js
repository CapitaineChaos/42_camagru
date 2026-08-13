// Décors des lettrages — enseigne de l'accueil comme titres de page : ils partent hors de
// la fenêtre et n'y reviennent pas. L'animation est portée par une classe et non par :hover,
// sinon l'élément quitte le curseur dès qu'il bouge et s'interrompt à mi-course.
document.querySelectorAll('.decor-anim').forEach((decor) => {
    const lettrage = decor.closest('svg');
    if (!lettrage) {
        return;
    }

    // les transformations d'un décor s'expriment dans le repère du viewBox, pas en pixels écran
    const unitesParPixel = () =>
        lettrage.viewBox.baseVal.width / lettrage.getBoundingClientRect().width;

    const distanceDeSortie = () => {
        const boite = decor.getBoundingClientRect();
        const marge = 60;

        if (decor.classList.contains('coeur')) {
            return -(boite.bottom + marge);
        }
        if (decor.classList.contains('fleur')) {
            return window.innerHeight - boite.top + marge;
        }
        if (decor.classList.contains('moustache-gauche')) {
            return -(boite.right + marge);
        }
        return window.innerWidth - boite.left + marge;
    };

    const jouer = () => {
        if (decor.classList.contains('anime')) {
            return;
        }
        decor.style.setProperty('--sortie', distanceDeSortie() * unitesParPixel() + 'px');
        decor.classList.add('anime');
    };

    decor.addEventListener('pointerover', jouer);
    decor.addEventListener('pointerdown', jouer);

    // hors champ : retiré du rendu, sinon il continue d'allonger la zone défilable
    decor.addEventListener('animationend', () => {
        decor.style.display = 'none';
    });
});
