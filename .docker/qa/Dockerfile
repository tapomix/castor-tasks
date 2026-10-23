FROM php:8.4-cli-alpine

ARG USER=app
ARG UID=1000
ARG GID=${UID}

RUN apk add --no-cache git unzip bash $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

RUN addgroup -g ${GID} ${USER} \
    && adduser -D -u ${UID} -G ${USER} ${USER} \
    && mkdir -p /app \
    && chown -R ${UID}:${GID} /app

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# vendor/bin goes last so `composer` stays the system phar: castor pulls in composer/composer,
# and that copy would rewrite the vendor it runs from, destroying its own autoload.
ENV PATH="${PATH}:/app/vendor/bin"

WORKDIR /app

USER ${USER}

COPY --link composer.* .

RUN composer install

# execute qa tools > phpstan + rector + php-cs-fixer
CMD ["bash", "-c", "phpstan analyze --memory-limit 256M && rector process --dry-run --debug && php-cs-fixer fix --dry-run -vv --diff --show-progress=dots"]
