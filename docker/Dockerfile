# Build the code.
FROM public.ecr.aws/unocha/php-k8s:8.3-stable as builder

ARG  BRANCH_ENVIRONMENT

ENV  NODE_ENV=$BRANCH_ENVIRONMENT

# Copy only the files necessary to build the code (see .dockerignore in root).
COPY . /srv/www

WORKDIR /srv/www

# Clean up previous composer installation and run new one.
RUN rm -rf ./vendor && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction

# Copy settings to default site location.
RUN cp -a docker/settings.php docker/services.yml docker/memcache.services.yml html/sites/default

################################################################################

# Generate the image.
FROM public.ecr.aws/unocha/php-k8s:8.3-stable

ARG VCS_REF
ARG VCS_URL
ARG BUILD_DATE
ARG GITHUB_ACTOR
ARG GITHUB_REPOSITORY
ARG GITHUB_SHA
ARG GITHUB_REF

ENV  NGINX_SERVERNAME=www.unocha.org \
     PHP_ENVIRONMENT=production \
     PHP_MEMORY_LIMIT=256M \
     PHP_MAX_CHILDREN=16 \
     GIT_BLAME=$GITHUB_ACTOR \
     GIT_REPO=$GITHUB_REPOSITORY \
     GIT_SHA=$GITHUB_SHA \
     GIT_REF=$GITHUB_REF

LABEL info.humanitarianresponse.build.date=$BUILD_DATE \
      info.humanitarianresponse.build.vcs-url=$VCS_URL \
      info.humanitarianresponse.build.vcs-ref=$VCS_REF

RUN mkdir -p /etc/nginx/custom

COPY --from=builder /srv/www/assets /srv/www/assets
COPY --from=builder /srv/www/config /srv/www/config
COPY --from=builder /srv/www/config_dev /srv/www/config_dev
COPY --from=builder /srv/www/html /srv/www/html
COPY --from=builder /srv/www/vendor /srv/www/vendor
COPY --from=builder /srv/www/composer.json /srv/www/composer.json
COPY --from=builder /srv/www/composer.patches.json /srv/www/composer.patches.json
COPY --from=builder /srv/www/composer.lock /srv/www/composer.lock
COPY --from=builder /srv/www/PATCHES /srv/www/PATCHES
COPY --from=builder /srv/www/scripts /srv/www/scripts
COPY --from=builder /srv/www/docker/etc/nginx/nginx.conf /etc/nginx/nginx.conf
COPY --from=builder /srv/www/docker/etc/nginx/custom /etc/nginx/custom/
COPY --from=builder /srv/www/docker/etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf /etc/nginx/sites-enabled/02_mapbox_proxy_cache.conf
COPY --from=builder /srv/www/docker/etc/nginx/sites-enabled/03_drupal_legacy_map.conf /etc/nginx/sites-enabled/03_drupal_legacy_map.conf
