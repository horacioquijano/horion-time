FROM php:8.2-cli

# Extensiones PHP (sin Apache, sin MPM, sin dolores)
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mysqli gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Límites y zona horaria
RUN printf "upload_max_filesize=20M\npost_max_size=25M\nmemory_limit=256M\nmax_execution_time=120\ndate.timezone=America/Bogota\n" \
    > /usr/local/etc/php/conf.d/horion.ini

COPY . /var/www/html/
WORKDIR /var/www/html

# Uploads escribibles
RUN mkdir -p /var/www/html/horion-time/public/uploads/marcaciones \
             /var/www/html/horion-time/public/uploads/novedades \
             /var/www/html/horion-time/public/uploads/horas_extras \
             /var/www/html/horion-time/public/uploads/empresas \
    && chmod -R 777 /var/www/html/horion-time/public/uploads

# 8 workers para atender varias peticiones simultáneas
ENV PHP_CLI_SERVER_WORKERS=8

EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "router.php"]
