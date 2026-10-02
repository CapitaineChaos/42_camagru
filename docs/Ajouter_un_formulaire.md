# Ajouter un formulaire

Un formulaire demande deux routes, l'une pour afficher la page qui le contient,
l'autre pour traiter l'envoi, ainsi qu'une méthode de traitement qui lit,
valide, agit et répond.

Dans ce document, `xxx` désigne la page et `XxxController` le contrôleur.

## 1 : Routes

```php
// config/routes.php
$router->get('/xxx', [XxxController::class, 'xxx'], Router::AUTH);
$router->post('/xxx/action', [XxxController::class, 'action'], Router::AUTH);
```

Le chemin du `POST` peut être identique à celui du `GET` ou distinct ; une page
qui porte plusieurs formulaires utilise un chemin par formulaire. Le niveau
d'accès se déclare sur les deux routes : protéger l'affichage ne protège pas le
traitement.

## 2 : Balisage

```php
<form class="form-block flex-vt" method="post" action="/xxx/action">
    <?= \App\Core\Csrf::field() ?>
    <p class="field flex-vt tight">
        <label for="nom">Nom</label>
        <input type="text" id="nom" name="nom"
               value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
               maxlength="<?= (int) \App\Core\Settings::get('xxx.max_length') ?>"
               required>
    </p>
    <p class="flex-hz"><button type="submit">Enregistrer</button></p>
</form>
```

| Élément | Règle |
|---------|-------|
| `method="post"` | Le routeur ne vérifie le jeton que sur les requêtes POST. |
| `Csrf::field()` | Placé à l'intérieur du `<form>` ; sans lui, la requête reçoit un 403. |
| `name` | Clé sous laquelle la valeur arrive dans `$_POST`. |
| `id` et `for` | Associent le libellé au champ. |
| `value` | Valeur réaffichée après une erreur, échappée par `htmlspecialchars()`. |
| `pattern` | Expression que la valeur entière doit vérifier. Les navigateurs la compilent avec le drapeau `v` : un `-` littéral dans une classe s'écrit `\-`, et une expression invalide est ignorée, avec un avertissement dans la console. |
| `autocomplete` | Indique au navigateur le type de donnée (`username`, `email`, `current-password`, `new-password`). |

Les contraintes HTML (`required`, `maxlength`, `minlength`, `type="email"`)
servent à l'affichage. Le serveur valide les mêmes règles : un POST peut être
envoyé sans passer par la page. Les bornes se lisent dans `config/settings.php`,
du côté de la vue comme du côté du contrôleur, pour que les deux appliquent la
même valeur.

Les classes de mise en forme sont définies dans `components.css` et
`utilities.css` : `form-block`, `form-inline`, `field`, `flex-vt`, `flex-hz`,
`tight`.

## 3 : Méthode de traitement

La méthode suit quatre étapes : lecture, validation, action, réponse.

```php
public function action(): void
{
    $nom = trim((string) ($_POST['nom'] ?? ''));

    $errors = [];
    if ($nom === '') {
        $errors[] = 'Name is required.';
    }
    if (mb_strlen($nom) > (int) Settings::get('xxx.max_length')) {
        $errors[] = 'Name is too long.';
    }

    if ($errors !== []) {
        Flash::errors($errors);
        $this->redirect('/xxx');
    }

    (new Xxx())->update($this->viewerId(), $nom);
    Flash::notice('Saved.');
    $this->redirect('/xxx');
}
```

Chaque champ se lit avec `?? ''` : un champ peut manquer dans la requête, et la
lecture d'une clé absente de `$_POST` produit un avertissement PHP. Le
transtypage `(string)` couvre le cas d'un champ envoyé sous forme de tableau
(`nom[]=...`).

Les erreurs s'accumulent dans `$errors` et s'affichent ensemble.

Une contrainte portée par la base (longueur de colonne, unicité) se valide aussi
en PHP avant l'écriture. Sinon, la violation lève une `PDOException`, qui
produit une erreur 500.

`redirect()` termine le script : aucun `return` n'est nécessaire après lui.

## 4 : Réponse

Deux réponses sont possibles.

