# CSS

## 1 : Cascade

Quand plusieurs déclarations visent la même propriété d'un même élément, le
navigateur retient celle qui gagne selon ces critères, dans l'ordre :

1. **importance** : une déclaration `!important` l'emporte sur les autres ;
2. **origine** : styles de l'auteur du site, puis de l'utilisateur, puis du
   navigateur ;
3. **attribut `style`** : une déclaration en ligne l'emporte sur toute règle à
   sélecteur ;
4. **couche** (`@layer`) : les styles hors couche l'emportent sur les couches,
   et entre couches la dernière déclarée gagne ; l'ordre s'inverse pour
   `!important` ;
5. **spécificité** du sélecteur ;
6. **ordre d'apparition** : à égalité, la dernière déclaration lue gagne,
   fichier par fichier dans l'ordre des `<link>`.

## 2 : Spécificité

La spécificité est un triplet comparé de gauche à droite :

| Colonne | Compte |
|---------|--------|
| A | sélecteurs d'identifiant `#x` |
| B | classes `.x`, attributs `[x]`, pseudo-classes `:hover` |
| C | types `div`, pseudo-éléments `::before` |

| Sélecteur | Spécificité |
|-----------|-------------|
| `p` | 0-0-1 |
| `.carte p` | 0-1-1 |
| `.carte .titre:hover` | 0-3-0 |
| `#menu a` | 1-0-1 |
| `:where(.carte) p` | 0-0-1 |
| `:is(#menu, .nav) a` | 1-0-1 |

`*` et les combinateurs (espace, `>`, `+`, `~`) comptent 0. `:where()` compte
toujours 0, ce qui en fait un outil pour écrire des styles par défaut faciles à
remplacer. `:is()`, `:not()` et `:has()` prennent la spécificité de leur
argument le plus spécifique.

## 3 : Héritage

| Propriétés héritées | Propriétés non héritées |
|---------------------|-------------------------|
| `color`, `font-*`, `line-height`, `text-align`, `letter-spacing`, `visibility`, `cursor`, `list-style`, variables CSS | `margin`, `padding`, `border`, `background`, `width`, `height`, `display`, `position` |

| Mot-clé | Valeur obtenue |
|---------|----------------|
| `inherit` | celle du parent |
| `initial` | la valeur initiale de la spécification (`display: inline` pour tout élément) |
| `unset` | `inherit` pour une propriété héritée, `initial` sinon |
| `revert` | celle de la feuille du navigateur (`display: block` pour une `div`) |

## 4 : Modèle de boîte

Une boîte se compose, de l'intérieur vers l'extérieur, du contenu, de la marge
intérieure (`padding`), de la bordure (`border`) et de la marge extérieure
(`margin`).

`width` désigne par défaut la largeur du contenu seul (`box-sizing:
content-box`) : une boîte de `width: 200px` avec `padding: 20px` occupe 240
pixels. `box-sizing: border-box` inclut `padding` et `border` dans `width` ;
la règle suivante l'applique partout :

```css
*, *::before, *::after { box-sizing: border-box; }
```

Les marges verticales de deux blocs voisins fusionnent : `margin-bottom: 20px`
suivi de `margin-top: 30px` donne un écart de 30 pixels. La fusion n'a pas lieu
entre les enfants d'un conteneur flex ou grid.

## 5 : Affichage

| `display` | Comportement |
|-----------|--------------|
| `block` | occupe toute la largeur, commence sur une nouvelle ligne |
| `inline` | dans le flux du texte ; `width`, `height` et marges verticales sans effet |
| `inline-block` | dans le texte, mais dimensionnable |
| `flex`, `grid` | conteneur dont les enfants sont placés par flexbox ou grid |
| `none` | retiré de l'affichage et de l'arbre d'accessibilité |

L'attribut HTML `hidden` agit par la règle `[hidden] { display: none }` de la
feuille du navigateur. Une règle de l'auteur comme `.menu { display: flex }`
l'emporte sur elle : l'élément reste visible. La règle suivante rétablit
`hidden` :

```css
[hidden] { display: none !important; }
```

## 6 : Flexbox

Flexbox place les enfants d'un conteneur sur une ligne ou une colonne.

| Propriété du conteneur | Effet |
|------------------------|-------|
| `flex-direction` | axe principal : `row` (défaut) ou `column` |
| `justify-content` | répartition sur l'axe principal : `flex-start`, `center`, `space-between` |
| `align-items` | alignement sur l'axe perpendiculaire : `stretch` (défaut), `center`, `flex-start` |
| `flex-wrap: wrap` | passage à la ligne quand la place manque |
| `gap` | espace entre les enfants |

