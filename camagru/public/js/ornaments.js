// class-driven, not :hover: on :hover the element leaves the cursor and stops midway
document.querySelectorAll('.ornament').forEach((decor) => {
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

        if (decor.classList.contains('heart')) {
            return -(boite.bottom + marge);
        }
        if (decor.classList.contains('flower')) {
            return window.innerHeight - boite.top + marge;
        }
        if (decor.classList.contains('whisker-left')) {
            return -(boite.right + marge);
        }
        return window.innerWidth - boite.left + marge;
    };

    const jouer = () => {
        if (decor.classList.contains('playing')) {
            return;
        }
        decor.style.setProperty('--sortie', distanceDeSortie() * unitesParPixel() + 'px');
        decor.classList.add('playing');
    };

    decor.addEventListener('pointerover', jouer);
    decor.addEventListener('pointerdown', jouer);

    // off-screen nodes keep stretching the scroll area
    decor.addEventListener('animationend', () => {
        decor.style.display = 'none';
    });
});
