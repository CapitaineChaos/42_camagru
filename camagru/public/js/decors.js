// class-driven, not :hover: on :hover the element leaves the cursor and stops midway
document.querySelectorAll('.decor-anim').forEach((decor) => {
    const lettrage = decor.closest('svg');
    if (!lettrage) {
        return;
    }

    // transforms are in viewBox units, not screen pixels
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

    // off-screen nodes keep stretching the scroll area
    decor.addEventListener('animationend', () => {
        decor.style.display = 'none';
    });
});
