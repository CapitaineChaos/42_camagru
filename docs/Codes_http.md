# Codes de statut HTTP

Le code de statut est le nombre à trois chiffres de la première ligne d'une
réponse (`HTTP/1.1 404 Not Found`). Le premier chiffre donne la classe de la
réponse ; un client qui ne connaît pas un code le traite comme le code `x00` de
sa classe. La liste ci-dessous reprend les codes enregistrés à l'IANA, avec leur
nom normalisé (RFC 9110 pour la plupart).

| Classe | Sens |
|--------|------|
| 1xx | Information : la requête est reçue, le traitement continue |
| 2xx | Succès : la requête est reçue, comprise et acceptée |
| 3xx | Redirection : le client doit faire une autre requête pour aboutir |
| 4xx | Erreur du client : la requête est incorrecte ou ne peut pas être satisfaite |
| 5xx | Erreur du serveur : la requête semble valide, mais le serveur a échoué |

## 1xx : information

| Code | Nom | Signification |
|------|-----|---------------|
| 100 | Continue | Les en-têtes sont acceptés ; le client peut envoyer le corps de la requête (réponse à `Expect: 100-continue`). |
| 101 | Switching Protocols | Le serveur change de protocole à la demande du client (`Upgrade`), par exemple vers WebSocket. |
| 102 | Processing | WebDAV : la requête est en cours de traitement, aucune réponse n'est encore disponible. Déprécié. |
| 103 | Early Hints | Réponse préliminaire portant des en-têtes `Link`, pour que le navigateur précharge des ressources avant la réponse finale. |

## 2xx : succès

| Code | Nom | Signification |
|------|-----|---------------|
| 200 | OK | La requête a réussi ; le corps contient le résultat. |
| 201 | Created | Une ressource a été créée ; `Location` donne son adresse. |
| 202 | Accepted | La requête est acceptée pour un traitement ultérieur, qui n'est pas encore fait. |
| 203 | Non-Authoritative Information | Réponse réussie, modifiée par un intermédiaire (proxy). |
| 204 | No Content | Réussite sans corps de réponse. |
| 205 | Reset Content | Réussite ; le client doit réinitialiser le document affiché (formulaire). |
| 206 | Partial Content | Le corps contient seulement la plage demandée par l'en-tête `Range`. |
| 207 | Multi-Status | WebDAV : le corps XML contient un statut par ressource. |
| 208 | Already Reported | WebDAV : les membres de la ressource ont déjà été listés dans la même réponse. |
| 226 | IM Used | Le corps est le résultat de manipulations d'instance appliquées à la ressource (codage delta). |

## 3xx : redirection

| Code | Nom | Signification |
|------|-----|---------------|
| 300 | Multiple Choices | Plusieurs représentations existent ; le client choisit. |
| 301 | Moved Permanently | La ressource a changé d'adresse définitivement ; le client peut transformer un POST en GET. |
| 302 | Found | La ressource est temporairement à une autre adresse ; le client peut transformer un POST en GET. |
| 303 | See Other | Le résultat est à une autre adresse, à demander en GET. |
| 304 | Not Modified | La copie en cache du client est encore valide (réponse à `If-None-Match` ou `If-Modified-Since`) ; pas de corps. |
| 305 | Use Proxy | Déprécié, pour raisons de sécurité. |
| 306 | (Unused) | Réservé, plus utilisé. |
| 307 | Temporary Redirect | Redirection temporaire ; la méthode et le corps doivent être conservés. |
| 308 | Permanent Redirect | Redirection définitive ; la méthode et le corps doivent être conservés. |

## 4xx : erreur du client

