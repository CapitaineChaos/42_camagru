<?php
/** @var string $content */
/** @var string $title */
/** @var string $view */

$accueil = ($view ?? '') === 'home';

$liens = [['/', 'Home', 'home'], ['/gallery', 'Gallery', 'gallery']];

if (!empty($_SESSION['user'])) {
    $liens[] = ['/photobooth', 'Photobooth', 'photobooth'];
    $liens[] = ['/friends', 'Friends', 'friends'];
    $liens[] = ['/preferences', 'Preferences', 'preferences'];
    $liens[] = ['/profile', 'Profile', 'profile'];
    if (!empty($_SESSION['user']['is_admin'])) {
        $liens[] = ['/admin', 'Admin', 'admin'];
    }
} else {
    $liens[] = ['/login', 'Login', 'login'];
    $liens[] = ['/register', 'Sign up', 'signup'];
}
if ($accueil) {
    array_shift($liens);
}

$titres = [
    'gallery' => ['gallery', 'Gallery'],             'photobooth' => ['photobooth', 'Photobooth'],
    'friends' => ['friends', 'Friends'],             'preferences' => ['preferences', 'Preferences'],
    'profile' => ['profile', 'Profile'],             'admin' => ['admin', 'Admin'],
    'auth/login' => ['login', 'Login'],              'auth/register' => ['signup', 'Sign up'],
    'auth/forgot' => ['lost-password', 'Lost password'],
    'auth/reset' => ['new-password', 'New password'],
    'errors/403' => ['403', '403'],                  'errors/404' => ['404', '404'],
];
$titrePage = $titres[$view ?? ''] ?? null;

$v = (int) \App\Core\Settings::get('assets.version', 1);

$entree = static function (string $lettrage, string $libelle) use ($accueil, $v): string {
    $droit = '/images/elements/menu/' . $lettrage . '.svg?v=' . $v;
    $alt = htmlspecialchars($libelle);

    if (!$accueil) {
        return '<img src="' . $droit . '" alt="' . $alt . '">';
    }

    return '<picture>'
        . '<source media="(max-width: 767.98px)" srcset="' . $droit . '">'
        . '<img src="/images/elements/accueil/' . $lettrage . '.svg?v=' . $v . '" alt="' . $alt . '">'
        . '</picture>';
};
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) \App\Core\Settings::get('app.lang', 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Camagru') ?></title>
    <?php foreach (['global', 'fond', 'page', 'lettrage', 'decors', 'contenu', 'menu-mobile'] as $feuille): ?>
    <link rel="stylesheet" href="/css/<?= $feuille ?>.css?v=<?= $v ?>">
    <?php endforeach; ?>
</head>
<body class="<?= $accueil ? 'accueil' : 'interieur' ?>">
    <header>
        <input type="checkbox" id="burger" class="hamburger">
        <label for="burger"><span></span></label>
        <nav class="<?= $accueil ? 'menu-accueil' : 'menu-lateral' ?>">
            <ul>
            <?php foreach ($liens as [$url, $libelle, $lettrage]): ?>
                <li><a href="<?= $url ?>"><?= $entree($lettrage, $libelle) ?></a></li>
            <?php endforeach; ?>
            </ul>
        </nav>
    </header>
    <?php if (!empty($currentUser)): ?>
    <div class="compte">
        <span class="jeton">
            <?php if (!empty($currentUserAvatarUrl)): ?>
            <img src="<?= htmlspecialchars($currentUserAvatarUrl) ?>" alt="Avatar de <?= htmlspecialchars($currentUser['username']) ?>" class="avatar">
            <?php endif; ?>
            <span class="initiale"><?= htmlspecialchars(mb_strtoupper(mb_substr($currentUser['username'], 0, 1))) ?></span>
        </span>
        <form action="/logout" method="post" class="logout-form">
            <?= \App\Core\Csrf::field() ?>
            <a href="/logout" onclick="event.preventDefault(); this.closest('form').submit();">
                <img src="/images/elements/menu/logout.svg?v=<?= $v ?>" alt="Logout">
            </a>
        </form>
    </div>
    <?php endif; ?>
    <?php if ($titrePage): ?>
        <h1 class="titre-page"><?= \App\Core\Svg::inline('titres/' . $titrePage[0]) ?></h1>
    <?php endif; ?>
    <main>
        <?= $content ?>
    </main>
    <footer>
        <p>&copy; <?= date('Y') ?> Camagru. All rights reserved.</p>
    </footer>
    <script src="/js/decors.js?v=<?= $v ?>" defer></script>
</body>
</html>
