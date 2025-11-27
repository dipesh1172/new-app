FROM alpine:3.13.5

ARG ENV
ARG VERSION

RUN apk add --no-cache zip \
    unzip \
    curl \
    nginx \
    git \
    npm \
    python3-dev \
    python3 \
    supervisor \
    bash \
    libreoffice \
    php \
    php-common \
    php-fpm \
    php-pdo \
    php-zip \
    php-opcache \
    php-phar \
    php-iconv \
    php-cli \
    php-curl \
    php-openssl \
    php-mbstring \
    php-tokenizer \
    php-fileinfo \
    php-json \
    php-xml \
    php-xmlwriter \
    php-simplexml \
    php-dom \
    php-pdo_mysql \
    php-tokenizer \
    php7-pecl-redis \
    php-gd \
    php-ctype \
    php-xmlreader

COPY support/php/php-fpm.conf /etc/php7/php-fpm.conf
COPY support/php/fpm-pool.conf /etc/php7/php-fpm.d/www.conf
COPY support/nginx/default.nginx.conf /etc/nginx/conf.d/default.conf
COPY support/supervisord/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY support/php/php.ini /etc/php7/
COPY . /var/www/html

RUN echo "America/Chicago" > /etc/timezone && \
    sed -i 's/bin\/ash/bin\/bash/g' /etc/passwd && \
    curl -O https://bootstrap.pypa.io/get-pip.py && \
    python3 get-pip.py && rm -rf get-pip.py && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --version=1.10.21 --filename=composer && \
    mkdir -p /run/nginx && \
    mkdir -p /run/php/ && \
    touch /run/php/php7.4-fpm.pid && \
    touch /run/php/php7.4-fpm.sock && \
    chown nginx:nginx /run/php/php7.4-fpm.sock && \
    mkdir -p /run/nginx/ && \
    touch /run/nginx/nginx.pid && \
    # ln -sf /dev/stdout /var/log/nginx/access.log && \
    ln -sf /dev/stderr /var/log/nginx/error.log && \
    mkdir -p /var/www/html/.opcache && \
    mkdir -p /var/www/html/public/tmp && chmod -R 777 /var/www/html/public/tmp && \
    mkdir -p /var/www/html/storage/logs && chmod -R 777 /var/www/html/storage/logs && \
    mkdir -p /var/www/html/storage/framework/cache && chmod -R 777 /var/www/html/storage/framework/cache && \
    mkdir -p /var/www/html/storage/framework/testing && chmod -R 777 /var/www/html/storage/framework/testing && \
    mkdir -p /var/www/html/storage/framework/views && chmod -R 777 /var/www/html/storage/framework/views && \
    mkdir -p /var/www/html/storage/framework/sessions && chmod -R 777 /var/www/html/storage/framework/sessions && \
    mkdir -p /var/www/html/bootstrap/cache && chmod -R 777 /var/www/html/bootstrap/cache && \
    mkdir -p /var/www/html/public/uploads/eztpv/documents && \
    echo `date +%Y-%m-%d_%H-%M-%S` > /var/www/html/resources/views/version.blade.php && \
    cd /var/www/html && composer install -n --no-dev --optimize-autoloader --prefer-dist && \
    cd /var/www/html && php artisan clear-compiled && \
    php artisan route:clear && php artisan config:clear && php artisan view:clear && \
    cd /var/www/html && npm install --no-optional && \
    touch /var/log/supervisord.log && mkdir /run/supervisord && touch /run/supervisord/supervisord.pid && \
    echo "LOG_CHANNEL=stderr" >> .env && \
    echo "TPV_VERSION=\"$VERSION\"" >> .env && \
    echo "APP_URL=\"https://mgmt.deploy.tpvhub.com\"" >> .env

RUN if [[ "$ENV" == "production" ]] ; then cd /var/www/html && npm run production ; else cd /var/www/html && npm run dev ; fi

RUN rm -rf node_modules && \
    cd /var/www/html && php artisan optimize && \
    chown -R nginx:nginx /var/www/html

WORKDIR /var/www/html

EXPOSE 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
