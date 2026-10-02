# Bases de PHP

## 1 : Exécution

Apache transmet chaque requête HTTP à PHP, qui exécute le script demandé du
début à la fin. La sortie du script (texte produit par `echo`, HTML hors des
balises PHP) forme le corps de la réponse. À la fin de la requête, toutes les
variables et tous les objets sont détruits : la requête suivante repart d'un
état vide. Une donnée qui doit survivre d'une requête à l'autre se garde en
session, en base ou dans un fichier.

Le code PHP s'écrit entre `<?php` et `?>`. Le texte situé hors de ces balises
est envoyé tel quel. `<?= expr ?>` équivaut à `<?php echo expr; ?>`.

Un fichier qui ne contient que du PHP omet la balise fermante `?>` : un saut de
ligne placé après elle serait envoyé au navigateur, et tout `header()` appelé
ensuite échouerait (`headers already sent`).

## 2 : Variables et types

Une variable commence par `$`, n'est pas déclarée et prend le type de la valeur
affectée.

| Type | Valeurs |
|------|---------|
| `int`, `float` | `42`, `3.5` |
| `string` | `'texte'`, `"texte"` |
| `bool` | `true`, `false` |
| `array` | `[1, 2]`, `['cle' => 'valeur']` |
| `null` | `null` : variable sans valeur |
| objet | instance d'une classe |

Chaînes :

- entre apostrophes, le contenu est littéral ; seuls `\'` et `\\` sont
  interprétés ;
- entre guillemets, les variables sont remplacées par leur valeur
  (`"Bonjour $nom"`, `"{$ligne['nom']}"`) et les séquences `\n`, `\t` sont
  interprétées ;
- `.` concatène deux chaînes : `'a' . $b`.

Comparaison : `==` convertit les deux opérandes avant de comparer (`'1' == 1`
est vrai), `===` exige le même type et la même valeur. Le projet emploie `===`.

Opérateurs propres à PHP :

| Opérateur | Résultat |
|-----------|----------|
| `$a ?? $b` | `$a` s'il existe et n'est pas `null`, sinon `$b`, sans avertissement sur une clé absente |
| `$a ?: $b` | `$a` s'il est vrai, sinon `$b` |
| `(int) $a`, `(string) $a` | conversion explicite |

En PHP, `||` renvoie toujours un booléen : `$_POST['nom'] || ''` vaut `true` ou
`false`. En JavaScript, `a || b` renvoie l'un des deux opérandes. Une valeur par défaut s'écrit avec `??` ou
avec `?:`, qui diffèrent sur deux points.

`?:` lit d'abord son opérande gauche : une clé absente de `$_POST` produit
l'avertissement `Undefined array key`. `??` teste l'existence de la clé sans
avertissement.

`?:` remplace toute valeur fausse : `0`, `'0'`, `''`, `[]`, `false`, `null`.
`??` ne remplace que l'absence et `null`. Avec `?page=0`, `$_GET['page'] ?: 1`
vaut `1` alors que `$_GET['page'] ?? 1` vaut `'0'`.

`??` sert donc à lire une entrée qui peut manquer (`$_GET`, `$_POST`,
`$_SESSION`, clé de tableau). `?:` sert à remplacer une valeur présente mais
fausse : `$stmt->fetch() ?: null` convertit le `false` que renvoie `fetch()` en
l'absence de ligne, `false` n'étant pas `null` et passant donc à travers `??`.

## 3 : Tableaux

Un tableau PHP est une liste ordonnée de couples clé-valeur. Les clés sont des
entiers ou des chaînes ; sans clé explicite, PHP numérote à partir de 0.

```php
$liste = ['a', 'b'];
$liste[] = 'c';                  // ajout en fin
$fiche = ['nom' => 'x', 'age' => 3];
$fiche['nom'];                   // 'x'

foreach ($fiche as $cle => $valeur) {
    echo $cle . ' : ' . $valeur;
}
```

L'affectation d'un tableau le copie : modifier la copie ne modifie pas
l'original. Un objet, au contraire, est désigné par une référence : deux
variables affectées au même objet modifient le même objet.

Fonctions courantes : `count()`, `in_array()`, `array_map()`, `array_filter()`,
`implode()`, `explode()`, `isset()`, `empty()`.

## 4 : Fonctions

```php
function aire(int $largeur, int $hauteur = 1): int
{
    return $largeur * $hauteur;
}
```

Les paramètres et le retour se typent. `?string` accepte `string` ou `null` ;
`void` signale l'absence de retour ; `mixed` accepte tout type.

`declare(strict_types=1);`, en tête de fichier, interdit les conversions
implicites lors des appels écrits dans ce fichier : `aire('3')` lève alors une
`TypeError` ; sans cette déclaration, PHP convertit `'3'` en `3`. Dans le projet, tous les fichiers
PHP commencent par cette ligne, sauf les vues.

Une fonction ne voit pas les variables extérieures. Une fonction anonyme les
importe par `use`, une fonction fléchée les capture d'office :

```php
$taux = 2;
$double = function (int $x) use ($taux): int { return $x * $taux; };
$triple = fn (int $x): int => $x * 3;
```

