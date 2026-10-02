# Événements du navigateur

Un événement est un objet que le navigateur crée lorsqu'il se produit quelque
chose dans la page (clic, frappe, chargement, fin d'animation) et qu'il
transmet aux fonctions enregistrées pour ce type d'événement sur l'élément
concerné ou ses ancêtres.

## 1 : Écouter

```js
element.addEventListener('click', gerer, options);

function gerer(evenement) {
    // evenement : objet Event décrivant ce qui s'est produit
}
```

| Option | Effet |
|--------|-------|
| `once: true` | la fonction est retirée après son premier appel |
| `passive: true` | la fonction promet de ne pas appeler `preventDefault()` ; le navigateur peut faire défiler la page sans l'attendre |
| `capture: true` | la fonction est appelée pendant la phase de capture (section 2) |
| `signal` | un `AbortController` retire la fonction : `controleur.abort()` |

`removeEventListener(type, fonction, { capture })` retire une fonction à
condition de recevoir la même référence de fonction et la même valeur de
`capture`. Une fonction anonyme écrite directement dans `addEventListener` ne
peut donc pas être retirée ; `signal` couvre ce cas :

```js
const controleur = new AbortController();
window.addEventListener('pointermove', suivre, { signal: controleur.signal });
window.addEventListener('keydown', touche, { signal: controleur.signal });
controleur.abort();     // retire les deux
```

Les autres formes d'enregistrement :

| Forme | Particularité |
|-------|---------------|
| `element.onclick = f` | une seule fonction par type ; une nouvelle affectation remplace la précédente |
| attribut HTML `onclick="..."` | code exécuté depuis une chaîne ; une politique CSP sans `'unsafe-inline'` le bloque |

## 2 : Propagation

Un événement traverse l'arbre du document en trois phases :

1. **capture** : de `window` jusqu'au parent de la cible ;
2. **cible** : sur l'élément où l'événement s'est produit ;
3. **remontée** (*bubbling*) : de la cible jusqu'à `window`.

Une fonction enregistrée sans `capture` est appelée pendant la phase de cible ou
de remontée. Un clic sur un `<span>` dans un `<button>` dans un `<form>` appelle
donc les fonctions du `<span>`, puis du `<button>`, puis du `<form>`, puis de
`document`.

| Propriété | Valeur |
|-----------|--------|
| `evenement.target` | l'élément d'origine, le plus profond |
| `evenement.currentTarget` | l'élément qui porte la fonction en cours d'appel |
| `evenement.eventPhase` | 1 capture, 2 cible, 3 remontée |
| `evenement.bubbles` | vrai si l'événement remonte |

`stopPropagation()` arrête le trajet après l'élément courant.
`stopImmediatePropagation()` arrête aussi les autres fonctions du même élément.

Événements qui ne remontent pas, avec leur équivalent qui remonte :

| Ne remonte pas | Remonte |
|----------------|---------|
| `focus`, `blur` | `focusin`, `focusout` |
| `mouseenter`, `mouseleave` | `mouseover`, `mouseout` |
| `pointerenter`, `pointerleave` | `pointerover`, `pointerout` |
| `invalid` | écouter en capture sur le formulaire |
| `load`, `error` d'une image | écouter en capture sur un ancêtre |
| `scroll` d'un élément | |

Dans une fonction déclarée par `function`, `this` vaut `currentTarget`. Une
fonction fléchée garde le `this` du code qui l'a créée.

## 3 : Délégation

Une seule fonction, placée sur un ancêtre, traite les événements de tous ses
descendants grâce à la remontée :

```js
liste.addEventListener('click', (evenement) => {
    const bouton = evenement.target.closest('.supprimer');
    if (!bouton || !liste.contains(bouton)) {
        return;
    }
    // traiter le bouton
});
```

La fonction couvre aussi les éléments ajoutés après son enregistrement, par
exemple des cartes chargées en JavaScript. `closest()` remonte de la cible au
premier ancêtre qui correspond au sélecteur, ce qui couvre un clic sur une icône
placée dans le bouton.

## 4 : Action par défaut

Beaucoup d'événements déclenchent une action du navigateur après l'appel des
fonctions : suivre un lien, envoyer un formulaire, cocher une case, ouvrir le
menu contextuel, faire défiler la page, insérer le caractère tapé.
`preventDefault()` annule cette action, si `evenement.cancelable` est vrai.
`evenement.defaultPrevented` indique qu'une fonction l'a déjà annulée.

```js
form.addEventListener('submit', (evenement) => {
    evenement.preventDefault();     // pas d'envoi ni de changement de page
    // envoi par fetch()
});
```

`preventDefault()` et `stopPropagation()` agissent séparément : le premier
annule l'action par défaut, le second interrompt le trajet de l'événement.

Chrome et Firefox rendent passives par défaut les fonctions `touchstart`,
`touchmove` et `wheel` enregistrées sur `window`, `document` ou `body` ;
`preventDefault()` y est ignoré, avec un avertissement dans la console.
`{ passive: false }` rétablit l'annulation.

## 5 : Ordre des événements

Clic de souris :

```
pointerdown → mousedown → focus → pointerup → mouseup → click
```

`click` est émis sur l'ancêtre commun le plus proche des cibles de
`pointerdown` et `pointerup`. Un clic au clavier (Entrée ou Espace sur un
bouton) émet `click` sans les événements de pointeur.

Frappe d'un caractère dans un champ :

```
keydown → beforeinput → input → keyup
```

`keypress`, entre `keydown` et `beforeinput`, est obsolète.

Chargement d'une page :

```
analyse du HTML → scripts defer et modules → DOMContentLoaded → images, styles → load
```

Un script `defer` ou `type="module"` s'exécute quand l'arbre est complet : il
trouve tous les éléments sans attendre `DOMContentLoaded`.

## 6 : Catalogue

| Famille | Événements |
|---------|------------|
| pointeur | `pointerdown`, `pointermove`, `pointerup`, `pointercancel`, `pointerover`, `pointerout`, `pointerenter`, `pointerleave` |
| souris | `click`, `dblclick`, `auxclick` (bouton du milieu), `contextmenu`, `mousedown`, `mouseup`, `mousemove`, `wheel` |
| clavier | `keydown`, `keyup` |
| focus | `focus`, `blur`, `focusin`, `focusout` |
| formulaire | `input`, `change`, `beforeinput`, `submit`, `reset`, `invalid`, `formdata`, `select` |
| document | `DOMContentLoaded`, `load`, `visibilitychange`, `pagehide`, `pageshow`, `beforeunload` |
| fenêtre | `resize`, `scroll`, `online`, `offline`, `hashchange`, `popstate`, `storage` |
| média | `loadedmetadata`, `canplay`, `play`, `pause`, `ended` |
| animation | `transitionend`, `animationstart`, `animationend`, `animationiteration` |
| presse-papiers | `copy`, `cut`, `paste` |
| glisser-déposer | `dragstart`, `dragover`, `drop`, `dragend` |

Précisions utiles :

- `transitionend` est émis une fois par propriété animée.
- `dragover` doit appeler `preventDefault()` pour que l'élément accepte un
  `drop`.
- `visibilitychange` signale qu'un onglet passe au premier ou à l'arrière-plan ;
  `pagehide` est le dernier événement fiable avant de quitter la page.
- `storage` est émis dans les autres onglets du même site quand l'un d'eux
  modifie `localStorage`.
- `resize` n'existe que sur `window`.

## 7 : Propriétés de l'objet événement

| Type | Propriétés |
|------|------------|
| tous | `type`, `target`, `currentTarget`, `timeStamp`, `isTrusted` (faux pour un événement créé par script) |
| souris, pointeur | `clientX`, `clientY` (fenêtre), `pageX`, `pageY` (document), `offsetX`, `offsetY` (élément), `button` (bouton changé), `buttons` (boutons enfoncés) |
| pointeur | `pointerId`, `pointerType` (`mouse`, `pen`, `touch`), `isPrimary`, `pressure` |
| clavier | `key` (caractère produit : `'a'`, `'Enter'`), `code` (touche physique : `'KeyA'`), `repeat`, `isComposing` |
| modificateurs | `ctrlKey`, `shiftKey`, `altKey`, `metaKey` |

`key` suit la disposition du clavier ; `code` désigne la position physique de
la touche, nommée d'après le clavier QWERTY : sur un clavier AZERTY, la touche A
envoie `key: 'a'` et `code: 'KeyQ'`.

## 8 : Pointer Events

Les Pointer Events unifient souris, stylet et doigt. Un glisser se construit
ainsi :

```js
piece.addEventListener('pointerdown', (evenement) => {
    piece.setPointerCapture(evenement.pointerId);
});
piece.addEventListener('pointermove', (evenement) => {
    if (!piece.hasPointerCapture(evenement.pointerId)) {
        return;
    }
    // déplacer selon evenement.clientX, evenement.clientY
});
piece.addEventListener('pointerup', (evenement) => {
    piece.releasePointerCapture(evenement.pointerId);
});
```

`setPointerCapture()` envoie tous les événements de ce pointeur à l'élément,
même quand le pointeur en sort. Sans capture, un mouvement rapide fait sortir le
pointeur de l'élément et les `pointermove` partent ailleurs ; l'autre solution
consiste à écouter `pointermove` et `pointerup` sur `window` le temps du geste.

`pointercancel` est émis quand le navigateur reprend le geste pour lui-même,
typiquement un défilement ou un zoom au doigt. La propriété CSS
`touch-action: none` sur l'élément lui réserve tous les gestes tactiles.

## 9 : Événements créés par script

```js
const evenement = new CustomEvent('panier:ajout', {
    detail: { id: 12 },
    bubbles: true,
});
element.dispatchEvent(evenement);
```

`dispatchEvent()` est synchrone : toutes les fonctions sont appelées avant son
retour, et il renvoie `false` si l'une d'elles a appelé `preventDefault()`
(avec `cancelable: true`).

`element.click()` produit un `click` complet, action par défaut comprise.
Modifier `champ.value` par script n'émet ni `input` ni `change`.

## 10 : Observateurs

Trois API signalent des changements par une fonction de rappel :

| API | Signale |
|-----|---------|
| `IntersectionObserver` | un élément entre dans la zone visible ou en sort |
| `ResizeObserver` | un élément change de taille |
| `MutationObserver` | l'arbre du document change : ajout, retrait, attribut |

```js
const observateur = new IntersectionObserver((entrees) => {
    for (const entree of entrees) {
        if (entree.isIntersecting) {
            charger();
        }
    }
}, { rootMargin: '0px 0px 400px 0px' });

observateur.observe(sentinelle);
```

`rootMargin` agrandit la zone observée : ici, le rappel part 400 pixels avant
que l'élément n'apparaisse. `threshold` fixe la part visible qui déclenche le
rappel (0 par défaut, dès le premier pixel). `unobserve(element)` et
`disconnect()` arrêtent l'observation.

## 11 : Fréquence

`input`, `pointermove`, `scroll` et `resize` peuvent être émis des dizaines de
fois par seconde.

Attente de fin de saisie (*debounce*) : l'action part après une pause.

```js
let minuteur;
champ.addEventListener('input', () => {
    clearTimeout(minuteur);
    minuteur = setTimeout(verifier, 400);
});
```

Une action par image affichée (*throttle* par `requestAnimationFrame`) : les
événements reçus entre deux images n'en déclenchent qu'une.

```js
let prevu = false;
window.addEventListener('scroll', () => {
    if (prevu) {
        return;
    }
    prevu = true;
    requestAnimationFrame(() => {
        prevu = false;
        mettreAJour();
    });
}, { passive: true });
```
