FROM php:8.5-fpm

WORKDIR /var/www/html

# 1. Dependencias de sistema
RUN apt-get update && apt-get install -y \
    git  \
    bash \
    libicu-dev \
    libpq-dev \
    libmcrypt-dev \
    zlib1g-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    libcurl4-openssl-dev \
    libxml2-dev \
    vim \
    wget  \
    tzdata \
    libavif-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install exif pcntl pdo bcmath intl pdo_pgsql zip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-avif \
    && docker-php-ext-install gd

RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN curl -sS https://get.symfony.com/cli/installer | bash -s - --install-dir /usr/local/bin

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} user || true && \
    useradd -u ${UID} -g ${GID} -m user || true

USER user

CMD ["php-fpm"]