## 5 : Classes

```php
abstract class Forme
{
    public const COTES = 0;

    public function __construct(protected string $nom)
    {
    }

    abstract public function aire(): float;

    public function nom(): string
    {
        return $this->nom;
    }
}

final class Carre extends Forme
{
    public const COTES = 4;

    public function __construct(private float $cote)
    {
        parent::__construct('carré');
    }

    public function aire(): float
    {
        return $this->cote ** 2;
    }
}

$c = new Carre(2.0);
$c->aire();        // 4.0
Carre::COTES;      // 4
```

| Élément | Rôle |
|---------|------|
| `new` | crée une instance et appelle `__construct()` |
| `$this->x` | propriété ou méthode de l'instance courante |
| `Classe::x` | constante ou membre statique, sans instance |
| `self::`, `parent::` | la classe courante, la classe mère |
| `public` / `protected` / `private` | accès depuis partout / depuis la classe et ses filles / depuis la classe seule |
| `extends` | héritage d'une seule classe mère |
| `abstract` | classe non instanciable, ou méthode à définir par les classes filles |
| `final` | classe dont on ne peut pas hériter |
| `static` | membre attaché à la classe et partagé par toutes les instances |

Un paramètre du constructeur précédé de sa visibilité
(`private float $cote`) déclare et remplit la propriété du même nom.

## 6 : Espaces de noms

Un espace de noms préfixe les noms de classes pour éviter les collisions. Il se
déclare en tête de fichier :

```php
namespace App\Models;
```

La classe `User` de ce fichier s'appelle alors `App\Models\User`. Un autre
fichier la désigne par son nom complet, ou l'importe par `use` :

```php
use App\Models\User;

$u = new User();
```

`use` ne charge aucun fichier : il associe un nom court à un nom complet, au
moment de la compilation. `User::class` vaut la chaîne `'App\Models\User'`.

Une classe de PHP lui-même (`PDO`, `RuntimeException`) appartient à l'espace de
noms global : depuis un fichier qui déclare un espace de noms, elle s'écrit
`\PDO` ou s'importe par `use PDO;`.

## 7 : Inclusion de fichiers

`require` et `include` exécutent un autre fichier PHP à l'endroit de l'appel.

| Instruction | Fichier absent | Fichier déjà inclus |
|-------------|----------------|---------------------|
| `require` | erreur fatale, arrêt du script | inclus de nouveau |
| `include` | avertissement, le script continue | inclus de nouveau |
| `require_once`, `include_once` | comme ci-dessus | ignoré |

Le fichier inclus partage la portée de la ligne qui l'inclut : appelé dans une
fonction, il voit les variables locales de cette fonction. Ce mécanisme permet
à une vue incluse par une méthode de lire les variables préparées par cette
méthode.

Un fichier inclus peut se terminer par `return` ; `require` renvoie alors cette
valeur. Un fichier de configuration renvoie ainsi un tableau :

```php
// reglages.php
return ['duree' => 3600];

// ailleurs
$reglages = require __DIR__ . '/reglages.php';
```

Un chemin relatif est résolu par rapport à l'`include_path` et au répertoire
courant, qui ne correspondent pas forcément au dossier du fichier appelant. Les
chemins s'écrivent donc en absolu, à partir de `__DIR__` (dossier du fichier
courant) ou d'une constante comme `BASE_PATH`.

## 8 : Chargement automatique des classes

`spl_autoload_register()` enregistre une fonction que PHP appelle lorsqu'il
rencontre une classe encore inconnue. Cette fonction reçoit le nom complet de la
classe et inclut le fichier correspondant. Avec la convention « espace de noms
= dossier », `App\Models\User` se charge depuis `app/Models/User.php`, et aucun
`require` de classe n'est écrit à la main. Une classe jamais utilisée pendant
une requête n'est jamais chargée.

## 9 : Superglobales

Tableaux remplis par PHP à chaque requête, accessibles partout :

| Variable | Contenu |
|----------|---------|
| `$_GET` | paramètres de la chaîne de requête |
| `$_POST` | champs d'un corps au format formulaire |
| `$_FILES` | fichiers envoyés en `multipart/form-data` |
| `$_SESSION` | données de session, après `session_start()` |
| `$_SERVER` | méthode, chemin, en-têtes de la requête, informations serveur |
| `$_COOKIE` | cookies envoyés par le navigateur |

Leurs valeurs viennent du client : une clé peut manquer (`?? ''`), et une valeur
peut être une chaîne là où un entier est attendu, ou un tableau (`nom[]=...`).


## 10 : Exceptions

```php
try {
    $resultat = traiter($entree);
} catch (RuntimeException $e) {
    echo $e->getMessage();
} finally {
    // exécuté dans tous les cas
}
```

`throw new RuntimeException('message')` interrompt la fonction et remonte
jusqu'au premier `catch` dont le type correspond. Une exception que rien
n'attrape arrête le script et produit une réponse 500. `Throwable` attrape
toutes les exceptions et les erreurs (`TypeError`, `Error`).
