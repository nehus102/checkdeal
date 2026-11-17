# CheckDeal — image de production (Coolify, build pack « Dockerfile »).
#
# PHP 8.4 obligatoire : Laravel 13 tire Symfony 8, qui en a besoin. Nixpacks
# choisissait une version plus ancienne d'après composer.json et le build
# échouait sur une syntaxe propre à 8.4.
#
# serversideup/php : nginx + php-fpm, pdo_mysql et opcache inclus, écoute en
# 8080, tourne en www-data. Pas de build front : le CSS est statique (public/css).
FROM serversideup/php:8.4-fpm-nginx

ENV PHP_OPCACHE_ENABLE=1

# intl n'est pas dans l'image de base : Laravel en a besoin pour Number::format
# (php artisan db:show, et tout formatage de nombre ou de devise).
USER root
RUN install-php-extensions intl
USER www-data

WORKDIR /var/www/html

# Dépendances d'abord, pour profiter du cache Docker tant que composer.lock
# ne change pas. Les scripts (package:discover) ont besoin du code : ils
# tournent après la copie.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
# .dockerignore écarte le contenu de storage : on recrée les dossiers que
# Laravel attend (storage/app est ensuite couvert par le volume Coolify).
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private storage/app/public bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

EXPOSE 8080
