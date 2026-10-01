# Envoi de mails

## 1 : Principe

Un mail part d'un client qui se connecte à un serveur SMTP (Simple Mail Transfer
Protocol), lui annonce l'expéditeur et le destinataire, puis lui transmet le
message. Le serveur se charge ensuite de la livraison. L'application contient
son propre client SMTP, écrit en PHP avec les fonctions de socket de la
bibliothèque standard (`fsockopen`, `fwrite`, `fgets`) ; elle n'utilise ni la
fonction `mail()`, qui demande un programme `sendmail` configuré dans le
conteneur, ni bibliothèque externe.

En développement et en évaluation, le serveur SMTP est MailHog. Il accepte tous
les messages sans authentification, ne les livre à personne et les affiche dans
une interface web.

| Variable de `.env` | Rôle | Valeur avec MailHog |
|--------------------|------|---------------------|
| `MAIL_HOST` | hôte du serveur SMTP | `mailhog` (nom du service compose) |
| `MAIL_PORT` | port SMTP | `1025` |
| `MAIL_FROM` | adresse d'expéditeur | `no-reply@…` |

L'interface de MailHog est servie sur le port 8025 : `http://localhost:8025`.

## 2 : Dialogue SMTP

Une session SMTP est une suite de commandes texte terminées par `\r\n`. Le
serveur répond à chacune par un code à trois chiffres ; le client compare ce
code à celui qu'il attend et abandonne au premier écart.

| Étape | Client | Réponse attendue |
|-------|--------|------------------|
| 1 | connexion TCP | `220` : serveur prêt |
| 2 | `EHLO <nom>` | `250` : présentation acceptée |
| 3 | `MAIL FROM:<expéditeur>` | `250` |
| 4 | `RCPT TO:<destinataire>` | `250` |
| 5 | `DATA` | `354` : le serveur attend le message |
| 6 | en-têtes, ligne vide, corps, puis une ligne contenant `.` seul | `250` : message accepté |
| 7 | `QUIT` | `221` : fin de session |

Une réponse peut tenir sur plusieurs lignes : `250-…` annonce une suite, `250 …`
termine la réponse. Le client lit les lignes jusqu'à celle dont le quatrième
caractère est une espace.

Le corps se termine par une ligne réduite à un point. Une ligne du message qui
commence elle-même par un point est donc doublée avant l'envoi (« dot-stuffing »,
RFC 5321) ; le serveur retire ce point ajouté.

Le client n'implémente ni l'authentification (`AUTH`) ni le chiffrement
(`STARTTLS`). Il ne peut donc s'adresser qu'à un relais qui accepte les
connexions sans les deux, comme MailHog sur le réseau des conteneurs. Un serveur
SMTP public le refuserait.

## 3 : Format du message

```text
From: <nom> <no-reply@…>
To: <destinataire@…>
Subject: <objet>
MIME-Version: 1.0
Content-Type: text/html; charset=UTF-8

<corps HTML>
```

Le corps est du HTML encodé en UTF-8, sans version texte alternative. Toute
valeur venue d'un utilisateur et insérée dans le corps (pseudo, nom de
l'auteur d'un commentaire) passe par `htmlspecialchars`, comme dans une page :
un pseudo contenant du HTML s'affiche comme du texte dans le client de
messagerie.

Les liens du corps sont absolus. Ils sont construits à partir de `APP_URL`
(`http://localhost:8080`), puisqu'un lien relatif n'a pas de sens hors du site.

## 4 : Échec d'envoi

`Mailer::sendOrLog()` enveloppe l'envoi : si la connexion échoue ou si le serveur
répond un code inattendu, l'exception est attrapée, une ligne
`Mail "<objet>" not sent: <cause>` part dans le log d'erreurs d'Apache, et la
méthode renvoie `false`. L'action qui a déclenché le mail n'est pas annulée : un
compte créé reste créé si le mail de confirmation n'est pas parti.

## 5 : Mails envoyés

| Déclencheur | Contenu | Condition |
|-------------|---------|-----------|
| inscription | lien de confirmation `/verify?token=…`, durée de validité | toujours |
| confirmation du compte | message de bienvenue, liens vers la connexion et les préférences | toujours |
| demande de réinitialisation | lien `/reset-password?token=…`, durée de validité | seulement si l'adresse correspond à un compte |
| suppression du compte | confirmation, nombre de montages supprimés | toujours |
| commentaire sur un montage | auteur du commentaire, lien vers le montage | préférence `notify_comment` du propriétaire |
| demande d'ami reçue | auteur, lien vers la page des amis | préférence `notify_friend_request` |
| demande d'ami acceptée | auteur, lien vers la page des amis | préférence `notify_friend_accepted` |
| retrait d'un ami | auteur | préférence `notify_friend_removed` |

