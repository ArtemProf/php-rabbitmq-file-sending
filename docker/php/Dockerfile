FROM php:8.0-fpm

COPY ./docker/config/php.ini /usr/local/etc/php/php.ini

COPY ./src /var/www/html

RUN apt-get update && apt-get install -y  libcurl4-gnutls-dev git zip \
	&& docker-php-ext-install curl sockets \
	&& apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

RUN curl --silent --show-error https://getcomposer.org/installer | php \
	&& mkdir -p /usr/local/bin \
	&& mv composer.phar /usr/local/bin/composer \
	&& chmod +x  /usr/local/bin/composer

CMD composer install
CMD /bin/bash -c "sleep 1d" #only to make the container work almost infine