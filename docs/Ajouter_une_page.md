# Ajouter une page

Une page est une méthode de contrôleur qui rend une vue, atteinte par une route
`GET`. Le layout l'entoure du menu, du titre, des feuilles de style et des
scripts ; chacun de ces éléments se déclare dans `app/Views/layout.php`.

Dans ce document, `xxx` désigne le nom de la vue et `XxxController` le
contrôleur.

## 1 : Contrôleur

Un contrôleur regroupe les pages d'un même domaine. Une page s'ajoute comme
méthode d'un contrôleur existant, ou dans un nouveau fichier
`app/Controllers/XxxController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class XxxController extends Controller
{
    public function xxx(): void
    {
        $this->view('xxx', ['title' => 'Xxx']);
    }
}
```

L'espace de noms `App\Controllers` correspond au dossier `app/Controllers` :
l'autoloader de `public/index.php` déduit le chemin du fichier à partir du nom
complet de la classe. Une classe dont l'espace de noms ne suit pas son dossier
n'est pas trouvée, et la route produit une erreur 500.

La méthode prépare toutes les données affichées (appels aux modèles, calculs,
bornes) et les passe à `view()` dans un tableau. La clé `title` alimente la
balise `<title>` du layout.

## 2 : Vue

La vue est le fichier `app/Views/xxx.php`. Son nom, sans l'extension, est le
premier argument de `view()` ; un sous-dossier fait partie du nom
(`view('auth/xxx')` rend `app/Views/auth/xxx.php`).

La vue ne contient que le contenu de `<main>` : `layout.php` fournit
`<html>`, `<head>`, le menu et le pied de page.

Chaque clé du tableau passé à `view()` devient une variable de la vue :
`['lignes' => $lignes]` donne `$lignes`. Toute valeur issue de la base ou d'une
saisie est échappée à l'affichage :

```php
<p><?= htmlspecialchars((string) $ligne['nom']) ?></p>
```

La vue ne lit ni `$_GET` ni `$_POST` et n'appelle aucun modèle.

Les variables définies dans `layout.php`, comme `$v`, n'existent pas dans la
vue : la vue est rendue avant l'inclusion du layout.

## 3 : Route

Dans `config/routes.php`, importer le contrôleur en tête de fichier, puis
déclarer la route :

```php
use App\Controllers\XxxController;
```

```php
$router->get('/xxx', [XxxController::class, 'xxx']);
```

Une page réservée aux utilisateurs connectés porte `Router::AUTH` en troisième
argument, une page d'administration `Router::ADMIN`.

## 4 : Entrée de menu

Le tableau `$liens` de `layout.php` liste les entrées du menu. Chaque entrée
contient le chemin, le libellé et le slug du lettrage :

```php
$liens[] = ['/xxx', 'Xxx', 'xxx'];
```

L'emplacement de la ligne détermine qui voit l'entrée : avant le `if`, tout
visiteur ; dans la branche `if (!empty($_SESSION['user']))`, les utilisateurs
connectés ; dans la branche `else`, les visiteurs non connectés. Un quatrième
élément, entier, affiche une pastille numérotée à côté de l'entrée.

Le libellé sert de texte alternatif. Le menu affiche deux images SVG, l'une
pour le menu latéral des pages internes, l'autre pour le menu de l'accueil :

```
public/images/elements/menu/xxx.svg
public/images/elements/accueil/xxx.svg
```

Ces fichiers sont produits par `scripts/draw/lettrage.py`. Sans eux, l'entrée
s'affiche comme une image cassée.

## 5 : Titre

Le tableau `$titres` de `layout.php` associe un nom de vue à un lettrage et un
libellé :

```php
'xxx' => ['xxx', 'Xxx'],
```

Le premier élément désigne `public/images/elements/titres/xxx.svg`, inséré dans
la page par `Svg::inline()` ; le second est le libellé. Une vue absente de
`$titres` s'affiche sans titre.

## 6 : Feuille de style

Les feuilles de `$feuilles` sont chargées sur toutes les pages, dans l'ordre de
la cascade. Une feuille propre à la page se déclare dans `$specifiques`, avec
le nom de la vue en clé et le nom du fichier `public/css/<valeur>.css` en
valeur :

```php
'xxx' => 'xxx',
```

Plusieurs vues peuvent partager la même feuille. La feuille spécifique est
chargée après les feuilles communes et l'emporte sur elles à spécificité égale.

## 7 : Script

Un script propre à la page se place dans `public/js/xxx.js` et se charge en fin
de `layout.php`, sous condition sur `$view` :

```php
<?php if (($view ?? '') === 'xxx'): ?>
<script src="/js/xxx.js?v=<?= $v ?>" defer></script>
<?php endif; ?>
```

La politique de sécurité de contenu refuse tout script en ligne : le code est
dans le fichier, et les événements s'attachent par `addEventListener`.

## 8 : Rechargement des fichiers statiques

Les URL des CSS, JS et SVG portent `?v=` suivi de `assets.version`
(`config/settings.php`). Après modification de l'un de ces fichiers, incrémenter
cette valeur : le navigateur indexe son cache par URL, et la nouvelle URL
l'oblige à télécharger le fichier.

## Récapitulatif

| Étape | Fichier |
|-------|---------|
| Méthode, données de la vue | `app/Controllers/XxxController.php` |
| Contenu de la page | `app/Views/xxx.php` |
| Route, `use`, niveau d'accès | `config/routes.php` |
| Entrée de menu | `layout.php` (`$liens`), deux SVG de lettrage |
| Titre | `layout.php` (`$titres`), un SVG de titre |
| Feuille propre à la page | `layout.php` (`$specifiques`), `public/css/xxx.css` |
| Script propre à la page | `layout.php`, `public/js/xxx.js` |
| Cache navigateur | `config/settings.php` (`assets.version`) |