| Propriété d'un enfant | Effet |
|-----------------------|-------|
| `flex: 1` | prend sa part de l'espace libre (abrégé de `flex-grow`, `flex-shrink`, `flex-basis`) |
| `flex: none` | garde sa taille |
| `align-self` | alignement propre sur l'axe perpendiculaire |
| `order` | position d'affichage ; l'ordre de lecture au clavier reste celui du HTML |

Un enfant flex ne rétrécit pas en dessous de la largeur de son contenu
(`min-width: auto`). Un texte long ou une image large débordent alors du
conteneur ; `min-width: 0` sur l'enfant autorise le rétrécissement.

## 7 : Grid

Grid place les enfants dans une grille à deux dimensions.

```css
.galerie {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}
```

| Élément | Sens |
|---------|------|
| `fr` | part de l'espace restant |
| `repeat(n, taille)` | répète une piste |
| `auto-fill` | autant de colonnes que la largeur permet |
| `minmax(a, b)` | piste entre `a` et `b` |
| `grid-column: 1 / 3` | l'enfant s'étend de la ligne 1 à la ligne 3 |
| `grid-template-areas` | grille décrite par des noms de zones |
| `place-items: center` | centre chaque enfant dans sa cellule |

## 8 : Unités et fonctions

| Unité | Référence |
|-------|-----------|
| `px` | pixel CSS |
| `rem` | taille de police de l'élément racine (16 px par défaut) |
| `em` | taille de police de l'élément ; se multiplie d'un niveau à l'autre |
| `%` | dimension du parent (largeur pour `padding` et `margin`, même verticaux) |
| `vw`, `vh` | 1 % de la largeur, de la hauteur de la fenêtre |
| `dvh`, `svh`, `lvh` | hauteur de fenêtre dynamique, petite, grande |
| `ch` | largeur du caractère `0` de la police |

Sur mobile, `100vh` correspond à la fenêtre sans barre d'adresse : un bloc de
cette hauteur passe sous la barre quand elle est affichée. `100dvh` suit la
hauteur réellement visible.

| Fonction | Résultat |
|----------|----------|
| `calc(100% - 2rem)` | calcul entre unités différentes |
| `min(a, b)`, `max(a, b)` | la plus petite, la plus grande valeur |
| `clamp(min, idéal, max)` | `idéal` borné : `font-size: clamp(1rem, 2.5vw, 2rem)` |

## 9 : Variables CSS

```css
:root {
    --couleur-texte: #222;
    --espace: 1rem;
}

.carte {
    color: var(--couleur-texte);
    padding: var(--espace, 16px);    /* 16px si --espace n'est pas définie */
}
```

Une variable (propriété personnalisée) s'hérite comme `color` : définie sur
`:root`, elle vaut pour toute la page ; redéfinie sur un élément, elle change
pour ses descendants. Une redéfinition dans une requête média modifie toutes les
règles qui l'utilisent.

Un fichier qui ne contient que ces définitions (couleurs, espacements, tailles)
regroupe les *design tokens* du site.

JavaScript lit et écrit les variables :

```js
element.style.setProperty('--decalage', 120 + 'px');
getComputedStyle(element).getPropertyValue('--decalage');
```

Une variable dont la valeur ne convient pas à la propriété (`width: var(--couleur-texte)`)
rend la déclaration invalide au moment du calcul : la propriété prend sa valeur
héritée ou initiale, et la déclaration précédente pour cette propriété est
perdue.

## 10 : Requêtes média

Approche *mobile-first* : les styles de base visent l'écran étroit, et des
requêtes `min-width` ajoutent la mise en page large.

```css
.colonnes { display: block; }

@media (min-width: 768px) {
    .colonnes { display: grid; grid-template-columns: 1fr 1fr; }
}
```

| Requête | Condition |
|---------|-----------|
| `(min-width: 768px)`, `(width >= 768px)` | fenêtre d'au moins 768 px |
| `(prefers-reduced-motion: reduce)` | l'utilisateur a demandé moins d'animations dans son système |
| `(prefers-color-scheme: dark)` | thème sombre du système |
| `(hover: hover)` | le pointeur principal peut survoler (souris) |
| `(pointer: coarse)` | le pointeur principal est imprécis (doigt) |

