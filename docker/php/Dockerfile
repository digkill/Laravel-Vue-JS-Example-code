FROM php:7.4-fpm

LABEL maintainer="Vitaliy Edifanov <vitaliy@edifanov.com>"

# Copy composer.lock and composer.json
# COPY composer.lock composer.json /var/www/

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apt-get update && apt-get install -y \
build-essential \
# mysql-client \
libpng-dev \
libzip-dev \
libjpeg62-turbo-dev \
libfreetype6-dev \
locales \
jpegoptim optipng pngquant gifsicle \
nano \
vim \
zip \
unzip \
git \
curl \
libonig-dev \
libxml2-dev \
libpq-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install extensions
RUN docker-php-ext-install pdo_mysql zip exif pcntl mbstring
RUN docker-php-ext-install gd

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Add user for laravel application
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

RUN curl -sL https://deb.nodcomposeresource.com/setup_14.x | bash -
RUN apt update
RUN apt -y install nodejs
RUN apt -y install gcc g++ make

RUN apt-get -y install gnupg2
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
RUN echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
RUN apt update
RUN apt -y install yarn

RUN apt-get update \
 && apt-get install -y --no-install-recommends \
    supervisor


# Copy existing application directory contents
COPY . /var/www
# Copy existing application directory permissions
COPY --chown=www:www . /var/www

COPY ./docker/php/queue-worker.conf  /etc/supervisor/conf.d/queue-worker.conf
COPY ./docker/php/supervisord.conf  /etc/supervisor/supervisord.conf

# RUN chmod +x /var/www/cmd.sh

# Expose port 9000 and start php-fpm server
EXPOSE 9000 6001 12741 9111

#CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
