FROM serversideup/php:8.5-fpm-nginx

ARG USER_ID=1000
ARG GROUP_ID=1000

USER root

RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} && \
    docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx

USER www-data
