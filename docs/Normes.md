# Normes

## 1 : Organismes

| Organisme | Publie | Forme |
|-----------|--------|-------|
| IETF | protocoles d'Internet : HTTP, URL, cookies, mail | RFC numérotées ; une RFC publiée ne change plus, une nouvelle la remplace |
| WHATWG | HTML, DOM, `fetch()`, URL côté navigateur | standards « vivants », mis à jour en continu, sans version |
| W3C | CSS, accessibilité (WCAG), CSP | recommandations versionnées ; CSS est découpé en modules |
| Ecma, comité TC39 | JavaScript (ECMAScript, norme ECMA-262) | une édition par an (ES2024, ES2025…) |
| ISO / IEC | SQL (ISO/IEC 9075) | éditions datées (SQL:2016, SQL:2023) |
| The Open Group | POSIX : shell, utilitaires (`make`, `sed`), API système | éditions datées |
| PHP-FIG | recommandations PHP (PSR) | numérotées, avec un statut : acceptée, en projet, abandonnée ou dépréciée |
| OWASP | listes de failles et d'exigences de sécurité | révisées tous les quelques ans |

### Vocabulaire des RFC

La [RFC 2119](https://www.rfc-editor.org/rfc/rfc2119) fixe le sens des mots
écrits en majuscules dans les normes :

| Mot | Sens |
|-----|------|
| MUST, REQUIRED, SHALL | obligation absolue |
| MUST NOT, SHALL NOT | interdiction absolue |
| SHOULD, RECOMMENDED | à suivre, sauf raison valable et comprise |
| SHOULD NOT | à éviter, sauf raison valable et comprise |
| MAY, OPTIONAL | libre |

## 2 : Web et réseau

| Norme | Contenu |
|-------|---------|
| [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110) | sémantique HTTP : méthodes, codes de statut, en-têtes |
| [RFC 9111](https://www.rfc-editor.org/rfc/rfc9111) | cache HTTP : `Cache-Control`, revalidation |
| [RFC 9112](https://www.rfc-editor.org/rfc/rfc9112) | format des messages HTTP/1.1 sur la connexion |
| [RFC 3986](https://www.rfc-editor.org/rfc/rfc3986) | syntaxe des URI, encodage `%` |
| [RFC 6265](https://www.rfc-editor.org/rfc/rfc6265) | cookies : `Set-Cookie`, `Domain`, `Path`, `HttpOnly`, `Secure` |
| [RFC 6265bis](https://datatracker.ietf.org/doc/draft-ietf-httpbis-rfc6265bis/) | révision en cours des cookies, qui définit `SameSite` |
| [RFC 7578](https://www.rfc-editor.org/rfc/rfc7578) | corps `multipart/form-data` |
| [RFC 6838](https://www.rfc-editor.org/rfc/rfc6838) | types de médias (`image/jpeg`, `application/json`) |
| [RFC 8259](https://www.rfc-editor.org/rfc/rfc8259) | format JSON |
| [RFC 5321](https://www.rfc-editor.org/rfc/rfc5321) | protocole SMTP |
| [RFC 5322](https://www.rfc-editor.org/rfc/rfc5322) | format d'un message électronique : en-têtes, corps |
| [HTML](https://html.spec.whatwg.org/) | éléments, formulaires, analyse du HTML, événements de l'interface |
| [DOM](https://dom.spec.whatwg.org/) | arbre du document, événements, `addEventListener` |
| [Fetch](https://fetch.spec.whatwg.org/) | `fetch()`, CORS, modes de requête |
| [URL](https://url.spec.whatwg.org/) | analyse des URL par les navigateurs, `URLSearchParams` |
| [CSS](https://www.w3.org/Style/CSS/current-work) | état de chaque module CSS |
| [CSP niveau 3](https://www.w3.org/TR/CSP3/) | en-tête `Content-Security-Policy` |
| [WCAG 2.2](https://www.w3.org/TR/WCAG22/) | accessibilité, critères de niveau A, AA, AAA |
| [ARIA APG](https://www.w3.org/WAI/ARIA/apg/) | modèles accessibles de composants (menu, onglets, dialogue) |

## 3 : Langages

| Norme | Contenu |
|-------|---------|
| [ECMA-262](https://tc39.es/ecma262/) | JavaScript |
| [PSR](https://www.php-fig.org/psr/) | recommandations PHP |
| [PSR-1](https://www.php-fig.org/psr/psr-1/), [PSR-12](https://www.php-fig.org/psr/psr-12/) | style de code PHP |
| [PSR-4](https://www.php-fig.org/psr/psr-4/) | chargement automatique : espace de noms et chemin de fichier se correspondent |
| ISO/IEC 9075 | SQL ; [conformité de PostgreSQL](https://www.postgresql.org/docs/current/features.html) |
| [POSIX](https://pubs.opengroup.org/onlinepubs/9799919799/) | shell `sh`, utilitaires, `make` |

## 4 : Sécurité

| Norme | Contenu |
|-------|---------|
| [OWASP Top 10](https://owasp.org/www-project-top-ten/) | les dix catégories de failles web les plus fréquentes |
| [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/) | liste d'exigences vérifiables, en trois niveaux |

## 5 : Conventions de nommage

| Nom | Forme | Usage courant |
|-----|-------|---------------|
| camelCase | `nomUtilisateur` | variables et fonctions JavaScript, méthodes PHP |
| PascalCase | `NomUtilisateur` | classes |
| snake_case | `nom_utilisateur` | colonnes SQL, variables Python, fonctions PHP natives |
| SCREAMING_SNAKE_CASE | `NOM_UTILISATEUR` | constantes, variables d'environnement |
| kebab-case | `nom-utilisateur` | classes CSS, noms de fichiers, URL |
| BEM | `carte__titre--actif` | classes CSS : [bloc, élément, modificateur](https://en.bem.info/) |

## 6 : Motifs d'architecture

| Nom | Principe |
|-----|----------|
| Front Controller | un point d'entrée unique reçoit toutes les requêtes et les distribue |
| MVC | modèle (données), vue (affichage), contrôleur (traitement de la requête) |
| Post/Redirect/Get | une action POST répond par une redirection ; la page affichée ensuite provient d'un GET |
| Synchronizer Token | jeton secret en session, recopié dans chaque formulaire, contre le CSRF |
| Amélioration progressive | la page fonctionne sans JavaScript ; le script ajoute du confort |
| [Twelve-Factor App](https://12factor.net/fr/) | douze règles pour une application déployable, dont la configuration dans l'environnement |

## 7 : Conventions de projet

| Convention | Contenu |
|------------|---------|
| [SemVer](https://semver.org/lang/fr/) | version `MAJEUR.MINEUR.CORRECTIF` : majeur pour une rupture de compatibilité, mineur pour un ajout, correctif pour une correction |
| [Conventional Commits](https://www.conventionalcommits.org/fr/v1.0.0/) | messages de commit préfixés : `feat:`, `fix:`, `docs:`, `refactor:` |
| [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) | structure d'un fichier `CHANGELOG.md` |
| [EditorConfig](https://editorconfig.org/) | fichier `.editorconfig` : indentation, fin de ligne, encodage, partagés entre éditeurs |
