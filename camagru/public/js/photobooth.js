const scene = document.getElementById('scene');
const flux = document.getElementById('stream');
const apercu = document.getElementById('preview');
const etat = document.getElementById('status');
const conteneur = document.getElementById('pieces');
const champCapture = document.getElementById('capture');
const champCalques = document.getElementById('layers');
const champFichier = document.getElementById('file');
const prendre = document.getElementById('shoot');
const reprendre = document.getElementById('retake');
const enregistrer = document.getElementById('save');

const LARGEUR_DEFAUT = 0.4;
const LARGEUR_MIN = 0.08;
const LARGEUR_MAX = 2;
const PAS_MOLETTE = 1.09;

// {slug, x, y, w, el, choix}: x and y locate the centre, w the width,
// all as fractions of the scene
const pieces = [];
let cameraPrete = false;
let gesteEnCours = false;

const borne = (valeur, minimum, maximum) => Math.max(minimum, Math.min(maximum, valeur));

const posee = (slug) => pieces.find((piece) => piece.slug === slug);

const DEMI_POIGNEE = 9;   // half a handle: they straddle the corners
const MARGE_BORD = 4;     // ... unless the piece runs past the scene, which clips them

function placer(piece) {
    piece.el.style.left = (piece.x * 100).toFixed(3) + '%';
    piece.el.style.top = (piece.y * 100).toFixed(3) + '%';
    piece.el.style.width = (piece.w * 100).toFixed(3) + '%';
    ancrer(piece);
}

/** Handles follow the piece, but never past the scene: overflow would clip them away. */
function ancrer(piece) {
    const cadre = scene.getBoundingClientRect();
    const boite = piece.el.getBoundingClientRect();
    const bords = {
        left: Math.max(-DEMI_POIGNEE, cadre.left - boite.left + MARGE_BORD),
        right: Math.max(-DEMI_POIGNEE, boite.right - cadre.right + MARGE_BORD),
        top: Math.max(-DEMI_POIGNEE, cadre.top - boite.top + MARGE_BORD),
        bottom: Math.max(-DEMI_POIGNEE, boite.bottom - cadre.bottom + MARGE_BORD),
    };

    for (const [coin, poignee] of Object.entries(piece.poignees)) {
        const [vertical, horizontal] = coin.split('-');
        poignee.style[vertical] = bords[vertical] + 'px';
        poignee.style[horizontal] = bords[horizontal] + 'px';
    }
}

/** The montage is composed server side: the form carries the geometry, not the pixels. */
function synchroniser() {
    champCalques.value = JSON.stringify(pieces.map((piece) => ({
        o: piece.slug,
        x: Number(piece.x.toFixed(4)),
        y: Number(piece.y.toFixed(4)),
        w: Number(piece.w.toFixed(4)),
    })));

    const source = champCapture.value !== '' || champFichier.files.length > 0;
    prendre.disabled = !cameraPrete || pieces.length === 0;
    enregistrer.disabled = pieces.length === 0 || !source;
}

function redimensionner(piece, facteur) {
    piece.w = borne(piece.w * facteur, LARGEUR_MIN, LARGEUR_MAX);
    placer(piece);
    synchroniser();
}

function saisir(piece, depart, mode) {
    if (gesteEnCours) {
        return;
    }
    gesteEnCours = true;

    const boite = scene.getBoundingClientRect();
    const prise = {
        x: piece.x - (depart.clientX - boite.left) / boite.width,
        y: piece.y - (depart.clientY - boite.top) / boite.height,
        w: piece.w,
        rayon: Math.hypot(depart.clientX - (boite.left + piece.x * boite.width),
                          depart.clientY - (boite.top + piece.y * boite.height)),
    };

    const suivre = (evenement) => {
        if (mode === 'taille') {
            const rayon = Math.hypot(
                evenement.clientX - (boite.left + piece.x * boite.width),
                evenement.clientY - (boite.top + piece.y * boite.height));
            piece.w = borne(prise.w * rayon / Math.max(prise.rayon, 1),
                            LARGEUR_MIN, LARGEUR_MAX);
        } else {
            piece.x = borne(prise.x + (evenement.clientX - boite.left) / boite.width, 0, 1);
            piece.y = borne(prise.y + (evenement.clientY - boite.top) / boite.height, 0, 1);
        }
        placer(piece);
    };

    // Listeners on the window, and both event families: pointer capture behaves
    // unevenly across browsers, and pointer events can be turned off outright.
    // gesteEnCours keeps the duplicated mouse events from starting a second drag.
    const lacher = () => {
        for (const type of ['pointermove', 'mousemove']) {
            window.removeEventListener(type, suivre);
        }
        for (const type of ['pointerup', 'pointercancel', 'mouseup']) {
            window.removeEventListener(type, lacher);
        }
        piece.el.classList.remove('grabbed');
        gesteEnCours = false;
        synchroniser();
    };

    piece.el.classList.add('grabbed');
    for (const type of ['pointermove', 'mousemove']) {
        window.addEventListener(type, suivre);
    }
    for (const type of ['pointerup', 'pointercancel', 'mouseup']) {
        window.addEventListener(type, lacher);
    }
}

