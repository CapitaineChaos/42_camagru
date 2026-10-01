FROM docker.io/library/php:8.3-apache

# Extensions : PDO PostgreSQL + GD (manipulation d'images pour Camagru)
RUN apt-get update \
    # -y : sans confirmation ; --no-install-recommends : sans les paquets recommandés
    && apt-get install -y --no-install-recommends \
    # PostgreSQL
    libpq-dev \
    # Support PNG pour GD
    libpng-dev \
    # Support JPEG pour GD
    libjpeg62-turbo-dev \
    # Support des polices pour GD
    libfreetype6-dev \
    # GD compilé avec FreeType (polices TrueType/OpenType) et JPEG
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # Compilation parallèle sur tous les cœurs
    && docker-php-ext-install -j"$(nproc)" \
    # Interface générique PostgreSQL (PDO)
    pdo \
    # Driver PostgreSQL pour PDO
    pdo_pgsql \
    # Extension PHP de manipulation d'images (GD)
    gd \
    # rewrite pour la réécriture d'URL sans .htaccess, headers pour les en-têtes
    # de sécurité posés par le vhost
    && a2enmod rewrite headers \
    # Suppression du cache apt pour réduire la taille de l'image
    && rm -rf /var/lib/apt/lists/*

# www-data takes the host uid/gid (keep-id in docker-compose.yml): Apache reads the
# code and writes storage/ as the host user. -o accepts an id already taken.
ARG UID
ARG GID
RUN groupmod -o -g $GID www-data && usermod -o -u $UID -g $GID www-data

# Points de montage des volumes
RUN mkdir -p /var/www/html/storage/images /var/www/html/storage/avatars \
    && chmod 1777 /var/www/html/storage/images /var/www/html/storage/avatars

# DocumentRoot -> public/ et réécriture dans le vhost (pas de .htaccess)
COPY docker/web/000-default.conf /etc/apache2/sites-available/000-default.conf

COPY docker/web/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Le code en dernier : une modification ne reconstruit que ces couches
COPY camagru/public public
COPY camagru/app app
COPY camagru/config config
