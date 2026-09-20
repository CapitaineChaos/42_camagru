# Ajouter un formulaire

Un formulaire ajoute deux routes, une validation et une réponse.

## 1 : Les deux routes

Une pour afficher, une pour traiter. Chemin identique ou distinct, méthodes HTTP
différentes :

```php
// config/routes.php
$router->get('/preferences', [PrefsController::class, 'prefs']);
$router->post('/preferences/account', [PrefsController::class, 'account']);
$router->requireAuth('GET', '/preferences');
$router->requireAuth('POST', '/preferences/account');
```

`requireAuth` se déclare sur les deux : protéger l'affichage ne protège pas
l'envoi.

## 2 : Le balisage

```php
<form class="form-block flex-vt" method="post" action="/register">
    <?= \App\Core\Csrf::field() ?>
    <p class="field flex-vt tight">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($old['username'] ?? '') ?>"
               autocomplete="username" required>
    </p>
    <p class="field flex-vt tight">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               autocomplete="new-password" required
               minlength="<?= (int) \App\Core\Settings::get('auth.password_min_length', 8) ?>">
    </p>
    <p class="flex-hz"><button type="submit">Sign up</button></p>
</form>
```

Points fixes :

- `method="post"` — un GET n'est pas vérifié par le routeur.
- `Csrf::field()` à l'intérieur du `<form>`, sinon 403.
- `name` : c'est la clé lue dans `$_POST`.
- `id` sur le champ et `for` correspondant sur le `<label>`.
- `value` réaffiché depuis `$old`, échappé.
- Les contraintes HTML (`required`, `minlength`, `type="email"`) sont du confort
  d'affichage. Elles ne dispensent pas de la validation serveur : un POST peut
  arriver sans passer par la page.
- Les bornes viennent de `config/settings.php`, pas d'un littéral, pour rester
  alignées avec la validation serveur.

Classes disponibles dans `components.css` et `utilities.css` : `form-block`,
`field`, `form-inline`, `flex-vt`, `flex-hz`, `tight`.

## 3 : La méthode de traitement

Lecture, validation, action, réponse :

```php
public function account(): void
{
    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));

    $errors = [];
    if ($username === '' || $email === '') {
        $errors[] = 'Username and email address are required.';
    }
    if (mb_strlen($username) > 50) {
        $errors[] = 'Username is limited to 50 characters.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }

    if ($errors !== []) {
        Flash::errors($errors);
        $this->redirect('/preferences');
    }

    (new User())->updateIdentity($this->viewerId(), $username, $email);
    Flash::notice('Account updated.');
    $this->redirect('/preferences');
}
```

Lecture systématique en `?? ''` puis `trim()` : une clé de `$_POST` absente n'est
pas une erreur PHP à provoquer, un champ peut ne pas être envoyé.

Les messages sont accumulés dans `$errors` et affichés d'un bloc, plutôt que de
sortir à la première erreur.

Une contrainte de base (longueur, unicité) est validée en PHP même si la colonne
la porte aussi : l'erreur SQL n'est pas un message utilisateur.

## 4 : Répondre — deux motifs

Les deux existent dans le projet.

Redirection + Flash, quand le formulaire est sur une page qui affiche autre
chose (préférences, galerie) :

```php
Flash::errors($errors);
$this->redirect('/preferences');
```

La méthode GET récupère les messages et les passe à la vue :

```php
$this->view('preferences', ['title' => 'Preferences'] + Flash::pull());
```

Rendu direct, quand la page n'est que le formulaire (inscription,
connexion) :

```php
$this->view('auth/register', [
    'title'  => 'Sign up',
    'errors' => $errors,
    'old'    => ['username' => $username, 'email' => $email],
]);
return;
```

Différence : le rendu direct conserve les valeurs saisies (`$old`) sans les
stocker en session, mais laisse le navigateur sur une réponse à un POST — un
rafraîchissement propose de renvoyer le formulaire. La redirection l'évite
(Post/Redirect/Get) au prix de la perte des champs saisis.

Une action réussie se termine toujours par une redirection.

## 5 : Afficher les messages

Dans la vue, avant le formulaire :

```php
<?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>
```

Le partiel lit `$notice` et `$errors`, les échappe, et les rend en `.notice` /
`.error`.

## 6 : Envoi de fichier

```php
<form method="post" action="/photobooth/capture" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="file" id="file" name="file" accept="image/jpeg,image/png,image/gif">
</form>
```

`enctype="multipart/form-data"` est obligatoire, sinon seul le nom du fichier est
transmis. Les champs texte continuent d'alimenter `$_POST`, le jeton CSRF
fonctionne donc tel quel.

Côté contrôleur, le fichier arrive dans `$_FILES`, jamais dans `$_POST` :

```php
$fichier = $_FILES['file'] ?? null;
if (is_array($fichier) && (int) $fichier['error'] !== UPLOAD_ERR_NO_FILE) {
    return $montage->fromUpload($fichier);
}
```

À vérifier avant tout traitement :

| Contrôle | Pourquoi |
|----------|----------|
| `error === UPLOAD_ERR_OK` | Distinguer absence, dépassement de taille et échec. |
| Type réel du contenu | `type` vient du client. Le type se détermine à la lecture du fichier, pas sur cette valeur. |
| Taille | `photobooth.max_source` dans `settings.php`, en plus des limites de `uploads.ini`. |
| Nom de destination | Généré côté serveur. Le nom d'origine n'est jamais réutilisé tel quel. |

Les limites PHP (`upload_max_filesize`, `post_max_size`) sont dans
`docker/web/uploads.ini`. Un envoi qui les dépasse arrive avec un `$_POST` vide,
donc sans jeton CSRF : la réponse est un 403, pas un message de taille.

## 7 : Envoi en JavaScript

Le routeur lit `$_POST` : le corps doit être au format formulaire, pas en JSON.

```js
const data = new FormData(form); // embarque le champ caché csrf_token
const reponse = await fetch('/mon-action', {
    method: 'POST',
    body: data,
    credentials: 'same-origin',
});
```

Un corps JSON laisse `$_POST['csrf_token']` vide et la requête est rejetée en
403.

## Récapitulatif

| Étape | Fichier |
|-------|---------|
| Routes GET + POST, `requireAuth` | `config/routes.php` |
| Balisage, `Csrf::field()`, `$old` | `app/Views/xxx.php` |
| Partiel de messages | `app/Views/partials/messages.php` |
| Lecture, validation, action | `app/Controllers/XxxController.php` |
| Écriture | `app/Models/Xxx.php` |
| Bornes (longueurs, tailles, MIME) | `config/settings.php` |