```css
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
```

Une durée très courte conserve les événements `animationend` et
`transitionend` dont un script peut dépendre ; `animation: none` les supprime.

## 11 : Positionnement et empilement

| `position` | Référence de `top`, `left`… |
|------------|-----------------------------|
| `static` | aucune ; valeur par défaut |
| `relative` | sa propre position normale ; la place d'origine reste réservée |
| `absolute` | le plus proche ancêtre positionné (autre que `static`) ; retiré du flux |
| `fixed` | la fenêtre |
| `sticky` | se comporte en `relative`, puis reste collé au seuil `top` pendant le défilement de son conteneur |

`sticky` exige un seuil (`top: 0`) et colle à l'intérieur du plus proche ancêtre
qui défile : un ancêtre en `overflow: hidden` ou `auto` devient ce conteneur, et
l'élément ne colle plus à la fenêtre.

`z-index` ordonne les éléments à l'intérieur d'un même contexte d'empilement.
Un nouveau contexte se crée notamment avec `position` et `z-index`, `opacity`
inférieure à 1, `transform`, `filter` ou `isolation: isolate`. Un enfant en
`z-index: 9999` reste sous un élément voisin de son parent si le contexte du
parent est plus bas.

## 12 : Sélecteurs utiles

| Sélecteur | Vise |
|-----------|------|
| `.carte:has(img)` | une carte qui contient une image (sélecteur de parent) |
| `:is(h1, h2, h3) a` | les liens dans un titre, en une règle |
| `:not(.actif)` | les éléments sans la classe |
| `:focus-visible` | l'élément focalisé au clavier ; un clic de souris ne le déclenche pas sur un bouton |
| `:focus-within` | un élément dont un descendant a le focus |
| `:nth-child(2n of .visible)` | un sur deux parmi les éléments `.visible` |
| `a + b`, `a ~ b` | frère suivant immédiat, frères suivants |
| `[href^="https"]`, `[src$=".svg"]` | attribut qui commence, qui finit par |
| `::before`, `::after` | contenu ajouté, affiché seulement avec une propriété `content` |

## 13 : Transitions et animations

```css
.bouton { transition: transform 200ms ease-out; }
.bouton:hover { transform: scale(1.05); }

@keyframes sortie {
    to { transform: translateX(var(--sortie)); opacity: 0; }
}
.joue { animation: sortie 600ms ease-in forwards; }
```

`forwards` garde l'état de la dernière image après la fin de l'animation.
`transform` et `opacity` s'animent sans recalcul de la mise en page ; animer
`width`, `top` ou `margin` recalcule la page à chaque image.

## 14 : Techniques

Texte réservé aux lecteurs d'écran, invisible à l'écran :

```css
.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
}
```

`display: none` et `visibility: hidden` retirent aussi l'élément de l'arbre
d'accessibilité ; cette classe le garde lisible.

Menu sans JavaScript, piloté par une case à cocher :

```html
<input type="checkbox" id="burger" class="hamburger">
<label for="burger">Menu</label>
<nav class="menu">...</nav>
```

```css
.hamburger { position: absolute; opacity: 0; }
.menu { display: none; }
.hamburger:checked ~ .menu { display: block; }
```

Le `<label>` coche la case, et le sélecteur `~` affiche le menu qui la suit.
L'état ouvert ne se ferme ni par Échap ni par un clic hors du menu.

Image recadrée sans déformation dans un cadre de proportions fixes :

```css
.vignette { aspect-ratio: 4 / 3; width: 100%; object-fit: cover; }
```

Image adaptée à l'écran :

```html
<picture>
    <source media="(max-width: 767px)" srcset="petit.svg">
    <img src="grand.svg" alt="...">
</picture>
```

Le navigateur prend la première `<source>` dont la condition est vraie, sinon
l'`<img>`. Le `alt` et les styles s'écrivent sur l'`<img>`.

Propriétés logiques : `margin-inline` (gauche et droite en écriture
horizontale), `padding-block` (haut et bas), `inset: 0` (`top`, `right`,
`bottom`, `left` à 0).

## 15 : Débogage

- Onglet « Calculé » des outils de développement : valeur finale de chaque
  propriété et règle qui l'a fournie.
- Règles barrées dans l'onglet « Styles » : déclarations perdantes de la
  cascade.
- `* { outline: 1px solid red; }` montre toutes les boîtes sans modifier la
  mise en page (`outline` n'occupe pas de place).
