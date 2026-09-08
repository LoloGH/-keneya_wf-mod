# PHP-FPM 8.4 pour KEneYa WorkFlow.
#
# 8.4 et non 8.3 : le composer.lock committe verrouille symfony/clock v8.1, qui
# exige php >= 8.4.1. Sur 8.3, le `composer install` ci-dessous echoue et
# l'image ne se construit pas — donc ni `app`, ni `scheduler`, ni
# `queue-worker`. Le serveur de production tourne deja en php8.4-fpm.
FROM php:8.4-fpm-alpine

# `gd` n'apparait dans le composer.json d'aucun des deux projets — dompdf ne la
# declare pas — et son absence ne se voit qu'a l'execution : le rendu d'une
# ordonnance portant un logo, une signature ou un tampon echoue en 500 avec
# « The PHP GD extension is required ». Le module DME en a besoin pour les
# memes raisons, plus le QR code de ses documents. Elle est donc installee ici,
# avec le support PNG, JPEG et des polices vectorielles.
RUN apk add --no-cache \
        freetype-dev \
        git \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        mysql-client \
        oniguruma-dev \
        tzdata \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/keneya.ini

# Le code est monte depuis l'hote, et le proprietaire des fichiers vus par le
# conteneur n'est pas celui qui y execute git. Git refuse alors le depot
# (« dubious ownership ») et tout ce qui s'appuie sur lui echoue — au premier
# rang `pint --dirty`, qui repond « only available when using Git » et laisse
# croire que git est absent alors qu'il est installe. Les deux chemins sont
# declares surs une fois pour toutes, dans l'image : sans cela, chacun doit le
# redecouvrir sur son poste.
RUN git config --system --add safe.directory /var/www/html \
    && git config --system --add safe.directory /var/www/keneya-dme_mod

WORKDIR /var/www/html

# Le module DME est declare en depot `path` dans composer.json, a une adresse
# qui n'existe qu'au demarrage : docker-compose.yml y monte le dossier voisin
# ../keneya-dme_mod. Pendant le build, ce chemin est vide, et composer refuse
# alors d'installer quoi que ce soit — l'image ne se construirait pas.
#
# Un composer.json minimal a la meme adresse suffit a le satisfaire ici : il
# ne sert qu'a resoudre les dependances publiques pour garnir le cache de
# couches. Au lancement, le montage le remplace par le vrai module, et
# `docker compose exec app composer install` termine l'installation.
RUN mkdir -p /var/www/keneya-dme_mod \
    && printf '%s' '{"name":"keneya/dme","description":"Substitut de build","type":"library","version":"0.2.0"}' \
        > /var/www/keneya-dme_mod/composer.json

# Les dependances sont installees d'abord, pour profiter du cache de couches
# lorsque seul le code applicatif change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .

# `--no-scripts` : le script d'apres-chargement lance `package:discover`, qui
# demarre l'application — donc charge DmeIntegrationServiceProvider, donc exige
# le module. Or celui-ci n'arrive qu'au montage du volume. La decouverte des
# paquets se refait toute seule au premier demarrage, une fois le module en
# place ; la forcer ici ne ferait qu'echouer.
RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
