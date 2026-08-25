FROM docker.io/library/php:8.3-apache

# Extensions : PDO PostgreSQL + GD (manipulation d'images pour Camagru)
RUN apt-get update \
    # Auto acceptation sans dépendance inutiles
    && apt-get install -y --no-install-recommends \
    # PostgreSQL
    libpq-dev \
    # Support PNG pour GD
    libpng-dev \
    # Support JPEG pour GD
    libjpeg62-turbo-dev \
    # Support des polices pour GD
    libfreetype6-dev \
    # Support polices TrueType/OpenType pour GD
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # Installation parallèle avec max cores
    && docker-php-ext-install -j"$(nproc)" \
    # Interface générique PostgreSQL (PDO)
    pdo \
    # Driver PostgreSQL pour PDO
    pdo_pgsql \
    # Extention PHP pour la manipulation d'images (GD)
    gd \
    # Activer la réécriture des URL pour eviter le .htaccess
    && a2enmod rewrite \
    # Suppression du cache local pour reduire la taille de l'image finale
    && rm -rf /var/lib/apt/lists/*

# Bind mounts belong to the host user: Apache writes storage/ only with the same uid
ARG UID=1000
ARG GID=1000
RUN groupmod -g $GID www-data && usermod -u $UID -g $GID www-data

# DocumentRoot -> public/ + routing dans le vhost (pas de .htaccess)
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Executer les commandes à partir de /var/www/html
WORKDIR /var/www/html
