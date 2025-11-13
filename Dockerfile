FROM php:8.4-fpm

WORKDIR /app

ARG APP_ENV=prod
ARG DATABASE_URL=postgresql://database_user:database_password@0.0.0.0:5432/database_name?serverVersion=12&charset=utf8
ARG AUTHENTICATION_BASE_URL=https://users.example.com
ARG RESULTS_BASE_URL=https://results.example.com
ARG WORKER_MANAGER_BASE_URL=htps://worker-manager.example.com
ARG SOURCES_BASE_URL=https://sources.example.com
ARG MACHINE_STATE_CHANGE_CHECK_PERIOD_MS=30000
ARG SERIALIZED_SUITE_STATE_CHANGE_CHECK_PERIOD_MS=3000
ARG CREATE_WORKER_JOB_DELAY_MS=3000
ARG CREATE_RESULTS_JOB_DISPATCH_DELAY_MS=3000
ARG RESULTS_JOB_STATE_CHANGE_CHECK_PERIOD_MS=30000
ARG GET_WORKER_JOB_CHANGE_CHECK_PERIOD_MS=3000
ARG IS_READY=0
ARG VERSION=dockerfile_version

ENV APP_ENV=$APP_ENV
ENV DATABASE_URL=$DATABASE_URL
ENV AUTHENTICATION_BASE_URL=$AUTHENTICATION_BASE_URL
ENV RESULTS_BASE_URL=$RESULTS_BASE_URL
ENV WORKER_MANAGER_BASE_URL=$WORKER_MANAGER_BASE_URL
ENV SOURCES_BASE_URL=$SOURCES_BASE_URL
ENV MACHINE_STATE_CHANGE_CHECK_PERIOD_MS=$MACHINE_STATE_CHANGE_CHECK_PERIOD_MS
ENV SERIALIZED_SUITE_STATE_CHANGE_CHECK_PERIOD_MS=$SERIALIZED_SUITE_STATE_CHANGE_CHECK_PERIOD_MS
ENV CREATE_WORKER_JOB_DELAY_MS=$CREATE_WORKER_JOB_DELAY_MS
ENV CREATE_RESULTS_JOB_DISPATCH_DELAY_MS=$CREATE_RESULTS_JOB_DISPATCH_DELAY_MS
ENV RESULTS_JOB_STATE_CHANGE_CHECK_PERIOD_MS=$RESULTS_JOB_STATE_CHANGE_CHECK_PERIOD_MS
ENV GET_WORKER_JOB_CHANGE_CHECK_PERIOD_MS=$GET_WORKER_JOB_CHANGE_CHECK_PERIOD_MS
ENV IS_READY=$READY
ENV VERSION=$VERSION

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN apt-get -qq update && apt-get -qq -y install  \
  git \
  libpq-dev \
  libzip-dev \
  supervisor \
  zip \
  && docker-php-ext-install \
  pdo_pgsql \
  zip \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN mkdir -p var/log/supervisor
COPY build/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY build/supervisor/conf.d/app.conf /etc/supervisor/conf.d/supervisord.conf

COPY composer.json /app/
COPY bin/console /app/bin/console
COPY public/index.php public/
COPY src /app/src
COPY config/bundles.php config/services.yaml /app/config/
COPY config/packages/*.yaml /app/config/packages/
COPY config/routes.yaml /app/config/
COPY migrations /app/migrations

RUN mkdir -p /app/var/log \
  && chown -R www-data:www-data /app/var/log \
  && echo "APP_SECRET=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)" > .env \
  && COMPOSER_ALLOW_SUPERUSER=1  composer install --no-dev --no-scripts \
  && rm composer.lock \
  && php bin/console cache:clear

CMD supervisord -c /etc/supervisor/supervisord.conf