| Code | Nom | Signification |
|------|-----|---------------|
| 400 | Bad Request | Requête mal formée (syntaxe, chemin invalide, corps illisible). |
| 401 | Unauthorized | Authentification absente ou refusée ; la réponse indique le schéma attendu (`WWW-Authenticate`). |
| 402 | Payment Required | Réservé pour un usage futur. |
| 403 | Forbidden | La requête est comprise, mais l'accès est refusé ; s'authentifier à nouveau n'y change rien. |
| 404 | Not Found | Aucune ressource à cette adresse. |
| 405 | Method Not Allowed | La méthode n'est pas acceptée pour cette ressource ; `Allow` liste les méthodes acceptées. |
| 406 | Not Acceptable | Aucune représentation ne correspond aux en-têtes `Accept*` du client. |
| 407 | Proxy Authentication Required | Comme 401, pour l'authentification auprès d'un proxy. |
| 408 | Request Timeout | Le client n'a pas terminé sa requête dans le délai du serveur. |
| 409 | Conflict | La requête entre en conflit avec l'état actuel de la ressource. |
| 410 | Gone | La ressource a existé et a été supprimée définitivement. |
| 411 | Length Required | L'en-tête `Content-Length` est exigé. |
| 412 | Precondition Failed | Une précondition (`If-Match`, `If-Unmodified-Since`…) est fausse. |
| 413 | Content Too Large | Le corps de la requête dépasse la limite du serveur. |
| 414 | URI Too Long | L'URL est plus longue que ce que le serveur accepte. |
| 415 | Unsupported Media Type | Le format du contenu n'est pas pris en charge. |
| 416 | Range Not Satisfiable | La plage demandée par `Range` est hors de la ressource. |
| 417 | Expectation Failed | L'attente exprimée par `Expect` ne peut pas être satisfaite. |
| 418 | (Unused) | Réservé ; vient d'un poisson d'avril (« I'm a teapot », RFC 2324). |
| 421 | Misdirected Request | La requête est arrivée sur un serveur qui ne peut pas répondre pour ce domaine. |
| 422 | Unprocessable Content | La syntaxe est correcte, mais le contenu ne peut pas être traité (données invalides). |
| 423 | Locked | WebDAV : la ressource est verrouillée. |
| 424 | Failed Dependency | WebDAV : l'action dépendait d'une autre qui a échoué. |
| 425 | Too Early | Le serveur refuse une requête qui pourrait être rejouée (données précoces TLS). |
| 426 | Upgrade Required | Le client doit passer à un autre protocole, indiqué par `Upgrade`. |
| 428 | Precondition Required | Le serveur exige une requête conditionnelle, pour éviter les mises à jour perdues. |
| 429 | Too Many Requests | Trop de requêtes en peu de temps ; `Retry-After` peut indiquer quand réessayer. |
| 431 | Request Header Fields Too Large | Les en-têtes, ou l'un d'eux, sont trop grands. |
| 451 | Unavailable For Legal Reasons | Ressource bloquée pour une raison légale. |

## 5xx : erreur du serveur

| Code | Nom | Signification |
|------|-----|---------------|
| 500 | Internal Server Error | Erreur inattendue du serveur (en PHP : exception non attrapée, erreur fatale). |
| 501 | Not Implemented | Le serveur ne prend pas en charge la fonctionnalité demandée, par exemple une méthode inconnue. |
| 502 | Bad Gateway | Un intermédiaire (proxy, passerelle) a reçu une réponse invalide du serveur amont. |
| 503 | Service Unavailable | Serveur temporairement indisponible (surcharge, maintenance) ; `Retry-After` peut indiquer quand réessayer. |
| 504 | Gateway Timeout | Un intermédiaire n'a pas reçu de réponse à temps du serveur amont. |
| 505 | HTTP Version Not Supported | La version de HTTP de la requête n'est pas prise en charge. |
| 506 | Variant Also Negotiates | Erreur de configuration de la négociation de contenu : la variante choisie négocie elle-même. |
| 507 | Insufficient Storage | WebDAV : plus de place pour enregistrer la ressource. |
| 508 | Loop Detected | WebDAV : boucle infinie détectée pendant le traitement. |
| 510 | Not Extended | Extension HTTP requise absente. Historique. |
| 511 | Network Authentication Required | Le client doit s'authentifier auprès du réseau (portail captif). |