/** One square per corner: they show the piece can be resized, and do the resizing. */
function poignees(element) {
    const carres = {};
    for (const coin of ['top-left', 'top-right', 'bottom-left', 'bottom-right']) {
        const carre = document.createElement('span');
        carre.className = 'handle handle-' + coin;
        carres[coin] = carre;
        element.append(carre);
    }
    return carres;
}

function marquer(choix, pose) {
    choix.classList.toggle('chosen', pose);
    choix.setAttribute('aria-pressed', pose ? 'true' : 'false');
}

function retirer(piece) {
    pieces.splice(pieces.indexOf(piece), 1);
    piece.el.remove();
    marquer(piece.choix, false);
    synchroniser();
}

function ajouter(slug, url, choix) {
    const element = document.createElement('div');
    element.className = 'piece';

    const image = document.createElement('img');
    image.src = url;
    image.alt = '';
    image.draggable = false;
    element.append(image);

    conteneur.append(element);

    const decalage = (pieces.length % 4 - 1.5) * 0.06;
    const piece = {
        slug, choix, el: element, poignees: poignees(element),
        x: 0.5 + decalage, y: 0.5 + decalage, w: LARGEUR_DEFAUT,
    };
    pieces.push(piece);
    placer(piece);
    // the height is unknown until the overlay is decoded: anchor the handles again
    image.addEventListener('load', () => placer(piece), { once: true });
    marquer(choix, true);
    synchroniser();

    for (const type of ['pointerdown', 'mousedown']) {
        element.addEventListener(type, (evenement) => {
            if (evenement.button > 0) {
                return;
            }
            evenement.preventDefault();
            saisir(piece, evenement,
                   evenement.target.classList.contains('handle') ? 'taille' : 'deplacer');
        });
    }

    // Firefox starts its native image drag on pointerdown, which kills the gesture
    element.addEventListener('dragstart', (evenement) => evenement.preventDefault());

    // the page must not scroll while the wheel resizes a piece
    element.addEventListener('wheel', (evenement) => {
        evenement.preventDefault();
        redimensionner(piece, evenement.deltaY < 0 ? PAS_MOLETTE : 1 / PAS_MOLETTE);
    }, { passive: false });
}

function afficher(source) {
    apercu.src = source;
    apercu.hidden = false;
    flux.hidden = true;
    // the source is on screen: the missing-camera notice has nothing left to say
    etat.hidden = true;
    prendre.hidden = true;
    reprendre.hidden = false;
}

function direct() {
    apercu.hidden = true;
    apercu.removeAttribute('src');
    flux.hidden = false;
    etat.hidden = etat.textContent === '';
    prendre.hidden = false;
    reprendre.hidden = true;
    champCapture.value = '';
    champFichier.value = '';
    synchroniser();
}

document.querySelectorAll('.filter-choice').forEach((choix) => {
    marquer(choix, false);
    choix.addEventListener('click', () => {
        const deja = posee(choix.dataset.overlay);
        if (deja) {
            retirer(deja);
        } else {
            ajouter(choix.dataset.overlay, choix.dataset.url, choix);
        }
    });
});

prendre.addEventListener('click', () => {
    const toile = document.createElement('canvas');
    toile.width = flux.videoWidth;
    toile.height = flux.videoHeight;

    // the preview is mirrored: the shot has to match what was aimed at
    const pinceau = toile.getContext('2d');
    pinceau.translate(toile.width, 0);
    pinceau.scale(-1, 1);
    pinceau.drawImage(flux, 0, 0);

    champCapture.value = toile.toDataURL('image/jpeg', 0.9);
    champFichier.value = '';
    afficher(champCapture.value);
    synchroniser();
});

reprendre.addEventListener('click', direct);

champFichier.addEventListener('change', () => {
    const fichier = champFichier.files[0];
    if (!fichier) {
        direct();
        return;
    }
    champCapture.value = '';
    afficher(URL.createObjectURL(fichier));
    synchroniser();
});

function sansCamera() {
    etat.hidden = false;
    etat.textContent = 'No camera available: pick an image file instead.';
    flux.hidden = true;
    prendre.hidden = true;
}

// getUserMedia is missing outside a secure context, https or localhost
const demande = navigator.mediaDevices?.getUserMedia({
    video: { width: { ideal: 1280 }, height: { ideal: 960 } },
});

if (demande === undefined) {
    sansCamera();
} else {
    demande.then((camera) => {
        flux.srcObject = camera;
        flux.play();
        // shooting before the metadata arrives would grab a canvas of zero pixels
        flux.addEventListener('loadedmetadata', () => {
            cameraPrete = true;
            synchroniser();
        }, { once: true });
    }).catch(sansCamera);
}
