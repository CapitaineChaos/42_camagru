# Formulaires HTML

## 1 : Envoi

### Bouton par défaut

Un `<button>` placé dans un formulaire sans attribut `type` est un bouton
d'envoi : `type="submit"` est sa valeur par défaut. Un bouton qui doit seulement
déclencher un script porte `type="button"`.

Le bouton par défaut d'un formulaire est son premier bouton d'envoi dans l'ordre
du document.

### Envoi implicite

La touche Entrée dans un champ texte envoie le formulaire :

- si le formulaire a un bouton d'envoi, le navigateur émet un `click` sur le
  bouton par défaut, puis l'envoi suit ;
- s'il n'en a pas, l'envoi n'a lieu que si le formulaire contient un seul champ
  de saisie sur une ligne.

Un bouton par défaut désactivé (`disabled`) bloque l'envoi implicite.

### Attributs du bouton

Un bouton d'envoi peut remplacer les attributs du formulaire pour son propre
envoi :

| Attribut du bouton | Remplace |
|--------------------|----------|
| `formaction` | `action` |
| `formmethod` | `method` |
| `formenctype` | `enctype` |
| `formnovalidate` | `novalidate` : envoi sans validation |
| `formtarget` | `target` |

Deux boutons d'un même formulaire peuvent ainsi viser deux routes.

Seul le bouton qui a déclenché l'envoi transmet son `name` et sa `value`. Un
même formulaire distingue ainsi plusieurs actions :

```html
<button name="action" value="enregistrer">Enregistrer</button>
<button name="action" value="publier">Publier</button>
```

### Champ hors du formulaire

L'attribut `form="id"` rattache un champ ou un bouton à un formulaire dont il
n'est pas descendant, par exemple un bouton placé dans une autre partie de la
page.

### Envoi par script

| Méthode | Validation | Événement `submit` |
|---------|------------|--------------------|
| `form.requestSubmit(bouton)` | oui | oui |
| `form.submit()` | non | non |

`requestSubmit()` se comporte comme un clic sur le bouton passé en argument.
`form.submit()` envoie directement : les fonctions qui écoutent `submit` ne sont
pas appelées.

Dans une fonction `submit`, `evenement.submitter` désigne le bouton qui a
déclenché l'envoi, ou `null` pour un envoi par `requestSubmit()` sans argument.

## 2 : Données envoyées

Un champ est transmis s'il a un attribut `name` et s'il n'est pas désactivé.

| Champ | Transmis |
|-------|----------|
| texte, `hidden`, `password`, `textarea` | toujours, même vide (`nom=`) |
| case à cocher | seulement cochée ; valeur `on` sans attribut `value` |
| bouton radio | seulement celui qui est coché |
| `<select>` | la `value` de l'option choisie, ou son texte si l'option n'a pas de `value` |
| `<select multiple>` | une entrée par option choisie |
| `readonly` | oui |
| `disabled` | non |
| bouton d'envoi | seulement celui qui a déclenché l'envoi |
| `<input type="image">` | `nom.x` et `nom.y`, coordonnées du clic |
| fichier | contenu en `multipart/form-data` ; seulement le nom de fichier dans les autres encodages |

`<fieldset disabled>` désactive tous les champs qu'il contient, sauf ceux de sa
première `<legend>`.

Une case décochée n'envoie rien. Pour recevoir une valeur dans les deux cas, un
champ caché du même nom la précède ; PHP garde la dernière valeur reçue pour un
nom donné :

```html
<input type="hidden" name="actif" value="0">
<input type="checkbox" name="actif" value="1">
```

Un nom terminé par `[]` (`nom[]`) fait arriver un tableau dans PHP : c'est la
forme à donner à un `<select multiple>` ou à une série de cases.

### Encodage du corps

| `enctype` | Corps |
|-----------|-------|
| `application/x-www-form-urlencoded` (défaut) | `nom=valeur&autre=valeur`, valeurs encodées |
| `multipart/form-data` | une partie par champ ; seul format qui transporte des fichiers |
| `text/plain` | `nom=valeur` par ligne, sans encodage ; à éviter |

`method="dialog"` dans un `<dialog>` ferme le dialogue sans requête ;
`dialog.returnValue` reçoit la `value` du bouton.

## 3 : `FormData`

```js
const donnees = new FormData(form, evenement.submitter);
```

`FormData` lit les champs comme le ferait un envoi, selon les règles de la
section 2. Le second argument ajoute le bouton qui a déclenché l'envoi.