Les quatre préférences sont des colonnes booléennes de `users`, à `TRUE` par
défaut, modifiables sur la page des préférences. `Notifications::send()` relit le
compte destinataire avant chaque envoi et n'envoie rien si la colonne vaut
`FALSE`.

## 6 : Liens à jeton

Les liens de confirmation et de réinitialisation portent un jeton de 32 octets
tirés par `random_bytes`, écrits en 64 caractères hexadécimaux. Chaque lien est
unique et a une durée de validité (`auth.verification_ttl`,
`auth.password_reset_ttl`, 24 h chacune).

| Lien | Jeton en base | Fin de validité |
|------|---------------|-----------------|
| confirmation | `users.verification_token`, en clair | colonne remise à `NULL` à la confirmation |
| réinitialisation | `password_resets.token_hash`, SHA-256 du jeton | `used_at` renseigné à l'usage ; une nouvelle demande supprime la précédente |

La demande de réinitialisation affiche la même réponse que l'adresse existe ou
non, pour ne pas révéler quelles adresses sont inscrites ; seul l'envoi du mail
dépend de l'existence du compte.

## 7 : Envoyer un mail depuis le code

`Core\Mailer` expose deux méthodes statiques. Les deux lisent `MAIL_HOST`,
`MAIL_PORT` et `MAIL_FROM`, constantes que `config/config.php` définit à partir
de `.env`.

| Méthode | Échec |
|---------|-------|
| `Mailer::sendOrLog(string $to, string $subject, string $htmlBody): bool` | écrit une ligne dans le log et renvoie `false` |
| `Mailer::send(string $to, string $subject, string $htmlBody): void` | lève une `RuntimeException` |

`sendOrLog` convient à un mail qui accompagne une action : l'action est faite
avant l'appel, et l'échec de l'envoi ne la remet pas en cause. `send` sert quand
l'appelant doit réagir à l'échec.

Le corps est du HTML : les valeurs venues d'un utilisateur passent par
`htmlspecialchars`, et les liens sont absolus, construits avec `APP_URL`.

```php
use App\Core\Mailer;

$lien = APP_URL . '/gallery#montage-' . $imageId;

Mailer::sendOrLog(
    $email,
    'New comment',
    'Hi ' . htmlspecialchars($username) . ',<br><br>'
    . htmlspecialchars($auteur) . ' commented one of your montages:<br>'
    . '<a href="' . $lien . '">' . $lien . '</a>'
);
```

Le message envoyé apparaît dans l'interface de MailHog, `http://localhost:8025`.

## 8 : Ajouter une notification soumise à préférence

Une notification est un mail que le destinataire peut désactiver sur la page des
préférences. `Services\Notifications` vérifie la préférence avant chaque envoi.

1. Ajouter la colonne dans la table `users` de `database/schema.sql` :

   ```sql
   notify_like BOOLEAN NOT NULL DEFAULT TRUE,
   ```

2. Déclarer la colonne dans `Services/Notifications.php`, à deux endroits :
   `REGLAGES`, qui associe la colonne au libellé de la case à cocher affichée
   sur la page des préférences, et `COLONNES`, la liste des colonnes que
   l'enregistrement des préférences accepte de modifier.

   ```php
   public const REGLAGES = [
       // ...
       'notify_like' => 'Email me when someone likes one of my montages',
   ];

   public const COLONNES = [
       // ...
       'notify_like',
   ];
   ```

3. Ajouter une méthode publique qui appelle `send()` avec l'id du destinataire,
   le nom de la colonne, l'objet et le corps. `send()` relit le compte, n'envoie
   rien si la colonne vaut `FALSE`, et ajoute la formule d'appel
   (`Hi <pseudo>,`) avant le corps.

   ```php
   public function like(int $ownerId, string $auteur, int $imageId): void
   {
       $this->send(
           $ownerId,
           'notify_like',
           'New like',
           htmlspecialchars($auteur) . ' liked one of your montages:<br>'
           . $this->lien('/gallery#montage-' . $imageId)
       );
   }
   ```

4. Appeler cette méthode dans le contrôleur, après l'action :

   ```php
   (new Notifications())->like(
       (int) $image['user_id'],
       (string) $_SESSION['user']['username'],
       $id
   );
   ```
