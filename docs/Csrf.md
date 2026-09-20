# Protection CSRF

Le jeton anti-CSRF vit en session (`$_SESSION['csrf_token']`), généré une fois
(32 octets aléatoires) et réutilisé pour toute la session.

`Core/Csrf.php` :

```php
public static function token(): string   // le jeton de session (créé au besoin)
public static function field(): string   // <input type="hidden" name="csrf_token" ...>
public static function check(mixed $token): bool   // comparaison à temps constant (hash_equals)
```

## L'attaque sans jeton

« Voler un formulaire » = rejouer la requête d'un formulaire du site depuis un
autre site, en profitant du cookie que le navigateur envoie tout seul. Sur une
version **sans jeton**, le déroulé est le suivant.

1. L'attaquant regarde le formulaire visé sur Camagru. Exemple : la suppression
   d'un montage, `POST /photo/delete` avec un champ `id`. Sans protection, il n'y
   a rien d'autre à fournir.

2. Il recrée ce formulaire sur son propre site, champs en caché, valeurs
   choisies, et le fait s'envoyer au chargement :

   ```html
   <!-- page sur evil.example -->
   <form action="https://camagru.local/photo/delete" method="post">
       <input type="hidden" name="id" value="42">
   </form>
   <script>document.forms[0].submit()</script>
   ```

3. Il attire une victime **connectée** sur cette page (lien, pub, iframe).

4. Le formulaire s'auto-soumet. Le navigateur de la victime, comme pour toute
   requête vers `camagru.local`, y attache automatiquement le cookie de session.

5. Le serveur reçoit un POST authentifié (cookie valide) et supprime le montage
   42 — au nom de la victime, sans qu'elle ait cliqué. La cible n'a pas besoin
   d'être visible : `id` est deviné ou lu ailleurs, la victime ne voit rien.

L'attaquant ne vole pas le cookie ni ne lit la page ; il déclenche une action en
laissant le navigateur faire le travail d'authentification.

Le jeton casse l'étape 2 : le formulaire forgé n'a pas de `csrf_token` valide, et
l'attaquant ne peut pas le lire (la *same-origin policy* interdit de lire une
page Camagru depuis `evil.example`). `check()` échoue → 403.

`SameSite=Lax` sur le cookie (`Core/Session.php`) bloque en plus l'envoi du
cookie sur un POST cross-site : le jeton est la seconde barrière, indépendante du
navigateur.

## Vérification : automatique

Elle est centralisée dans le routeur. **Toute requête POST** est contrôlée avant
d'atteindre le contrôleur ; un jeton absent ou invalide renvoie un 403.

`Core/Router.php` :

```php
if ($httpMethod === 'POST' && !Csrf::check($_POST['csrf_token'] ?? null)) {
    (new ErrorController())->forbidden('Security token invalid or expired. Reload the page and try again.');
    return;
}
```

Il n'y a donc rien à appeler dans le contrôleur : il suffit que le formulaire
soit en POST et qu'il porte le jeton.

## Comparaison à temps constant (`hash_equals`)

`check()` compare le jeton reçu avec `hash_equals`, pas avec `==`/`===`.

`==` s'arrête au premier octet qui diffère :

```
"Xxxxxxxx…"  → faux dès l'octet 1
"aXxxxxxx…"  → faux à l'octet 2
```

La durée dépend alors du nombre d'octets corrects en tête. En mesurant le temps
de réponse sur un grand nombre d'essais, un attaquant reconstitue le secret
octet par octet (attaque temporelle).

`hash_equals` compare tous les octets à chaque appel, sans court-circuit : sa
durée est indépendante du contenu.

Pour comparer un secret (jeton CSRF, jeton de session, hash, signature) :
`hash_equals`. Pour un mot de passe : `password_verify`.

Sur ce cas l'attaque est peu réaliste (jeton en session, régénéré à chaque
session) ; `hash_equals` reste la fonction adaptée.

## Protéger son propre formulaire

### 1 : Émettre le jeton dans la vue

Placer `Csrf::field()` à l'intérieur du `<form>` :

```php
<form method="post" action="/mon-action">
    <?= \App\Core\Csrf::field() ?>
    <p class="field flex-vt tight">
        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" required>
    </p>
    <p class="flex-hz"><button type="submit">Envoyer</button></p>
</form>
```

`Csrf::field()` génère le champ caché `csrf_token` — c'est le nom exact que le
routeur relit dans `$_POST`.

### 2 : Déclarer la route en POST

Dans `config/routes.php` :

```php
$router->post('/mon-action', [MonController::class, 'traiter']);
```

Le routeur vérifie le jeton pour cette route comme pour les autres. Rien de plus.

## Points d'attention

- **Uniquement POST.** Une requête GET n'est pas vérifiée — une action qui
  modifie l'état (création, suppression, like) ne doit jamais passer en GET.
- **Le routeur lit `$_POST`.** Un envoi `fetch`/AJAX doit transmettre le jeton
  dans le corps au format formulaire (`FormData` ou `application/x-www-form-urlencoded`),
  pas en JSON — sinon `$_POST['csrf_token']` est vide et la requête est rejetée.

  ```js
  const data = new FormData(form); // récupère aussi le champ caché csrf_token
  fetch('/mon-action', { method: 'POST', body: data });
  ```

- **Upload de fichier.** `enctype="multipart/form-data"` remplit quand même
  `$_POST` pour les champs non-fichiers : le champ caché fonctionne tel quel.
- **Jeton par session, pas par formulaire.** Tous les formulaires d'une même
  session partagent le même jeton ; inutile d'en générer un par page.