| Méthode | Effet |
|---------|-------|
| `get(nom)`, `getAll(nom)` | première valeur, toutes les valeurs |
| `set(nom, valeur)` | remplace les valeurs du nom |
| `append(nom, valeur)` | ajoute une valeur, même si le nom existe |
| `delete(nom)`, `has(nom)` | retire, teste |
| `append(nom, blob, 'fichier.png')` | ajoute un fichier |

Conversions :

```js
Object.fromEntries(donnees)          // objet ; la dernière valeur de chaque nom
new URLSearchParams(donnees)         // corps urlencoded, sans fichier
```

L'événement `formdata` est émis sur le formulaire quand un `FormData` est
construit à partir de lui, y compris lors d'un envoi classique.
`evenement.formData.append(...)` y ajoute des valeurs calculées.

## 4 : Validation intégrée

### Contraintes

| Attribut | Contrainte | Propriété de `validity` en échec |
|----------|------------|---------------------------------|
| `required` | valeur non vide | `valueMissing` |
| `type="email"`, `type="url"` | format | `typeMismatch` |
| `pattern` | expression | `patternMismatch` |
| `minlength`, `maxlength` | longueur | `tooShort`, `tooLong` |
| `min`, `max` | bornes (nombres, dates) | `rangeUnderflow`, `rangeOverflow` |
| `step` | pas (`step="0.01"`, `step="any"`) | `stepMismatch` |
| saisie illisible dans un champ `number` ou `date` | | `badInput` |
| `setCustomValidity(message)` | message non vide | `customError` |

Règles moins connues :

- `pattern` s'applique à la valeur entière, sans `^` ni `$`. Les navigateurs le
  compilent avec le drapeau `v` : un `-` littéral dans une classe s'écrit `\-`,
  et une expression invalide est ignorée avec un avertissement dans la console.
  Une valeur vide n'est vérifiée que par `required`.
- `maxlength` empêche de taper au-delà de la limite. `minlength` et `maxlength`
  ne signalent une erreur que pour une valeur modifiée par l'utilisateur : une
  valeur trop courte écrite dans le HTML ou par script passe.
- Les longueurs se comptent en unités UTF-16 : un émoji compte souvent pour 2.
- Pour un groupe de boutons radio, un seul `required` suffit.
- Les champs `disabled`, `readonly` et `hidden` échappent à la validation.
- `novalidate` sur le formulaire, ou `formnovalidate` sur un bouton, désactive
  la validation à l'envoi ; les propriétés `validity` restent calculées.

### API de validation

| Membre | Rôle |
|--------|------|
| `champ.validity` | objet `ValidityState` : une propriété booléenne par contrainte (tableau ci-dessus), plus `valid` |
| `champ.validationMessage` | message que le navigateur afficherait, dans la langue du navigateur |
| `champ.willValidate` | faux pour un champ exclu de la validation |
| `checkValidity()` | renvoie `true` ou `false` et émet `invalid` sur chaque champ en échec |
| `reportValidity()` | comme `checkValidity()`, et affiche la bulle d'erreur du premier champ |
| `setCustomValidity(message)` | rend le champ invalide avec ce message ; `''` le rend de nouveau valide |

`setCustomValidity()` ajoute une règle que les attributs ne savent pas
exprimer, comme l'égalité de deux champs. Le message reste en place jusqu'au
prochain appel avec `''` : la vérification se refait à chaque `input`.

```js
const comparer = () => confirmation.setCustomValidity(
    confirmation.value === motDePasse.value ? '' : 'The two passwords differ.');
motDePasse.addEventListener('input', comparer);
confirmation.addEventListener('input', comparer);
```

L'événement `invalid` ne remonte pas : un formulaire l'écoute en capture.
`preventDefault()` sur `invalid` supprime la bulle du navigateur, pour afficher
un message dans la page à la place.

```js
form.addEventListener('invalid', (evenement) => {
    evenement.preventDefault();
    afficherErreur(evenement.target, evenement.target.validationMessage);
}, true);
```

### Pseudo-classes CSS

| Pseudo-classe | Élément visé |
|---------------|--------------|
| `:valid`, `:invalid` | champ qui respecte ou non ses contraintes, dès l'affichage |
| `:user-valid`, `:user-invalid` | même chose, après une interaction de l'utilisateur ou une tentative d'envoi |
| `:required`, `:optional` | présence de `required` |
| `:in-range`, `:out-of-range` | valeur entre `min` et `max` |
| `:placeholder-shown` | champ vide qui affiche son `placeholder` |
| `:checked`, `:indeterminate` | case cochée, case à l'état intermédiaire |
| `:disabled`, `:enabled`, `:read-only`, `:read-write` | état d'édition |
| `:default` | bouton par défaut, option ou case cochée d'origine |
| `:autofill` | champ rempli par le navigateur |