### Redirection et `Flash`

La méthode dépose les messages par `Flash::notice()` ou `Flash::errors()`, puis
redirige vers la page. La méthode `GET` de la page les récupère et les transmet
à la vue :

```php
$this->view('xxx', ['title' => 'Xxx'] + Flash::pull());
```

`Flash::pull()` renvoie les messages et les retire de la session : ils
s'affichent une seule fois. Après la redirection, la page affichée est la
réponse à un `GET`, et un rafraîchissement ne renvoie pas le formulaire
(Post/Redirect/Get). Les valeurs saisies sont perdues.

### Rendu direct

En cas d'erreur, la méthode rend la vue elle-même, avec les erreurs et les
valeurs saisies :

```php
$this->view('xxx', [
    'title'  => 'Xxx',
    'errors' => $errors,
    'old'    => ['nom' => $nom],
]);
return;
```

Les champs sont réaffichés à partir de `$old`. Le navigateur reste sur la
réponse à un POST : un rafraîchissement propose de renvoyer le formulaire. Ce
rendu convient à une page qui ne contient que le formulaire.

Dans les deux cas, une action réussie se termine par une redirection.

## 5 : Affichage des messages

Le partiel commun s'inclut dans la vue, avant le formulaire :

```php
<?php require BASE_PATH . '/app/Views/partials/messages.php'; ?>
```

Il lit `$notice` et `$errors`, échappe leur contenu et les rend avec les classes
`.notice` et `.error`.

## 6 : Envoi de fichier

```php
<form method="post" action="/xxx/action" enctype="multipart/form-data">
    <?= \App\Core\Csrf::field() ?>
    <input type="file" id="file" name="file" accept="image/jpeg,image/png">
</form>
```

`enctype="multipart/form-data"` est obligatoire : sans lui, seul le nom du
fichier est transmis. Les champs texte, dont le jeton, continuent d'alimenter
`$_POST`.

Le fichier arrive dans `$_FILES['file']`, tableau qui contient `name`, `type`,
`tmp_name`, `error` et `size`. Contrôles à effectuer avant tout traitement :

| Contrôle | Raison |
|----------|--------|
| `error === UPLOAD_ERR_OK` | Distingue l'absence de fichier (`UPLOAD_ERR_NO_FILE`), le dépassement de taille (`UPLOAD_ERR_INI_SIZE`) et l'échec. |
| `is_uploaded_file($fichier['tmp_name'])` | Confirme que le chemin provient d'un envoi HTTP. |
| type réel | `type` est déclaré par le client. Le type se détermine en lisant les octets, par `getimagesize()` ou `finfo`. |
| taille | Borne lue dans `config/settings.php`, en plus des limites de PHP. |
| nom de destination | Généré côté serveur (`bin2hex(random_bytes(16))`) ; `name` n'est jamais réutilisé. |

Les limites `upload_max_filesize` et `post_max_size` sont fixées dans
`docker/web/uploads.ini`. Un envoi qui dépasse `post_max_size` arrive avec un
`$_POST` vide, donc sans jeton : la réponse est le 403 du contrôle CSRF.

## 7 : Envoi par JavaScript

Un formulaire peut être envoyé par `fetch()` sans changement de page. Le corps
reste au format formulaire (`new FormData(form)`), qui transporte le jeton.
`fetch()` suit la redirection de fin d'action et reçoit la page HTML de la
cible : une méthode dont le script doit connaître le résultat répond par
`$this->json([...])`. Les refus du routeur (403, redirection vers `/login`)
arrivent en HTML.

## Récapitulatif

| Étape | Fichier |
|-------|---------|
| Routes `GET` et `POST`, niveau d'accès | `config/routes.php` |
| Balisage, `Csrf::field()`, `$old` | `app/Views/xxx.php` |
| Messages | `app/Views/partials/messages.php` |
| Lecture, validation, réponse | `app/Controllers/XxxController.php` |
| Écriture en base | `app/Models/Xxx.php` |
| Bornes (longueurs, tailles, types) | `config/settings.php` |
| Limites d'envoi de PHP | `docker/web/uploads.ini` |