`:invalid` colore en rouge un champ `required` vide dès le chargement de la
page ; `:user-invalid` attend que l'utilisateur ait agi.

## 5 : Événements

| Événement | Émis | Remonte |
|-----------|------|---------|
| `input` | à chaque modification de la valeur | oui |
| `change` | à la validation de la modification : perte du focus pour un champ texte, immédiatement pour une case, un radio, un `select`, un fichier | oui |
| `beforeinput` | avant la modification ; annulable, `inputType` décrit l'opération (`insertText`, `deleteContentBackward`, `insertFromPaste`) | oui |
| `submit` | sur le formulaire, après validation, avant l'envoi ; annulable | oui |
| `reset` | sur le formulaire, avant la remise aux valeurs initiales ; annulable | oui |
| `invalid` | sur chaque champ en échec, à l'envoi ou à `checkValidity()` | non |
| `formdata` | sur le formulaire, à la construction d'un `FormData` | non |
| `select` | sélection de texte dans un champ | oui |

Une valeur modifiée par script (`champ.value = 'x'`) n'émet ni `input` ni
`change`.

## 6 : Propriétés des champs

| Propriété | Contenu |
|-----------|---------|
| `value` | valeur courante, toujours une chaîne |
| `defaultValue`, `defaultChecked` | valeur et état initiaux, tirés des attributs HTML ; `form.reset()` y revient |
| `valueAsNumber`, `valueAsDate` | valeur convertie, pour les champs `number`, `range`, `date`, `time` ; `NaN` ou `null` si vide |
| `checked` | état d'une case ou d'un radio |
| `indeterminate` | état intermédiaire d'une case, visuel seulement, jamais envoyé |
| `selectedIndex`, `selectedOptions` | option choisie, options choisies d'un `select` |
| `files` | `FileList` des fichiers choisis |
| `selectionStart`, `selectionEnd` | position du curseur ou de la sélection |
| `labels` | les `<label>` associés |
| `form` | le formulaire de rattachement |

`form.elements` donne tous les champs, accessibles par nom :
`form.elements.email`. Pour un groupe de radios, `form.elements.nom` est une
`RadioNodeList` dont la propriété `value` vaut la valeur du radio coché.

Un champ nommé `submit`, `action`, `method` ou `reset` masque la propriété du
même nom du formulaire : avec `<input name="submit">`, `form.submit` désigne le
champ, et `form.submit()` lève une erreur.

## 7 : Attributs d'aide à la saisie

| Attribut | Effet |
|----------|-------|
| `autocomplete` | type de donnée pour le remplissage automatique : `username`, `email`, `current-password`, `new-password` (propose un mot de passe généré), `one-time-code`, `off` |
| `inputmode` | clavier virtuel affiché sur mobile : `numeric`, `decimal`, `email`, `tel`, `url`, `search` |
| `enterkeyhint` | libellé de la touche Entrée du clavier virtuel : `send`, `next`, `search`, `done` |
| `autocapitalize`, `spellcheck` | majuscule automatique, correction orthographique |
| `list` + `<datalist>` | suggestions proposées sous le champ, saisie libre conservée |
| `placeholder` | texte d'exemple, effacé à la saisie ; un `<label>` reste nécessaire |
| `autofocus` | focus au chargement de la page |
| `accept` | types de fichiers proposés par le sélecteur ; indicatif, le serveur vérifie |
| `capture` | sur mobile, ouvre directement l'appareil photo : `user` (avant), `environment` (arrière) |
| `multiple` | plusieurs fichiers, ou plusieurs adresses pour `type="email"` |

Un clic sur un `<label>` donne le focus au champ associé, ou coche la case
associée. L'association se fait par `for="id"`, ou en plaçant le champ dans le
`<label>`. `<fieldset>` et `<legend>` regroupent des champs liés, comme un
groupe de radios, et les lecteurs d'écran annoncent la légende.

## 8 : Fichiers

```js
champ.addEventListener('change', () => {
    const fichier = champ.files[0];
    if (!fichier) {
        return;
    }
    // fichier.name, fichier.size (octets), fichier.type (déclaré), fichier.lastModified
    apercu.src = URL.createObjectURL(fichier);
});
```

`URL.createObjectURL()` crée une adresse `blob:` qui affiche le fichier local
sans l'envoyer ; `URL.revokeObjectURL()` libère la mémoire quand l'aperçu
change. `fichier.size` permet de refuser un fichier trop lourd avant l'envoi.

`champ.files` se remplace par script à travers un `DataTransfer` :

```js
const transfert = new DataTransfer();
transfert.items.add(fichier);
champ.files = transfert.files;
```

`champ.value = ''` vide la sélection ; c'est la seule valeur que le script
puisse écrire dans un champ fichier.
